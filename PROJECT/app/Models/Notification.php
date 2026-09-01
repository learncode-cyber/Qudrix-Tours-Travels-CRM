<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'type', 'title', 'message', 'data', 'read_at'];
    protected $casts = ['data' => 'json', 'read_at' => 'datetime'];
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function isRead(): bool { return $this->read_at !== null; }
}
