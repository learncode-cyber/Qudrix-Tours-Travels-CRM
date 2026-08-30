<?php
namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class HrmController extends Controller
{
    // ---------------- Employees ----------------
    public function employees(Request $request)
    {
        $employees = Employee::where('tenant_id', $request->user->tenant_id)
            ->with('user:id,name,email', 'branch:id,name')
            ->when($request->department, fn ($q, $v) => $q->where('department', $v))
            ->when($request->employment_status, fn ($q, $v) => $q->where('employment_status', $v))
            ->paginate($request->per_page ?? 20);

        return response()->json(['data' => $employees->items(), 'total' => $employees->total()]);
    }

    public function storeEmployee(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
            'employee_code' => 'required|string|max:64|unique:employees,employee_code',
            'department' => 'nullable|string',
            'designation' => 'nullable|string',
            'reporting_to' => 'nullable|exists:employees,id',
            'branch_id' => 'nullable|exists:branches,id',
            'joining_date' => 'nullable|date',
            'phone' => 'nullable|string',
            'address' => 'nullable|string',
            'basic_salary' => 'nullable|numeric|min:0',
            'salary_currency' => 'nullable|string|size:3',
        ]);

        $employee = Employee::create([
            'tenant_id' => $request->user->tenant_id,
            'employment_status' => 'active',
            ...$validated,
        ]);

        return response()->json(['data' => $employee], 201);
    }

    // ---------------- Attendance ----------------
    public function checkIn(Request $request)
    {
        $validated = $request->validate(['employee_id' => 'required|exists:employees,id']);
        $employee = Employee::where('tenant_id', $request->user->tenant_id)->findOrFail($validated['employee_id']);

        $attendance = Attendance::firstOrNew([
            'employee_id' => $employee->id,
            'work_date' => today()->toDateString(),
        ]);

        if ($attendance->check_in_at) {
            return response()->json(['error' => 'Already checked in today.'], 422);
        }

        $attendance->fill([
            'tenant_id' => $employee->tenant_id,
            'check_in_at' => now(),
            // Late is derived from the configured start time, not guessed.
            'status' => now()->format('H:i') > config('hrm.work_start_time', '09:30') ? 'late' : 'present',
        ])->save();

        return response()->json(['data' => $attendance]);
    }

    public function checkOut(Request $request)
    {
        $validated = $request->validate(['employee_id' => 'required|exists:employees,id']);
        $employee = Employee::where('tenant_id', $request->user->tenant_id)->findOrFail($validated['employee_id']);

        $attendance = Attendance::where('employee_id', $employee->id)
            ->where('work_date', today()->toDateString())
            ->first();

        if (!$attendance || !$attendance->check_in_at) {
            return response()->json(['error' => 'No check-in recorded for today.'], 422);
        }

        $attendance->update(['check_out_at' => now()]);

        return response()->json(['data' => $attendance->fresh()->toArray() + ['hours_worked' => $attendance->fresh()->hoursWorked()]]);
    }

    public function attendance(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'nullable|exists:employees,id',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        $records = Attendance::where('tenant_id', $request->user->tenant_id)
            ->when($validated['employee_id'] ?? null, fn ($q, $v) => $q->where('employee_id', $v))
            ->when($validated['from'] ?? null, fn ($q, $v) => $q->where('work_date', '>=', $v))
            ->when($validated['to'] ?? null, fn ($q, $v) => $q->where('work_date', '<=', $v))
            ->with('employee:id,employee_code,department')
            ->orderByDesc('work_date')
            ->paginate($request->per_page ?? 50);

        return response()->json(['data' => $records->items(), 'total' => $records->total()]);
    }

    // ---------------- Leave ----------------
    public function leaveTypes(Request $request)
    {
        return response()->json(['data' => LeaveType::where('tenant_id', $request->user->tenant_id)->get()]);
    }

    public function storeLeaveType(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'annual_quota_days' => 'nullable|integer|min:0',
            'is_paid' => 'boolean',
        ]);

        return response()->json([
            'data' => LeaveType::create(['tenant_id' => $request->user->tenant_id, ...$validated]),
        ], 201);
    }

    public function requestLeave(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string',
        ]);

        $employee = Employee::where('tenant_id', $request->user->tenant_id)->findOrFail($validated['employee_id']);

        // Real day count, inclusive of both ends.
        $days = Carbon::parse($validated['start_date'])->diffInDays(Carbon::parse($validated['end_date'])) + 1;

        $leave = LeaveRequest::create([
            'tenant_id' => $employee->tenant_id,
            'status' => 'pending',
            'days' => $days,
            ...$validated,
        ]);

        return response()->json(['data' => $leave], 201);
    }

    public function decideLeave(Request $request, $id)
    {
        $leave = LeaveRequest::where('tenant_id', $request->user->tenant_id)->findOrFail($id);

        if ($leave->status !== 'pending') {
            return response()->json(['error' => 'This request has already been decided.'], 422);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'decision_note' => 'nullable|string',
        ]);

        $leave->update([
            ...$validated,
            'decided_by' => $request->user->id,
            'decided_at' => now(),
        ]);

        // An approved leave writes real attendance rows, so payroll and
        // attendance reports agree instead of contradicting each other.
        if ($validated['status'] === 'approved') {
            $cursor = $leave->start_date->copy();
            while ($cursor->lte($leave->end_date)) {
                Attendance::updateOrCreate(
                    ['employee_id' => $leave->employee_id, 'work_date' => $cursor->toDateString()],
                    ['tenant_id' => $leave->tenant_id, 'status' => 'leave', 'note' => 'Approved leave #' . $leave->id],
                );
                $cursor->addDay();
            }
        }

        return response()->json(['data' => $leave->fresh()]);
    }

    public function leaveRequests(Request $request)
    {
        $leaves = LeaveRequest::where('tenant_id', $request->user->tenant_id)
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->with('employee:id,employee_code', 'leaveType:id,name')
            ->latest()
            ->paginate($request->per_page ?? 20);

        return response()->json(['data' => $leaves->items(), 'total' => $leaves->total()]);
    }

    // ---------------- Holidays ----------------
    public function holidays(Request $request)
    {
        return response()->json([
            'data' => Holiday::where('tenant_id', $request->user->tenant_id)->orderBy('holiday_date')->get(),
        ]);
    }

    public function storeHoliday(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'is_recurring' => 'boolean',
        ]);

        return response()->json([
            'data' => Holiday::create(['tenant_id' => $request->user->tenant_id, ...$validated]),
        ], 201);
    }

    // ---------------- Payroll ----------------
    // Generates a draft run from real employee salaries and real attendance.
    public function generatePayroll(Request $request)
    {
        $validated = $request->validate([
            'period' => 'required|date_format:Y-m',
        ]);

        $tenantId = $request->user->tenant_id;
        $period = $validated['period'];

        if (PayrollRun::where('tenant_id', $tenantId)->where('period', $period)->exists()) {
            return response()->json(['error' => "A payroll run for {$period} already exists."], 422);
        }

        $start = Carbon::createFromFormat('Y-m', $period)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        $employees = Employee::where('tenant_id', $tenantId)
            ->where('employment_status', 'active')
            ->get();

        if ($employees->isEmpty()) {
            return response()->json(['error' => 'No active employees to run payroll for.'], 422);
        }

        $run = DB::transaction(function () use ($tenantId, $period, $employees, $start, $end) {
            $run = PayrollRun::create([
                'tenant_id' => $tenantId,
                'period' => $period,
                'status' => 'draft',
            ]);

            foreach ($employees as $employee) {
                $present = Attendance::where('employee_id', $employee->id)
                    ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                    ->whereIn('status', ['present', 'late', 'half_day'])
                    ->count();

                $absent = Attendance::where('employee_id', $employee->id)
                    ->whereBetween('work_date', [$start->toDateString(), $end->toDateString()])
                    ->where('status', 'absent')
                    ->count();

                $basic = (float) ($employee->basic_salary ?? 0);

                PayrollItem::create([
                    'payroll_run_id' => $run->id,
                    'employee_id' => $employee->id,
                    'basic_salary' => $basic,
                    // Allowances and deductions are entered by finance; the
                    // system does not invent them.
                    'allowances' => 0,
                    'deductions' => 0,
                    'net_pay' => $basic,
                    'days_present' => $present,
                    'days_absent' => $absent,
                    'note' => $employee->basic_salary === null
                        ? 'No basic_salary set for this employee — recorded as 0, needs finance input'
                        : null,
                ]);
            }

            $run->recalculateTotals();

            return $run;
        });

        return response()->json([
            'data' => $run->fresh()->load('items.employee:id,employee_code'),
            'note' => 'Draft run generated from real salaries and attendance. Allowances and deductions '
                . 'must be entered before approval; employees without a basic_salary are flagged in item notes.',
        ], 201);
    }

    public function updatePayrollItem(Request $request, $runId, $itemId)
    {
        $run = PayrollRun::where('tenant_id', $request->user->tenant_id)->findOrFail($runId);

        if ($run->status !== 'draft') {
            return response()->json(['error' => 'Only a draft payroll run can be edited.'], 422);
        }

        $item = PayrollItem::where('payroll_run_id', $run->id)->findOrFail($itemId);

        $validated = $request->validate([
            'basic_salary' => 'sometimes|numeric|min:0',
            'allowances' => 'sometimes|numeric|min:0',
            'deductions' => 'sometimes|numeric|min:0',
            'note' => 'nullable|string',
        ]);

        $item->fill($validated);
        // Net pay is always derived, never accepted from the request.
        $item->net_pay = round(((float) $item->basic_salary) + ((float) $item->allowances) - ((float) $item->deductions), 2);
        $item->save();

        $run->recalculateTotals();

        return response()->json(['data' => $item->fresh()]);
    }

    public function approvePayroll(Request $request, $runId)
    {
        $run = PayrollRun::where('tenant_id', $request->user->tenant_id)->findOrFail($runId);

        if ($run->status !== 'draft') {
            return response()->json(['error' => 'This run is not in draft.'], 422);
        }

        $run->recalculateTotals();
        $run->update([
            'status' => 'approved',
            'approved_by' => $request->user->id,
            'approved_at' => now(),
        ]);

        return response()->json(['data' => $run->fresh()]);
    }

    public function payrollRuns(Request $request)
    {
        return response()->json([
            'data' => PayrollRun::where('tenant_id', $request->user->tenant_id)
                ->withCount('items')
                ->orderByDesc('period')
                ->get(),
        ]);
    }

    public function payrollRun(Request $request, $runId)
    {
        $run = PayrollRun::where('tenant_id', $request->user->tenant_id)
            ->with('items.employee:id,employee_code,department')
            ->findOrFail($runId);

        return response()->json(['data' => $run]);
    }
}
