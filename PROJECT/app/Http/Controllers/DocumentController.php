<?php
namespace App\Http\Controllers;
use App\Models\Document;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    // Whitelist of entities documents can attach to — never resolve the
    // morph type from an unvalidated client string.
    private const DOCUMENTABLE_TYPES = [
        'lead' => \App\Models\Lead::class,
        'customer' => \App\Models\Customer::class,
        'booking' => \App\Models\Booking::class,
        'visa_application' => \App\Models\VisaApplication::class,
        'support_ticket' => \App\Models\SupportTicket::class,
    ];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'documentable_type' => 'required|in:' . implode(',', array_keys(self::DOCUMENTABLE_TYPES)),
            'documentable_id' => 'required|integer',
        ]);
        $documents = Document::where('tenant_id', $request->user->tenant_id)
            ->where('documentable_type', self::DOCUMENTABLE_TYPES[$validated['documentable_type']])
            ->where('documentable_id', $validated['documentable_id'])
            ->latest()
            ->get();
        return response()->json(['data' => $documents]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'documentable_type' => 'required|in:' . implode(',', array_keys(self::DOCUMENTABLE_TYPES)),
            'documentable_id' => 'required|integer',
            'file' => 'required|file|max:20480',
            'category' => 'nullable|string',
        ]);
        $file = $request->file('file');
        $path = $file->store('documents/' . $request->user->tenant_id, 'local');

        $document = Document::create([
            'tenant_id' => $request->user->tenant_id,
            'documentable_type' => self::DOCUMENTABLE_TYPES[$validated['documentable_type']],
            'documentable_id' => $validated['documentable_id'],
            'uploaded_by' => $request->user->id,
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'disk' => 'local',
            'file_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'category' => $validated['category'] ?? null,
        ]);
        return response()->json(['data' => $document], 201);
    }
    public function destroy(Request $request, $id)
    {
        $document = Document::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        \Illuminate\Support\Facades\Storage::disk($document->disk)->delete($document->file_path);
        $document->delete();
        return response()->json(['message' => 'Document deleted']);
    }
}
