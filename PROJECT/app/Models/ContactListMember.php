<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactListMember extends Model
{
    protected $fillable = ['contact_list_id', 'customer_id', 'lead_id'];

    public function list(): BelongsTo { return $this->belongsTo(ContactList::class, 'contact_list_id'); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
}
