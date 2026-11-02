<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HotelVendor extends Model
{
    protected $fillable = [
        'hotel_id',
        'name',
        'contact_person',
        'gst_num',
        'phone',
        'email',
        'state',
        'address',
        'account_name',
        'account_no',
        'bank_name',
        'ifsc_code',
    ];

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(HotelPurchaseOrder::class);
    }
}
