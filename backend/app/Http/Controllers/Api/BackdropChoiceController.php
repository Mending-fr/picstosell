<?php

namespace App\Http\Controllers\Api;

use App\Models\Backdrop;
use App\Models\Photo;
use App\Models\Render;
use App\Services\RenderLauncher;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/** Choix du décor par le garage, et essai d'un décor sur une photo déjà envoyée. */
class BackdropChoiceController extends Controller
{
    public function index()
    {
        $g = $this->garage();
        $g->ensureShowroom();
        $shot = $g->showrooms()->first()?->shots()->first();

        return [
            'selected' => $g->backdrop()['key'],
            'chosen' => $g->settings['backdrop'] ?? null,
            'own' => ['shot_id' => $shot?->id, 'image_url' => Media::url($shot?->image_path)],
            'library' => Backdrop::orderByDesc('is_default')->orderBy('name')->get()->map->toApi()->values(),
        ];
    }

    public function choose(Request $request)
    {
        $data = $request->validate(['backdrop' => 'required|string|max:20']);
        $this->check($data['backdrop']);
        $g = $this->garage();
        $g->settings = array_merge($g->settings ?? [], ['backdrop' => $data['backdrop']]);
        $g->save();

        return $this->index();
    }

    /** Photos extérieures récentes du garage, pour choisir celle de l'essai. */
    public function photos()
    {
        $photos = Photo::with('vehicle')
            ->whereHas('vehicle', fn ($q) => $q->where('garage_id', $this->garage()->id))
            ->where('kind', 'exterior')->latest('id')->limit(24)->get();

        return ['data' => $photos->map(fn (Photo $p) => [
            'id' => $p->id,
            'thumb_url' => Media::url($p->thumb_path ?? $p->path),
            'label' => $p->displayLabel(),
            'vehicle' => trim($p->vehicle->label().' '.$p->vehicle->plate),
        ])->values()];
    }

    public function test(Request $request)
    {
        $data = $request->validate(['photo_id' => 'required|integer', 'backdrop' => 'required|string|max:20']);
        $this->check($data['backdrop']);
        $photo = Photo::whereHas('vehicle', fn ($q) => $q->where('garage_id', $this->garage()->id))->findOrFail($data['photo_id']);

        return response()->json(RenderLauncher::launch($photo, testBackdrop: $data['backdrop'])->fresh()->toApi(), 201);
    }

    public function show(int $id)
    {
        $r = Render::where('is_test', true)->whereHas('vehicle', fn ($q) => $q->where('garage_id', $this->garage()->id))->findOrFail($id);

        return $r->toApi() + ['source_url' => Media::url($r->photo?->path)];
    }

    private function check(string $choice): void
    {
        $ok = $choice === 'own' || (str_starts_with($choice, 'lib:') && Backdrop::whereKey((int) substr($choice, 4))->exists());
        if (! $ok) {
            throw ValidationException::withMessages(['backdrop' => "Ce décor n'existe pas."]);
        }
    }
}
