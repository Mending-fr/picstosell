<?php

namespace App\Http\Controllers\Api;

use App\Models\Render;
use App\Services\RenderLauncher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RenderController extends Controller
{
    private function find(int $id): Render
    {
        $q = Render::with('photo.vehicle');
        if (! request()->user()->isSuperAdmin()) {
            $garageId = app()->bound('garage') ? $this->garage()->id : request()->user()->garage_id;
            $q->whereHas('vehicle', fn ($w) => $w->where('garage_id', $garageId));
        }

        return $q->findOrFail($id);
    }

    public function approve(int $id)
    {
        $r = $this->find($id);
        abort_unless($r->output_path, 422, "Ce rendu n'a pas d'image");
        $r->update(['status' => 'approved', 'rating' => $r->rating ?? 'publishable']);
        $this->refresh($r);

        return $r->toApi();
    }

    public function keepOriginal(int $id)
    {
        $r = $this->find($id);
        $r->update(['status' => 'kept_original']);
        $this->refresh($r);

        return $r->toApi();
    }

    public function retry(int $id)
    {
        $r = $this->find($id);
        if (! $r->rating) {
            $r->update(['rating' => 'redo']);
        }
        if ($r->status !== 'approved') {
            $r->update(['status' => 'failed']);
        }
        $config = $r->aiConfig;
        $new = RenderLauncher::launch($r->photo, $r->comparison_id ? $config : null, $r->comparison_id, $r->attempt + 1);
        $this->refresh($r);

        return $new->fresh('photo')->toApi();
    }

    public function rate(Request $request, int $id)
    {
        $data = $request->validate(['rating' => ['nullable', Rule::in(Render::RATINGS)]]);
        $r = $this->find($id);
        $r->update(['rating' => $data['rating']]);

        return $r->toApi();
    }

    public function approveAll(int $id)
    {
        $v = $this->vehicle($id);
        $v->load('renders');
        foreach ($v->latestRendersByPhoto() as $r) {
            if ($r->status === 'to_review' && $r->output_path) {
                $r->update(['status' => 'approved', 'rating' => $r->rating ?? 'publishable']);
            }
        }
        $v->refreshStatus();

        return $v->fresh()->toApi(true);
    }

    private function refresh(Render $r): void
    {
        if (! $r->comparison_id) {
            $r->vehicle->refreshStatus();
        }
    }
}
