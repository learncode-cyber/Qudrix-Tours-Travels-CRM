<?php

namespace App\Services\Webhook;

use App\Models\Webhook;

class WebhookPayloadTransformationService
{
    /**
     * Transform webhook payload based on webhook configuration
     */
    public function transformPayload(Webhook $webhook, array $payload): array
    {
        $transformations = $webhook->payload_transformations ?? [];

        if (empty($transformations)) {
            return $payload;
        }

        $transformed = $payload;

        foreach ($transformations as $transformation) {
            $transformed = $this->applyTransformation($transformed, $transformation);
        }

        return $transformed;
    }

    /**
     * Apply a single transformation
     */
    protected function applyTransformation(array $payload, array $transformation): array
    {
        $type = $transformation['type'] ?? null;

        return match ($type) {
            'field_mapping' => $this->applyFieldMapping($payload, $transformation),
            'field_extraction' => $this->applyFieldExtraction($payload, $transformation),
            'field_rename' => $this->applyFieldRename($payload, $transformation),
            'field_deletion' => $this->applyFieldDeletion($payload, $transformation),
            'field_encryption' => $this->applyFieldEncryption($payload, $transformation),
            'field_formatting' => $this->applyFieldFormatting($payload, $transformation),
            default => $payload,
        };
    }

    /**
     * Apply field mapping transformation
     */
    protected function applyFieldMapping(array $payload, array $transformation): array
    {
        $mappings = $transformation['mappings'] ?? [];

        foreach ($mappings as $source => $destination) {
            $value = $this->getNestedValue($payload, $source);
            
            if ($value !== null) {
                $payload = $this->setNestedValue($payload, $destination, $value);
            }
        }

        return $payload;
    }

    /**
     * Apply field extraction transformation
     */
    protected function applyFieldExtraction(array $payload, array $transformation): array
    {
        $fields = $transformation['fields'] ?? [];
        $extracted = [];

        foreach ($fields as $field) {
            $value = $this->getNestedValue($payload, $field);
            
            if ($value !== null) {
                $extracted[$field] = $value;
            }
        }

        return $extracted;
    }

    /**
     * Apply field rename transformation
     */
    protected function applyFieldRename(array $payload, array $transformation): array
    {
        $renames = $transformation['renames'] ?? [];

        foreach ($renames as $oldName => $newName) {
            $value = $this->getNestedValue($payload, $oldName);
            
            if ($value !== null) {
                $payload = $this->deleteNestedValue($payload, $oldName);
                $payload = $this->setNestedValue($payload, $newName, $value);
            }
        }

        return $payload;
    }

    /**
     * Apply field deletion transformation
     */
    protected function applyFieldDeletion(array $payload, array $transformation): array
    {
        $fields = $transformation['fields'] ?? [];

        foreach ($fields as $field) {
            $payload = $this->deleteNestedValue($payload, $field);
        }

        return $payload;
    }

    /**
     * Apply field encryption transformation
     */
    protected function applyFieldEncryption(array $payload, array $transformation): array
    {
        $fields = $transformation['fields'] ?? [];
        $algorithm = $transformation['algorithm'] ?? 'sha256';

        foreach ($fields as $field) {
            $value = $this->getNestedValue($payload, $field);
            
            if ($value !== null) {
                $encrypted = $this->encryptValue($value, $algorithm);
                $payload = $this->setNestedValue($payload, $field, $encrypted);
            }
        }

        return $payload;
    }

    /**
     * Apply field formatting transformation
     */
    protected function applyFieldFormatting(array $payload, array $transformation): array
    {
        $formats = $transformation['formats'] ?? [];

        foreach ($formats as $field => $format) {
            $value = $this->getNestedValue($payload, $field);
            
            if ($value !== null) {
                $formatted = $this->formatValue($value, $format);
                $payload = $this->setNestedValue($payload, $field, $formatted);
            }
        }

        return $payload;
    }

    /**
     * Encrypt value
     */
    protected function encryptValue(mixed $value, string $algorithm): string
    {
        return hash($algorithm, (string)$value);
    }

    /**
     * Format value
     */
    protected function formatValue(mixed $value, string $format): string
    {
        return match ($format) {
            'uppercase' => strtoupper((string)$value),
            'lowercase' => strtolower((string)$value),
            'trim' => trim((string)$value),
            'json' => json_encode($value),
            'date_iso' => is_numeric($value) 
                ? date('c', (int)$value) 
                : $value,
            default => (string)$value,
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
     * Set nested value in array using dot notation
     */
    protected function setNestedValue(array $data, string $path, mixed $value): array
    {
        $keys = explode('.', $path);
        $current = &$data;

        foreach ($keys as $key) {
            if (!isset($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        $current = $value;

        return $data;
    }

    /**
     * Delete nested value from array using dot notation
     */
    protected function deleteNestedValue(array $data, string $path): array
    {
        $keys = explode('.', $path);
        $key = array_pop($keys);
        $current = &$data;

        foreach ($keys as $k) {
            if (isset($current[$k])) {
                $current = &$current[$k];
            } else {
                return $data;
            }
        }

        unset($current[$key]);

        return $data;
    }

    /**
     * Validate transformation configuration
     */
    public function validateTransformation(array $transformation): array
    {
        $errors = [];
        $type = $transformation['type'] ?? null;

        if (!$type) {
            $errors[] = "'type' is required";
            return $errors;
        }

        switch ($type) {
            case 'field_mapping':
                if (empty($transformation['mappings'])) {
                    $errors[] = "'mappings' is required for field_mapping";
                }
                break;
            case 'field_extraction':
            case 'field_deletion':
            case 'field_encryption':
                if (empty($transformation['fields'])) {
                    $errors[] = "'fields' is required for {$type}";
                }
                break;
            case 'field_rename':
                if (empty($transformation['renames'])) {
                    $errors[] = "'renames' is required for field_rename";
                }
                break;
            case 'field_formatting':
                if (empty($transformation['formats'])) {
                    $errors[] = "'formats' is required for field_formatting";
                }
                break;
        }

        return $errors;
    }

    /**
     * Get available transformation types
     */
    public function getAvailableTransformations(): array
    {
        return [
            'field_mapping' => 'Map fields to new locations',
            'field_extraction' => 'Extract specific fields only',
            'field_rename' => 'Rename fields',
            'field_deletion' => 'Delete fields',
            'field_encryption' => 'Encrypt field values',
            'field_formatting' => 'Format field values',
        ];
    }
}
