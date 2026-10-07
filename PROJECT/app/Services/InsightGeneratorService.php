<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Payment;
use App\Models\DataInsight;

/**
 * PHASE 12: DataInsight had a model and read-only controller since an
 * earlier phase, but nothing anywhere ever created one — the "insights"
 * feature had no generation pipeline at all. Built as deterministic
 * week-over-week comparisons — no AI call, no invented trend.
 */
class InsightGeneratorService
{
    public function generate(int $tenantId): array
    {
        $insights = [];

        $conversionInsight = $this->conversionRateChange($tenantId);
        if ($conversionInsight) {
            $insights[] = DataInsight::create([
                'tenant_id' => $tenantId,
                'insight_type' => 'conversion_rate_change',
                'title' => $conversionInsight['title'],
                'description' => $conversionInsight['description'],
                'data' => $conversionInsight['data'],
                'impact_level' => $conversionInsight['impact_level'],
                'recommended_action' => $conversionInsight['recommended_action'],
                'generated_at' => now(),
            ]);
        }

        $revenueInsight = $this->revenueChange($tenantId);
        if ($revenueInsight) {
            $insights[] = DataInsight::create([
                'tenant_id' => $tenantId,
                'insight_type' => 'revenue_change',
                'title' => $revenueInsight['title'],
                'description' => $revenueInsight['description'],
                'data' => $revenueInsight['data'],
                'impact_level' => $revenueInsight['impact_level'],
                'recommended_action' => $revenueInsight['recommended_action'],
                'generated_at' => now(),
            ]);
        }

        $staleInsight = $this->staleLeadsAlert($tenantId);
        if ($staleInsight) {
            $insights[] = DataInsight::create([
                'tenant_id' => $tenantId,
                'insight_type' => 'stale_leads',
                'title' => $staleInsight['title'],
                'description' => $staleInsight['description'],
                'data' => $staleInsight['data'],
                'impact_level' => $staleInsight['impact_level'],
                'recommended_action' => $staleInsight['recommended_action'],
                'generated_at' => now(),
            ]);
        }

        return $insights;
    }

    protected function conversionRateChange(int $tenantId): ?array
    {
        $thisWeek = $this->weekConversion($tenantId, 0);
        $lastWeek = $this->weekConversion($tenantId, 1);

        if ($thisWeek === null || $lastWeek === null) {
            return null;
        }

        $change = round($thisWeek - $lastWeek, 1);
        if (abs($change) < 10) {
            return null;
        }

        $direction = $change > 0 ? 'improved' : 'declined';

        return [
            'title' => "Conversion rate {$direction} this week",
            'description' => "Lead conversion rate is {$thisWeek}% this week vs {$lastWeek}% last week (" . ($change > 0 ? '+' : '') . "{$change} points).",
            'data' => ['this_week' => $thisWeek, 'last_week' => $lastWeek, 'change' => $change],
            'impact_level' => abs($change) >= 20 ? 'high' : 'medium',
            'recommended_action' => $change < 0 ? 'Review recent lost deals for a common objection or pricing issue.' : null,
        ];
    }

    protected function weekConversion(int $tenantId, int $weeksAgo): ?float
    {
        $start = now()->subWeeks($weeksAgo + 1);
        $end = now()->subWeeks($weeksAgo);

        $closed = Lead::where('tenant_id', $tenantId)
            ->whereIn('status', ['won', 'lost'])
            ->whereBetween('updated_at', [$start, $end])
            ->count();

        if ($closed === 0) {
            return null;
        }

        $won = Lead::where('tenant_id', $tenantId)
            ->where('status', 'won')
            ->whereBetween('updated_at', [$start, $end])
            ->count();

        return round(($won / $closed) * 100, 1);
    }

    protected function revenueChange(int $tenantId): ?array
    {
        $thisWeek = Payment::where('tenant_id', $tenantId)->where('status', 'completed')
            ->whereBetween('created_at', [now()->subWeek(), now()])->sum('amount');
        $lastWeek = Payment::where('tenant_id', $tenantId)->where('status', 'completed')
            ->whereBetween('created_at', [now()->subWeeks(2), now()->subWeek()])->sum('amount');

        if ($lastWeek == 0) {
            return null;
        }

        $changePercent = round((($thisWeek - $lastWeek) / $lastWeek) * 100, 1);
        if (abs($changePercent) < 15) {
            return null;
        }

        $direction = $changePercent > 0 ? 'up' : 'down';

        return [
            'title' => "Revenue {$direction} week-over-week",
            'description' => "Completed payments this week total {$thisWeek}, vs {$lastWeek} last week (" . ($changePercent > 0 ? '+' : '') . "{$changePercent}%).",
            'data' => ['this_week' => (float) $thisWeek, 'last_week' => (float) $lastWeek, 'change_percent' => $changePercent],
            'impact_level' => abs($changePercent) >= 30 ? 'high' : 'medium',
            'recommended_action' => null,
        ];
    }

    protected function staleLeadsAlert(int $tenantId): ?array
    {
        $staleCount = Lead::where('tenant_id', $tenantId)
            ->whereNotIn('status', ['won', 'lost'])
            ->where(function ($q) {
                $q->where('last_contacted_at', '<', now()->subDays(14))
                    ->orWhereNull('last_contacted_at');
            })
            ->count();

        if ($staleCount < 3) {
            return null;
        }

        return [
            'title' => "{$staleCount} leads have had no contact in 14+ days",
            'description' => "There are currently {$staleCount} open leads with no logged contact in the last two weeks.",
            'data' => ['stale_lead_count' => $staleCount],
            'impact_level' => $staleCount >= 10 ? 'high' : 'medium',
            'recommended_action' => 'Review the pipeline risk view and follow up on stale leads.',
        ];
    }
}
