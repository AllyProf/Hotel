<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelPmsService extends Model
{
    protected $fillable = [
        'name', 'amount', 'tax_category', 'hsn_code', 'tax_inclusive',
        'visible_on_be', 'amount_editable', 'image', 'comments', 'hotel_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'tax_inclusive' => 'boolean',
            'visible_on_be' => 'boolean',
            'amount_editable' => 'boolean',
        ];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
