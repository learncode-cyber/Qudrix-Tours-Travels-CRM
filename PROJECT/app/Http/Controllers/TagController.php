<?php

namespace App\Http\Controllers;

use App\Models\Tag;
use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TagController extends Controller
{
    /**
     * Maps the short, stable entity slug used in the API to its model
     * class. Kept centralized so new taggable entities are a one-line
     * addition here rather than scattered string checks.
     */
    protected function resolveTaggable(string $entityType, int $tenantId, int $entityId)
    {
        $map = [
            'customer' => Customer::class,
            'lead' => Lead::class,
        ];

        if (!isset($map[$entityType])) {
            throw ValidationException::withMessages([
                'entity_type' => ["Unsupported entity_type '{$entityType}'. Supported: " . implode(', ', array_keys($map))],
            ]);
        }

        return $map[$entityType]::where('tenant_id', $tenantId)->findOrFail($entityId);
    }

    public function index(Request $request)
    {
        $tags = Tag::where('tenant_id', $request->user->tenant_id)
            ->orderBy('name')
            ->get();

        return response()->json(['data' => $tags]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'color' => 'nullable|string|max:7',
        ]);

        $tag = Tag::firstOrCreate(
            ['tenant_id' => $request->user->tenant_id, 'name' => $validated['name']],
            ['color' => $validated['color'] ?? '#6366f1']
        );

        return response()->json(['data' => $tag], 201);
    }

    public function delete(Request $request, $id)
    {
        $tag = Tag::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $tag->delete();

        return response()->json(['message' => 'Tag deleted successfully']);
    }

    public function attach(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|string',
            'entity_id' => 'required|integer',
            'tag_id' => 'required|integer|exists:tags,id',
        ]);

        $entity = $this->resolveTaggable($validated['entity_type'], $request->user->tenant_id, $validated['entity_id']);
        $tag = Tag::where('tenant_id', $request->user->tenant_id)->findOrFail($validated['tag_id']);

        $entity->tags()->syncWithoutDetaching([$tag->id]);

        return response()->json(['message' => 'Tag attached', 'data' => $entity->tags]);
    }

    public function detach(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|string',
            'entity_id' => 'required|integer',
            'tag_id' => 'required|integer',
        ]);

        $entity = $this->resolveTaggable($validated['entity_type'], $request->user->tenant_id, $validated['entity_id']);
        $entity->tags()->detach($validated['tag_id']);

        return response()->json(['message' => 'Tag detached', 'data' => $entity->tags]);
    }
}
