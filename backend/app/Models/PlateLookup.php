<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlateLookup extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'found' => 'boolean'];
    }
}
