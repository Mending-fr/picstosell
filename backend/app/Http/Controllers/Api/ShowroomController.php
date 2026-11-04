<?php

namespace App\Http\Controllers\Api;

use App\Models\Shot;
use App\Services\Imaging;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ShowroomController extends Controller
{
    public function index()
    {
        $this->garage()->ensureShowroom();

        return ['data' => $this->garage()->showrooms()->with('shots')->get()->map(fn ($s) => [
            'id' => $s->id, 'name' => $s->name, 'shots' => $s->shots->map->toApi()->values(),
        ])];
    }

    private function shot(int $id): Shot
    {
        return Shot::whereHas('showroom', fn ($q) => $q->where('garage_id', $this->garage()->id))->findOrFail($id);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'label' => 'sometimes|string|max:60',
            'placement' => 'sometimes|array',
            'placement.height' => 'numeric|min:10|max:90',
            'placement.bottom' => 'numeric|min:0|max:60',
            'placement.offset_x' => 'numeric|min:-50|max:50',
        ]);
        $shot = $this->shot($id);
        if (isset($data['placement'])) {
            $data['placement'] = array_intersect_key(array_merge(Shot::DEFAULT_PLACEMENT, $shot->placement ?? [], $data['placement']), Shot::DEFAULT_PLACEMENT);
        }
        $shot->update($data);

        return $shot->toApi();
    }

    public function image(Request $request, int $id)
    {
        $request->validate(['file' => 'required|file|image|max:25600']);
        $shot = $this->shot($id);
        $img = Imaging::fit(Imaging::load($request->file('file')->get()), 2048);
        $path = "garages/{$this->garage()->id}/showroom/{$shot->key}-".Str::random(6).'.jpg';
        Media::put($path, Imaging::jpeg($img, 90));
        $shot->update(['image_path' => $path]);

        return $shot->toApi();
    }

    public function overlay(int $id)
    {
        $shot = Shot::findOrFail($id);

        return response(Imaging::overlaySvg($shot->key), 200, ['Content-Type' => 'image/svg+xml', 'Cache-Control' => 'public, max-age=86400']);
    }
}
