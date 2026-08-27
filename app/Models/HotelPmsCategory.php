<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HotelPmsCategory extends Model
{
    protected $fillable = ['hotel_id', 'name', 'service_names', 'comments'];

    protected function casts(): array
    {
        return ['service_names' => 'array'];
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }
}
