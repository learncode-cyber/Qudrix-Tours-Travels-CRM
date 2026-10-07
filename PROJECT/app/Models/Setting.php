<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['tenant_id', 'key', 'value', 'type', 'description'];
    public function tenant() { return $this->belongsTo(Tenant::class); }
}
