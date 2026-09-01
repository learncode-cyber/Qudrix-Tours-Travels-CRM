<?php
namespace App\Http\Controllers;
use App\Models\Expense;
use Illuminate\Http\Request;

class ExpenseController extends Controller
{
    public function create(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'category' => 'required|string',
            'amount' => 'required|numeric',
            'currency' => 'required|string|size:3',
            'expense_date' => 'required|date',
        ]);
        $expense = Expense::create(['tenant_id' => $request->user->tenant_id, ...$validated]);
        return response()->json(['data' => $expense], 201);
    }
    public function getByBooking(Request $request, $bookingId)
    {
        $expenses = Expense::where('tenant_id', $request->user->tenant_id)
            ->where('booking_id', $bookingId)->get();
        $stats = ['total' => $expenses->sum('amount'), 'count' => $expenses->count(), 'by_category' => $expenses->groupBy('category')->map->sum('amount')];
        return response()->json(['data' => $expenses, 'stats' => $stats]);
    }
}
