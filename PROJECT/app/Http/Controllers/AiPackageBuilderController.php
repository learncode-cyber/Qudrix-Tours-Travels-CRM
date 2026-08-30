<?php
namespace App\Http\Controllers;

use App\Services\Ai\AiPackageBuilderService;
use App\Services\Ai\AiProviderException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AiPackageBuilderController extends Controller
{
    public function __construct(private AiPackageBuilderService $builder)
    {
    }

    // Free text -> structured requirements. No inventory or price claims.
    public function interpret(Request $request)
    {
        $validated = $request->validate(['text' => 'required|string|max:5000']);

        try {
            return response()->json([
                'data' => $this->builder->interpretRequirements(
                    $request->user->tenant_id,
                    $validated['text'],
                    $request->user->id,
                ),
            ]);
        } catch (AiProviderException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }

    // Requirements -> a proposal built only from real available inventory,
    // every component re-verified, priced by the deterministic engine, and
    // returned as a draft for human approval.
    public function propose(Request $request)
    {
        $validated = $request->validate([
            'requirements' => 'required|array',
            'requirements.destination' => 'nullable|string',
            'requirements.travel_date' => 'nullable|date',
            'requirements.group_size' => 'nullable|integer|min:1',
        ]);

        try {
            return response()->json([
                'data' => $this->builder->proposePackage(
                    $request->user->tenant_id,
                    $validated['requirements'],
                    $request->user->id,
                ),
            ]);
        } catch (ValidationException $e) {
            // Raised when the model named a component that does not exist
            // or lacks availability — surfaced plainly rather than hidden.
            return response()->json([
                'error' => 'The proposed package failed inventory verification.',
                'details' => $e->errors(),
            ], 422);
        } catch (AiProviderException $e) {
            return response()->json(['error' => $e->getMessage()], 502);
        }
    }
}
