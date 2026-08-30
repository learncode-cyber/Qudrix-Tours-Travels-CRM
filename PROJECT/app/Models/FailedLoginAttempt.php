<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class FailedLoginAttempt extends Model
{
    public $timestamps = false;

    protected $fillable = ['email', 'ip_address', 'user_agent', 'reason', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];

    /**
     * Consecutive recent failures for this email from this IP.
     * Keyed on both so an attacker cannot lock a real user out globally
     * just by hammering their address from elsewhere.
     */
    public static function recentCount(string $email, ?string $ip, int $withinMinutes): int
    {
        return static::where('email', $email)
            ->when($ip, fn ($q) => $q->where('ip_address', $ip))
            ->where('created_at', '>=', now()->subMinutes($withinMinutes))
            ->count();
    }
}
