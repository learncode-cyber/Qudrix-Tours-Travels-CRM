<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HajjUmrahGroup extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'tenant_id', 'package_type', 'package_id', 'name', 'departure_date',
        'return_date', 'group_leader_id', 'agent_id', 'capacity', 'status',
    ];

    protected $casts = [
        'departure_date' => 'date',
        'return_date' => 'date',
    ];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function groupLeader(): BelongsTo { return $this->belongsTo(User::class, 'group_leader_id'); }
    public function agent(): BelongsTo { return $this->belongsTo(Vendor::class, 'agent_id'); }
    public function pilgrims(): HasMany { return $this->hasMany(Pilgrim::class); }

    public function package(): HajjPackage|UmrahPackage|null
    {
        return $this->package_type === 'hajj'
            ? HajjPackage::find($this->package_id)
            : UmrahPackage::find($this->package_id);
    }

    public function seatsAvailable(): int
    {
        return max(0, $this->capacity - $this->pilgrims()->count());
    }
}
