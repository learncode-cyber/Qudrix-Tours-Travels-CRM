<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Model implements AuthenticatableContract, JWTSubject
{
    use Authenticatable, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'phone', 'password',
        'avatar_url', 'is_active', 'mfa_enabled', 'mfa_secret', 'status'
    ];

    protected $hidden = [
        'password', 'mfa_secret', 'remember_token'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'mfa_enabled' => 'boolean',
        'email_verified_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    public function hasRole($roleName): bool
    {
        return $this->roles()
            ->where('name', $roleName)
            ->exists();
    }

    public function hasAnyRole(array $roles): bool
    {
        return $this->roles()
            ->whereIn('name', $roles)
            ->exists();
    }

    public function hasPermission($permission): bool
    {
        return $this->roles()
            ->get()
            ->filter(function($role) use ($permission) {
                $permissions = json_decode($role->permissions, true) ?? [];
                return in_array('*', $permissions) || in_array($permission, $permissions);
            })
            ->count() > 0;
    }

    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [
            'tenant_id' => $this->tenant_id,
            'email' => $this->email,
        ];
    }

    public function isActive(): bool
    {
        return $this->is_active === true && $this->status === 'active';
    }
}
