<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'customer_id', 'type', 'title', 'message', 'data',
        'read_at', 'channel', 'delivery_status', 'delivery_detail',
        'related_entity_type', 'related_entity_id',
    ];
    protected $casts = ['data' => 'json', 'read_at' => 'datetime'];
    public function tenant() { return $this->belongsTo(Tenant::class); }
    public function user() { return $this->belongsTo(User::class); }
    public function customer() { return $this->belongsTo(Customer::class); }
    public function isRead(): bool { return $this->read_at !== null; }
}
