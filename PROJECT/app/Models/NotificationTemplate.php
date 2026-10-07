<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationTemplate extends Model
{
    protected $fillable = ['tenant_id', 'event_key', 'channel', 'locale', 'subject', 'body', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Replaces {{key}} tokens with values from $data. Unmatched tokens
     * are left as-is rather than silently blanked, so a missing variable
     * is visible/debuggable instead of producing a message with a silent
     * gap in it.
     */
    public function render(array $data): array
    {
        $replace = function (?string $text) use ($data) {
            if ($text === null) {
                return null;
            }
            return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', function ($m) use ($data) {
                return array_key_exists($m[1], $data) ? (string) $data[$m[1]] : $m[0];
            }, $text);
        };

        return [
            'subject' => $replace($this->subject),
            'body' => $replace($this->body),
        ];
    }
}
