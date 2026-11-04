<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Comparison extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ai_config_ids' => 'array', 'vehicle_ids' => 'array'];
    }

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function renders(): HasMany
    {
        return $this->hasMany(Render::class);
    }
}
