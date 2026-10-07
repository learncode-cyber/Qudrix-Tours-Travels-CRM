<?php

namespace App\Services\Webhook;

use App\Models\Webhook;

class WebhookFilteringService
{
    /**
     * Supported filter operators
     */
    protected const ALLOWED_OPERATORS = [
        'equals' => '==',
        'not_equals' => '!=',
        'contains' => 'contains',
        'not_contains' => 'not_contains',
        'greater_than' => '>',
        'less_than' => '<',
        'in' => 'in',
        'not_in' => 'not_in',
    ];

    /**
     * Apply filters to webhook event
     */
    public function applyFilters(Webhook $webhook, array $eventData): bool
    {
        if (empty($webhook->filters)) {
            return true; // No filters, always deliver
        }

        $filters = $webhook->filters ?? [];
        
        foreach ($filters as $filter) {
            if (!$this->evaluateFilter($filter, $eventData)) {
                return false; // Filter failed, don't deliver
            }
        }

        return true; // All filters passed
    }

    /**
     * Evaluate a single filter
     */
    protected function evaluateFilter(array $filter, array $eventData): bool
    {
        $field = $filter['field'] ?? null;
        $operator = $filter['operator'] ?? 'equals';
        $value = $filter['value'] ?? null;

        if (!$field || !isset(self::ALLOWED_OPERATORS[$operator])) {
            return true; // Invalid filter, skip it
        }

        $eventValue = $this->getNestedValue($eventData, $field);

        return match ($operator) {
            'equals' => $eventValue == $value,
            'not_equals' => $eventValue != $value,
            'contains' => is_string($eventValue) && str_contains($eventValue, (string)$value),
            'not_contains' => is_string($eventValue) && !str_contains($eventValue, (string)$value),
            'greater_than' => is_numeric($eventValue) && $eventValue > $value,
            'less_than' => is_numeric($eventValue) && $eventValue < $value,
            'in' => in_array($eventValue, (array)$value),
            'not_in' => !in_array($eventValue, (array)$value),
            default => true,
        };
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
     * Validate filter configuration
     */
    public function validateFilters(array $filters): array
    {
        $errors = [];

        foreach ($filters as $index => $filter) {
            if (empty($filter['field'])) {
                $errors[] = "Filter {$index}: 'field' is required";
            }

            if (empty($filter['operator']) || !isset(self::ALLOWED_OPERATORS[$filter['operator']])) {
                $errors[] = "Filter {$index}: Invalid operator '{$filter['operator']}'";
            }

            if (!isset($filter['value'])) {
                $errors[] = "Filter {$index}: 'value' is required";
            }
        }

        return $errors;
    }

    /**
     * Get available filter operators
     */
    public function getAvailableOperators(): array
    {
        return array_keys(self::ALLOWED_OPERATORS);
    }

    /**
     * Create filter from request data
     */
    public function createFilter(string $field, string $operator, mixed $value): array
    {
        return [
            'field' => $field,
            'operator' => $operator,
            'value' => $value,
        ];
    }

    /**
     * Build filter query string for display
     */
    public function buildFilterString(array $filter): string
    {
        $field = $filter['field'] ?? 'unknown';
        $operator = $filter['operator'] ?? 'equals';
        $value = $filter['value'] ?? 'null';

        $operatorSymbol = self::ALLOWED_OPERATORS[$operator] ?? $operator;
        
        if (is_array($value)) {
            $value = '[' . implode(', ', $value) . ']';
        } else {
            $value = '"' . $value . '"';
        }

        return "{$field} {$operatorSymbol} {$value}";
    }
}
