<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelStaffDocument extends Model
{
    protected $fillable = [
        'hotel_id',
        'user_id',
        'document_key',
        'label',
        'file_path',
        'original_name',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function url(): string
    {
        return asset('storage/'.$this->file_path);
    }
}
