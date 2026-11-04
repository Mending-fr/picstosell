<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Garage;
use App\Models\Render;
use App\Models\Vehicle;

class OverviewController
{
    public function __invoke()
    {
        $start = now()->startOfMonth();
        $garages = Garage::orderBy('name')->get();
        $rows = $garages->map(function (Garage $g) use ($start) {
            $vehicles = Vehicle::withTrashed()->where('garage_id', $g->id)->where('created_at', '>=', $start)->count();
            $renders = Render::whereNull('comparison_id')->where('created_at', '>=', $start)
                ->whereHas('vehicle', fn ($q) => $q->withTrashed()->where('garage_id', $g->id));
            $cost = (float) (clone $renders)->sum('cost_usd');
            $total = (clone $renders)->count();
            $redo = (clone $renders)->where('attempt', '>', 1)->count();

            return $g->summary() + [
                'vehicles_month' => $vehicles,
                'quota' => $g->vehicle_quota,
                'cost_usd' => number_format($cost, 3, '.', ''),
                'cost_per_vehicle_usd' => $vehicles ? number_format($cost / $vehicles, 3, '.', '') : null,
                'redo_rate' => $total ? round($redo / $total, 3) : null,
                'ai_config_id' => $g->ai_config_id,
                'show_usage' => (bool) $g->setting('show_usage'),
            ];
        });
        $vehicles = $rows->sum('vehicles_month');
        $cost = $rows->sum(fn ($r) => (float) $r['cost_usd']);
        $all = Render::whereNull('comparison_id')->where('created_at', '>=', $start);
        $total = (clone $all)->count();

        $errors = Render::with('photo.vehicle')->where('status', 'failed')->latest('updated_at')->limit(20)->get()->map(fn ($r) => [
            'at' => $r->updated_at->toIso8601String(),
            'level' => 'error',
            'message' => $r->error,
            'ref' => trim(($r->photo?->vehicle?->plate ?? '').' · '.($r->photo?->shot_key ?? ''), ' ·'),
        ]);

        return [
            'garages_active' => $garages->whereIn('status', ['pilot', 'active'])->count(),
            'vehicles_month' => $vehicles,
            'cost_usd_month' => number_format($cost, 3, '.', ''),
            'cost_per_vehicle_usd' => $vehicles ? number_format($cost / $vehicles, 3, '.', '') : null,
            'redo_rate' => $total ? round((clone $all)->where('attempt', '>', 1)->count() / $total, 3) : null,
            'garages' => $rows->values(),
            'errors' => $errors,
        ];
    }
}
