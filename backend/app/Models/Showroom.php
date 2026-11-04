<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Showroom extends Model
{
    protected $guarded = ['id'];

    public function garage(): BelongsTo
    {
        return $this->belongsTo(Garage::class);
    }

    public function shots(): HasMany
    {
        return $this->hasMany(Shot::class)->orderBy('position');
    }
}
