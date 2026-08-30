<?php
namespace App\Http\Controllers;
use App\Models\Note;
use Illuminate\Http\Request;

class NoteController extends Controller
{
    // Whitelist of entities notes can attach to — never resolve the
    // morph type from an unvalidated client string.
    private const NOTABLE_TYPES = [
        'lead' => \App\Models\Lead::class,
        'customer' => \App\Models\Customer::class,
        'booking' => \App\Models\Booking::class,
        'quotation' => \App\Models\Quotation::class,
        'support_ticket' => \App\Models\SupportTicket::class,
        'student_visa_application' => \App\Models\StudentVisaApplication::class,
        'pilgrim' => \App\Models\Pilgrim::class,
    ];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'notable_type' => 'required|in:' . implode(',', array_keys(self::NOTABLE_TYPES)),
            'notable_id' => 'required|integer',
        ]);
        $notes = Note::where('tenant_id', $request->user->tenant_id)
            ->where('notable_type', self::NOTABLE_TYPES[$validated['notable_type']])
            ->where('notable_id', $validated['notable_id'])
            ->orderByDesc('pinned')
            ->latest()
            ->get();
        return response()->json(['data' => $notes]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'notable_type' => 'required|in:' . implode(',', array_keys(self::NOTABLE_TYPES)),
            'notable_id' => 'required|integer',
            'body' => 'required|string',
            'pinned' => 'boolean',
        ]);
        $note = Note::create([
            'tenant_id' => $request->user->tenant_id,
            'notable_type' => self::NOTABLE_TYPES[$validated['notable_type']],
            'notable_id' => $validated['notable_id'],
            'user_id' => $request->user->id,
            'body' => $validated['body'],
            'pinned' => $validated['pinned'] ?? false,
        ]);
        return response()->json(['data' => $note], 201);
    }
    public function update(Request $request, $id)
    {
        $note = Note::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $validated = $request->validate([
            'body' => 'sometimes|string',
            'pinned' => 'boolean',
        ]);
        $note->update($validated);
        return response()->json(['data' => $note]);
    }
    public function destroy(Request $request, $id)
    {
        $note = Note::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $note->delete();
        return response()->json(['message' => 'Note deleted']);
    }
}
