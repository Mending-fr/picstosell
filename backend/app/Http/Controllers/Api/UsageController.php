<?php

namespace App\Http\Controllers\Api;

use App\Models\Render;

class UsageController extends Controller
{
    public function __invoke()
    {
        $g = $this->garage();
        $start = now()->startOfMonth();
        $renders = Render::whereHas('vehicle', fn ($q) => $q->withTrashed()->where('garage_id', $g->id))->where('created_at', '>=', $start);

        return [
            'month' => $start->format('Y-m'),
            'vehicles' => $g->vehicles()->withTrashed()->where('created_at', '>=', $start)->count(),
            'quota' => $g->vehicle_quota,
            'renders' => (clone $renders)->count(),
            'cost_usd' => number_format((float) (clone $renders)->sum('cost_usd'), 3, '.', ''),
            'extras' => [],
        ];
    }
}
