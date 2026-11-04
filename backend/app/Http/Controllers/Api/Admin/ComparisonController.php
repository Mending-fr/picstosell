<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\AiConfig;
use App\Models\Comparison;
use App\Models\Render;
use App\Models\Vehicle;
use App\Services\RenderLauncher;
use App\Support\Media;
use Illuminate\Http\Request;

class ComparisonController
{
    public function index()
    {
        return ['data' => Comparison::latest()->get()->map(fn ($c) => [
            'id' => $c->id, 'name' => $c->name, 'status' => $c->status, 'garage_id' => $c->garage_id,
            'created_at' => $c->created_at->toIso8601String(),
            'ai_config_ids' => $c->ai_config_ids, 'vehicle_ids' => $c->vehicle_ids,
        ])];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'garage_id' => 'required|exists:garages,id',
            'ai_config_ids' => 'required|array|min:1|max:6',
            'ai_config_ids.*' => 'exists:ai_configs,id',
            'vehicle_ids' => 'required|array|min:1|max:30',
            'vehicle_ids.*' => 'integer',
        ]);
        $vehicleIds = Vehicle::where('garage_id', $data['garage_id'])->whereIn('id', $data['vehicle_ids'])->pluck('id')->all();
        abort_if(! $vehicleIds, 422, 'Aucune voiture de ce garage dans la sélection');
        $c = Comparison::create([
            'name' => $data['name'], 'garage_id' => $data['garage_id'],
            'ai_config_ids' => array_values(array_map('intval', $data['ai_config_ids'])),
            'vehicle_ids' => $vehicleIds, 'created_by' => $request->user()->id,
        ]);
        $this->launch($c);

        return response()->json($this->payload($c), 201);
    }

    public function show(Comparison $comparison)
    {
        return $this->payload($comparison);
    }

    public function run(Comparison $comparison)
    {
        $this->launch($comparison);

        return $this->payload($comparison);
    }

    private function launch(Comparison $c): void
    {
        $configs = AiConfig::whereIn('id', $c->ai_config_ids)->get();
        $vehicles = Vehicle::with('photos')->whereIn('id', $c->vehicle_ids)->get();
        foreach ($vehicles as $v) {
            foreach ($v->photos->where('kind', 'exterior') as $photo) {
                foreach ($configs as $config) {
                    $prev = Render::where('comparison_id', $c->id)->where('photo_id', $photo->id)->where('ai_config_id', $config->id)->max('attempt');
                    RenderLauncher::launch($photo, $config, $c->id, ($prev ?? 0) + 1);
                }
            }
        }
        $c->update(['status' => 'running']);
    }

    private function payload(Comparison $c): array
    {
        $configs = AiConfig::whereIn('id', $c->ai_config_ids)->get();
        $vehicles = Vehicle::withTrashed()->with('photos')->whereIn('id', $c->vehicle_ids)->get();
        $renders = Render::with('photo')->where('comparison_id', $c->id)->orderBy('id')->get();
        $latest = [];
        foreach ($renders as $r) {
            $latest[$r->photo_id.'-'.$r->ai_config_id] = $r;
        }

        $grid = [];
        foreach ($vehicles as $v) {
            foreach ($v->photos->where('kind', 'exterior') as $p) {
                $cells = [];
                foreach ($configs as $cfg) {
                    $r = $latest[$p->id.'-'.$cfg->id] ?? null;
                    $cells[$cfg->id] = $r?->toApi();
                }
                $grid[$v->id]['p'.$p->id] = ['label' => $p->displayLabel(), 'source_url' => Media::url($p->thumb_path ?? $p->path), 'renders' => $cells];
            }
        }

        $exteriorCount = $vehicles->sum(fn ($v) => $v->photos->where('kind', 'exterior')->count());
        $summary = $configs->map(function ($cfg) use ($latest, $renders, $vehicles) {
            $mine = collect($latest)->filter(fn ($r) => $r->ai_config_id === $cfg->id);
            $all = $renders->where('ai_config_id', $cfg->id);
            $rated = $mine->whereNotNull('rating');
            $pub = $rated->where('rating', 'publishable')->count();

            return [
                'ai_config_id' => $cfg->id,
                'rated' => $rated->count(),
                'publishable' => $pub,
                'redo' => $rated->where('rating', 'redo')->count(),
                'unusable' => $rated->where('rating', 'unusable')->count(),
                'publishable_pct' => $rated->count() ? (int) round($pub / $rated->count() * 100) : null,
                'cost_per_vehicle_usd' => $vehicles->count() ? number_format((float) $all->sum('cost_usd') / $vehicles->count(), 3, '.', '') : null,
                'avg_duration_ms' => $mine->whereNotNull('duration_ms')->count() ? (int) $mine->whereNotNull('duration_ms')->avg('duration_ms') : null,
            ];
        })->values();

        $pending = collect($latest)->contains(fn ($r) => in_array($r->status, ['pending', 'processing'], true));
        if (! $pending && $c->status === 'running') {
            $c->update(['status' => 'done']);
        }

        return [
            'id' => $c->id,
            'name' => $c->name,
            'status' => $c->status,
            'garage_id' => $c->garage_id,
            'configs' => $configs->map->toApi()->values(),
            'vehicles' => $vehicles->map(fn ($v) => ['id' => $v->id, 'plate' => $v->plate, 'label' => $v->label()])->values(),
            'summary' => $summary,
            'estimate_usd' => number_format($exteriorCount * $configs->sum(fn ($c) => (float) $c->unit_cost_usd), 2, '.', ''),
            'grid' => (object) $grid,
        ];
    }
}
