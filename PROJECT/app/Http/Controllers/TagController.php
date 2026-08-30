<?php
namespace App\Http\Controllers;
use App\Models\Tag;
use Illuminate\Http\Request;

class TagController extends Controller
{
    // Whitelist of entities that can be tagged.
    private const TAGGABLE_TYPES = [
        'lead' => \App\Models\Lead::class,
        'customer' => \App\Models\Customer::class,
    ];

    public function index(Request $request)
    {
        $tags = Tag::where('tenant_id', $request->user->tenant_id)->get();
        return response()->json(['data' => $tags]);
    }
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'color' => 'nullable|string|max:7',
        ]);
        $tag = Tag::create(['tenant_id' => $request->user->tenant_id, ...$validated]);
        return response()->json(['data' => $tag], 201);
    }
    public function destroy(Request $request, $id)
    {
        $tag = Tag::where('tenant_id', $request->user->tenant_id)->findOrFail($id);
        $tag->delete();
        return response()->json(['message' => 'Tag deleted']);
    }
    public function attach(Request $request)
    {
        $validated = $request->validate([
            'taggable_type' => 'required|in:' . implode(',', array_keys(self::TAGGABLE_TYPES)),
            'taggable_id' => 'required|integer',
            'tag_id' => 'required|exists:tags,id',
        ]);
        $modelClass = self::TAGGABLE_TYPES[$validated['taggable_type']];
        $model = $modelClass::where('tenant_id', $request->user->tenant_id)->findOrFail($validated['taggable_id']);
        $model->tags()->syncWithoutDetaching([$validated['tag_id']]);
        return response()->json(['data' => $model->load('tags')]);
    }
    public function detach(Request $request)
    {
        $validated = $request->validate([
            'taggable_type' => 'required|in:' . implode(',', array_keys(self::TAGGABLE_TYPES)),
            'taggable_id' => 'required|integer',
            'tag_id' => 'required|exists:tags,id',
        ]);
        $modelClass = self::TAGGABLE_TYPES[$validated['taggable_type']];
        $model = $modelClass::where('tenant_id', $request->user->tenant_id)->findOrFail($validated['taggable_id']);
        $model->tags()->detach($validated['tag_id']);
        return response()->json(['data' => $model->load('tags')]);
    }
}
