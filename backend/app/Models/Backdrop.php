<?php

namespace App\Models;

use App\Support\Media;
use Illuminate\Database\Eloquent\Model;

/** Décor de showroom fourni par Mending, au choix de chaque garage. */
class Backdrop extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    public static function default(): ?self
    {
        return self::where('is_default', true)->first() ?? self::orderBy('id')->first();
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image_url' => Media::url($this->image_path),
            'is_default' => $this->is_default,
        ];
    }
}
