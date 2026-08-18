<?php

namespace App\Services\Webhook;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class WebhookBatchingService
{
    /**
     * Batch size for webhook delivery
     */
    protected const DEFAULT_BATCH_SIZE = 10;

    /**
     * Max batch age before forced flush (seconds)
     */
    protected const MAX_BATCH_AGE = 300; // 5 minutes

    /**
     * Create a batched delivery queue
     */
    public function createBatch(Webhook $webhook, array $events, int $batchSize = self::DEFAULT_BATCH_SIZE): array
    {
        $batches = [];
        $currentBatch = [];
        $createdAt = now();

        foreach ($events as $event) {
            $currentBatch[] = [
                'webhook_id' => $webhook->id,
                'event_type' => $event['type'],
                'payload' => json_encode($event['payload']),
                'batch_id' => $this->generateBatchId(),
                'batch_size' => count($events),
                'sequence_number' => count($currentBatch) + 1,
                'status' => 'pending',
                'retry_count' => 0,
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];

            if (count($currentBatch) >= $batchSize) {
                $batches[] = $currentBatch;
                $currentBatch = [];
            }
        }

        // Add remaining events
        if (!empty($currentBatch)) {
            $batches[] = $currentBatch;
        }

        return $batches;
    }

    /**
     * Get pending batches for a webhook
     */
    public function getPendingBatches(Webhook $webhook, int $limit = 50): Collection
    {
        return WebhookDelivery::where('webhook_id', $webhook->id)
            ->where('status', 'pending')
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get()
            ->groupBy('batch_id');
    }

    /**
     * Process a single batch
     */
    public function processBatch(string $batchId, WebhookDeliveryService $deliveryService): array
    {
        $deliveries = WebhookDelivery::where('batch_id', $batchId)
            ->where('status', 'pending')
            ->get();

        $results = [
            'batch_id' => $batchId,
            'total' => $deliveries->count(),
            'successful' => 0,
            'failed' => 0,
            'timestamp' => now(),
        ];

        foreach ($deliveries as $delivery) {
            $webhook = $delivery->webhook;
            
            try {
                $success = $deliveryService->deliver($delivery);
                
                if ($success) {
                    $delivery->update(['status' => 'delivered']);
                    $results['successful']++;
                } else {
                    $delivery->increment('retry_count');
                    $results['failed']++;
                }
            } catch (\Exception $e) {
                $delivery->increment('retry_count');
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Get batch statistics
     */
    public function getBatchStatistics(Webhook $webhook): array
    {
        $totalDeliveries = WebhookDelivery::where('webhook_id', $webhook->id)->count();
        $pendingDeliveries = WebhookDelivery::where('webhook_id', $webhook->id)
            ->where('status', 'pending')
            ->count();
        $successfulDeliveries = WebhookDelivery::where('webhook_id', $webhook->id)
            ->where('status', 'delivered')
            ->count();
        $failedDeliveries = WebhookDelivery::where('webhook_id', $webhook->id)
            ->where('status', 'failed')
            ->count();

        $avgBatchSize = DB::table('webhook_deliveries')
            ->where('webhook_id', $webhook->id)
            ->avg('batch_size') ?? 0;

        return [
            'total_deliveries' => $totalDeliveries,
            'pending' => $pendingDeliveries,
            'successful' => $successfulDeliveries,
            'failed' => $failedDeliveries,
            'success_rate' => $totalDeliveries > 0 
                ? round(($successfulDeliveries / $totalDeliveries) * 100, 2) 
                : 0,
            'average_batch_size' => round($avgBatchSize, 2),
        ];
    }

    /**
     * Flush expired batches
     */
    public function flushExpiredBatches(): int
    {
        $cutoffTime = now()->subSeconds(self::MAX_BATCH_AGE);
        
        return WebhookDelivery::where('status', 'pending')
            ->where('created_at', '<', $cutoffTime)
            ->update(['status' => 'forced_sent']);
    }

    /**
     * Generate unique batch ID
     */
    protected function generateBatchId(): string
    {
        return 'batch_' . uniqid() . '_' . time();
    }
}
