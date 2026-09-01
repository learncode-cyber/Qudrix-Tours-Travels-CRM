<?php

namespace App\Services\Webhook;

use App\Models\Webhook;
use Carbon\Carbon;

class WebhookConditionalDeliveryService
{
    /**
     * Check if webhook should be delivered based on conditions
     */
    public function shouldDeliver(Webhook $webhook, array $eventData): bool
    {
        // Check if webhook is active
        if (!$webhook->is_active) {
            return false;
        }

        // Check rate limiting
        if (!$this->checkRateLimit($webhook)) {
            return false;
        }

        // Check scheduled delivery window
        if (!$this->isInDeliveryWindow($webhook)) {
            return false;
        }

        // Check event type subscription
        if (!in_array($eventData['event_type'], $webhook->events ?? [])) {
            return false;
        }

        // Check custom conditions
        if (!$this->evaluateConditions($webhook, $eventData)) {
            return false;
        }

        return true;
    }

    /**
     * Check webhook rate limiting
     */
    protected function checkRateLimit(Webhook $webhook): bool
    {
        $rateLimit = $webhook->rate_limit ?? null;
        
        if (!$rateLimit) {
            return true;
        }

        $deliveryCount = $webhook->deliveries()
            ->where('created_at', '>=', now()->subMinutes($rateLimit['window'] ?? 60))
            ->count();

        return $deliveryCount < ($rateLimit['max_per_window'] ?? 100);
    }

    /**
     * Check if current time is within delivery window
     */
    protected function isInDeliveryWindow(Webhook $webhook): bool
    {
        $window = $webhook->delivery_window ?? null;

        if (!$window) {
            return true; // No window restriction
        }

        $now = now();
        $currentTime = $now->format('H:i');
        $dayOfWeek = $now->dayOfWeek; // 0 = Sunday, 6 = Saturday

        // Check day restriction
        if (isset($window['days'])) {
            if (!in_array($dayOfWeek, $window['days'])) {
                return false;
            }
        }

        // Check time restriction
        if (isset($window['start_time']) && isset($window['end_time'])) {
            $startTime = $window['start_time'];
            $endTime = $window['end_time'];

            // Handle window spanning midnight
            if ($startTime > $endTime) {
                return $currentTime >= $startTime || $currentTime <= $endTime;
            } else {
                return $currentTime >= $startTime && $currentTime <= $endTime;
            }
        }

        return true;
    }

    /**
     * Evaluate custom delivery conditions
     */
    protected function evaluateConditions(Webhook $webhook, array $eventData): bool
    {
        $conditions = $webhook->delivery_conditions ?? [];

        if (empty($conditions)) {
            return true;
        }

        // AND logic - all conditions must pass
        foreach ($conditions as $condition) {
            if (!$this->evaluateCondition($condition, $eventData)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Evaluate a single condition
     */
    protected function evaluateCondition(array $condition, array $eventData): bool
    {
        $type = $condition['type'] ?? null;

        return match ($type) {
            'field_match' => $this->evaluateFieldMatch($condition, $eventData),
            'field_range' => $this->evaluateFieldRange($condition, $eventData),
            'value_exists' => $this->evaluateValueExists($condition, $eventData),
            default => true,
        };
    }

    /**
     * Evaluate field match condition
     */
    protected function evaluateFieldMatch(array $condition, array $eventData): bool
    {
        $field = $condition['field'] ?? null;
        $expectedValue = $condition['value'] ?? null;

        $actualValue = $this->getNestedValue($eventData, $field);

        return $actualValue == $expectedValue;
    }

    /**
     * Evaluate field range condition
     */
    protected function evaluateFieldRange(array $condition, array $eventData): bool
    {
        $field = $condition['field'] ?? null;
        $min = $condition['min'] ?? null;
        $max = $condition['max'] ?? null;

        $value = $this->getNestedValue($eventData, $field);

        if ($min !== null && $value < $min) {
            return false;
        }

        if ($max !== null && $value > $max) {
            return false;
        }

        return true;
    }

    /**
     * Evaluate value exists condition
     */
    protected function evaluateValueExists(array $condition, array $eventData): bool
    {
        $field = $condition['field'] ?? null;
        
        $value = $this->getNestedValue($eventData, $field);

        return $value !== null;
    }

    /**
     * Get nested value from array using dot notation
     */
    protected function getNestedValue(array $data, string $path): mixed
    {
        $keys = explode('.', $path);
        $value = $data;

        foreach ($keys as $key) {
            if (is_array($value) && isset($value[$key])) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    /**
     * Get delivery statistics
     */
    public function getDeliveryStats(Webhook $webhook): array
    {
        $totalDeliveries = $webhook->deliveries()->count();
        $successfulDeliveries = $webhook->deliveries()
            ->where('status', 'delivered')
            ->count();
        $failedDeliveries = $webhook->deliveries()
            ->where('status', 'failed')
            ->count();
        $skippedDeliveries = $webhook->deliveries()
            ->where('status', 'skipped')
            ->count();

        return [
            'total' => $totalDeliveries,
            'successful' => $successfulDeliveries,
            'failed' => $failedDeliveries,
            'skipped' => $skippedDeliveries,
            'success_rate' => $totalDeliveries > 0 
                ? round(($successfulDeliveries / $totalDeliveries) * 100, 2) 
                : 0,
            'skip_rate' => $totalDeliveries > 0 
                ? round(($skippedDeliveries / $totalDeliveries) * 100, 2) 
                : 0,
        ];
    }
}
