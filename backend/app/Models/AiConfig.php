<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConfig extends Model
{
    public const PROVIDERS = ['fake', 'openai', 'gemini', 'fal'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'unit_cost_usd' => 'decimal:4'];
    }

    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'provider' => $this->provider,
            'model' => $this->model,
            'method' => $this->method,
            'quality' => $this->quality,
            'prompt' => $this->prompt,
            'unit_cost_usd' => number_format((float) $this->unit_cost_usd, 3, '.', ''),
            'active' => $this->active,
        ];
    }
}
