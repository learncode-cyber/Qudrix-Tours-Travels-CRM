<?php

namespace App\Http\Controllers;

use App\Models\SeoMetadata;
use App\Models\Package;
use App\Models\Destination;
use App\Models\Lead;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class SeoController extends Controller
{
    public function index(Request $request)
    {
        $query = SeoMetadata::where('tenant_id', $request->user->tenant_id);
        if ($request->entity_type) $query->where('entity_type', $request->entity_type);
        return response()->json(['data' => $query->get()]);
    }

    public function upsert(Request $request)
    {
        $validated = $request->validate([
            'entity_type' => 'required|string',
            'entity_id' => 'nullable|integer',
            'slug' => 'nullable|string',
            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
            'og_title' => 'nullable|string',
            'og_description' => 'nullable|string',
            'og_image_url' => 'nullable|url',
            'canonical_url' => 'nullable|url',
            'schema_markup' => 'nullable|array',
            'robots_directive' => 'nullable|string',
        ]);

        $meta = SeoMetadata::updateOrCreate(
            [
                'tenant_id' => $request->user->tenant_id,
                'entity_type' => $validated['entity_type'],
                'entity_id' => $validated['entity_id'] ?? null,
                'slug' => $validated['slug'] ?? null,
            ],
            $validated
        );

        return response()->json(['data' => $meta]);
    }

    public function forEntity(Request $request, string $entityType, $entityId)
    {
        $tenantId = $request->tenant_id ?? $request->user?->tenant_id;

        $meta = SeoMetadata::where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where(function ($q) use ($entityId) {
                $q->where('entity_id', $entityId)->orWhere('slug', $entityId);
            })
            ->first();

        if ($meta) {
            return response()->json(['data' => $meta]);
        }

        if ($entityType === 'package' && is_numeric($entityId)) {
            $package = Package::where('tenant_id', $tenantId)->find($entityId);
            if ($package) {
                return response()->json(['data' => [
                    'meta_title' => $package->name,
                    'meta_description' => str($package->description ?? '')->limit(160)->toString(),
                    'og_title' => $package->name,
                    'robots_directive' => 'index,follow',
                    'is_fallback' => true,
                ]]);
            }
        }

        return response()->json(['data' => null, 'message' => 'No metadata configured and no matching entity found']);
    }

    public function sitemap(Request $request)
    {
        $tenantId = $request->tenant_id ?? $request->user?->tenant_id;
        $baseUrl = $request->input('base_url', config('app.url'));

        $urls = [];

        Package::where('tenant_id', $tenantId)->where('is_active', true)->where('status', 'active')
            ->get(['id', 'updated_at'])
            ->each(function ($p) use (&$urls, $baseUrl) {
                $urls[] = ['loc' => "{$baseUrl}/packages/{$p->id}", 'lastmod' => $p->updated_at->toDateString()];
            });

        if (class_exists(Destination::class)) {
            Destination::where('tenant_id', $tenantId)->where('is_active', true)
                ->get(['id', 'updated_at'])
                ->each(function ($d) use (&$urls, $baseUrl) {
                    $urls[] = ['loc' => "{$baseUrl}/destinations/{$d->id}", 'lastmod' => $d->updated_at->toDateString()];
                });
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($urls as $url) {
            $xml .= "  <url><loc>{$url['loc']}</loc><lastmod>{$url['lastmod']}</lastmod></url>\n";
        }
        $xml .= '</urlset>';

        return Response::make($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robotsTxt(Request $request)
    {
        $baseUrl = $request->input('base_url', config('app.url'));
        $content = "User-agent: *\nAllow: /\nSitemap: {$baseUrl}/sitemap.xml";
        return Response::make($content, 200, ['Content-Type' => 'text/plain']);
    }

    public function campaignAttribution(Request $request)
    {
        $tenantId = $request->user->tenant_id;

        $data = Lead::where('tenant_id', $tenantId)
            ->whereNotNull('utm_source')
            ->selectRaw('utm_source, utm_medium, utm_campaign, count(*) as leads, sum(case when status = "won" then 1 else 0 end) as conversions')
            ->groupBy('utm_source', 'utm_medium', 'utm_campaign')
            ->get()
            ->map(function ($row) {
                $row->conversion_rate = $row->leads > 0 ? round(($row->conversions / $row->leads) * 100, 1) : null;
                return $row;
            });

        return response()->json(['data' => $data]);
    }
}
