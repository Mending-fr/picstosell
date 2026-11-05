<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Backdrop;
use App\Services\Imaging;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Décors Mending : ajoutés ici, proposés à tous les garages. */
class BackdropController
{
    public function index()
    {
        return ['data' => Backdrop::orderByDesc('is_default')->orderBy('name')->get()->map->toApi()->values()];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:80',
            'file' => 'required|file|image|max:25600',
        ]);
        $img = Imaging::fit(Imaging::load($data['file']->get()), 2048);
        $path = 'backdrops/'.Str::uuid().'.jpg';
        Media::put($path, Imaging::jpeg($img, 90));
        $b = Backdrop::create(['name' => $data['name'], 'image_path' => $path, 'is_default' => ! Backdrop::exists()]);

        return response()->json($b->toApi(), 201);
    }

    public function update(Request $request, Backdrop $backdrop)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:80',
            'is_default' => 'sometimes|boolean',
        ]);
        DB::transaction(function () use ($data, $backdrop) {
            if (! empty($data['is_default'])) {
                Backdrop::where('id', '!=', $backdrop->id)->update(['is_default' => false]);
            }
            $backdrop->update($data);
        });

        return $backdrop->fresh()->toApi();
    }

    public function destroy(Backdrop $backdrop)
    {
        $wasDefault = $backdrop->is_default;
        Media::storage()->delete($backdrop->image_path);
        $backdrop->delete();
        if ($wasDefault) {
            Backdrop::orderBy('id')->first()?->update(['is_default' => true]);
        }

        return response()->noContent();
    }
}
