<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Casts\AsJson;

class Role extends Model
{
    protected $fillable = ['tenant_id', 'name', 'display_name', 'description', 'is_system', 'permissions'];
    protected $casts = ['permissions' => AsJson::class, 'is_system' => 'boolean'];

    public function tenant(): BelongsTo { return $this->belongsTo(Tenant::class); }
    public function users(): BelongsToMany { return $this->belongsToMany(User::class, 'role_user')->withPivot('tenant_id')->withTimestamps(); }
    public function hasPermission($permission): bool { $perms = $this->permissions ?? []; return in_array('*', $perms) || in_array($permission, $perms); }
}
