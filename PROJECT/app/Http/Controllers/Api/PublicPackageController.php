<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

// Public package listing for a tenant's own website.
//
// REWRITTEN: the previous version queried columns that do not exist in this
// schema (`price`, `duration_days`, `capacity`) and applied NO tenant filter
// at all — so any tenant's API key returned every tenant's packages. The API
// key carries the tenant, and every query here is scoped to it.
class PublicPackageController extends Controller
{
    /**
     * The tenant is always taken from the authenticating API key
     * (set by ApiKeyMiddleware), never from client input.
     */
    private function tenantId(Request $request): ?int
    {
        return $request->apiKey->tenant_id ?? null;
    }

    public function index(Request $request)
    {
        $tenantId = $this->tenantId($request);
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'API key is not bound to a tenant.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $page = max(1, (int) $request->get('page', 1));
        $limit = min(100, max(1, (int) $request->get('limit', 10)));
        $search = trim((string) $request->get('search', ''));
        $type = trim((string) $request->get('type', ''));

        // Whitelisted against columns that actually exist on `packages`.
        $allowedSorts = ['created_at', 'base_price', 'days', 'name'];
        $sort = in_array($request->get('sort'), $allowedSorts, true) ? $request->get('sort') : 'created_at';
        $order = strtolower((string) $request->get('order')) === 'asc' ? 'asc' : 'desc';

        $query = Package::where('tenant_id', $tenantId)->where('is_active', true);

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('destination', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        $total = (clone $query)->count();

        $packages = $query->orderBy($sort, $order)
            ->forPage($page, $limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $packages->map(fn (Package $p) => $this->formatPackage($p))->all(),
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'total_pages' => (int) ceil($total / $limit),
            ],
        ]);
    }

    public function show(Request $request, $id)
    {
        $tenantId = $this->tenantId($request);
        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'API key is not bound to a tenant.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $package = Package::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->find($id);

        if (!$package) {
            return response()->json([
                'success' => false,
                'message' => 'Package not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatPackage($package, true),
        ]);
    }

    private function formatPackage(Package $package, bool $detailed = false): array
    {
        $data = [
            'id' => $package->id,
            'name' => $package->name,
            'code' => $package->code,
            'type' => $package->type,
            'destination' => $package->destination,
            'days' => $package->days,
            'nights' => $package->nights,
            'base_price' => (float) $package->base_price,
        ];

        if ($detailed) {
            $data['description'] = $package->description;
            $data['inclusions'] = $package->inclusions ?? [];
            $data['exclusions'] = $package->exclusions ?? [];
        }

        return $data;
    }
}
