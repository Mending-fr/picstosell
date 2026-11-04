<?php

namespace App\Http\Controllers\Api;

use App\Models\Photo;
use App\Models\Shot;
use App\Models\Vehicle;
use App\Services\Imaging;
use App\Services\RenderLauncher;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PhotoController extends Controller
{
    /** URLs signées pour envoyer les photos directement au bucket. */
    public function uploads(Request $request, int $id)
    {
        $v = $this->vehicle($id);
        $data = $request->validate([
            'files' => 'required|array|min:1|max:40',
            'files.*.shot_key' => ['nullable', Rule::in(Shot::KEYS)],
            'files.*.kind' => ['required', Rule::in(Photo::KINDS)],
            'files.*.content_type' => ['required', Rule::in(['image/jpeg', 'image/png', 'image/heic', 'image/webp'])],
        ]);
        if (! Media::supportsSignedUploads()) {
            return response()->json(['message' => 'Envoi direct indisponible, utilisez le multipart'], 501);
        }
        $uploads = [];
        foreach ($data['files'] as $f) {
            $path = "garages/{$v->garage_id}/vehicles/{$v->id}/incoming/".Str::uuid().'.'.($f['content_type'] === 'image/png' ? 'png' : 'jpg');
            $uploads[] = ['path' => $path] + Media::uploadUrl($path, $f['content_type']);
        }

        return ['uploads' => $uploads];
    }

    public function store(Request $request, int $id)
    {
        $v = $this->vehicle($id);
        $data = $request->validate([
            'path' => 'required_without:file|string|max:500',
            'file' => 'required_without:path|file|image|max:25600',
            'shot_key' => ['nullable', Rule::in(Shot::KEYS)],
            'kind' => ['required', Rule::in(Photo::KINDS)],
        ]);
        if (isset($data['path']) && ! str_starts_with($data['path'], "garages/{$v->garage_id}/vehicles/{$v->id}/incoming/")) {
            abort(422, 'Chemin invalide');
        }
        $bytes = isset($data['file']) ? $data['file']->get() : Media::get($data['path']);
        [$main, $thumb] = Imaging::prepareUpload($bytes);
        $base = "garages/{$v->garage_id}/vehicles/{$v->id}/photos/".Str::uuid();
        Media::put("$base.jpg", $main);
        Media::put("{$base}_thumb.jpg", $thumb);
        if (isset($data['path'])) {
            Media::storage()->delete($data['path']);
        }

        $kind = $data['kind'];
        $shotKey = $kind === 'exterior' ? ($data['shot_key'] ?? null) : null;
        if ($kind === 'exterior' && $shotKey) {
            // Une nouvelle photo pour un angle remplace l'ancienne.
            $v->photos()->where('kind', 'exterior')->where('shot_key', $shotKey)->get()->each->delete();
        }
        $photo = $v->photos()->create([
            'shot_key' => $shotKey,
            'kind' => $kind,
            'path' => "$base.jpg",
            'thumb_path' => "{$base}_thumb.jpg",
            'position' => $kind === 'exterior' ? array_search($shotKey, array_column(Shot::DEFAULTS, 0)) ?: 0 : 100 + $v->photos()->count(),
        ]);
        $v->refreshStatus();

        return response()->json($photo->toApi(), 201);
    }

    public function destroy(int $id)
    {
        $photo = Photo::whereHas('vehicle', fn ($q) => $q->where('garage_id', $this->garage()->id))->findOrFail($id);
        $vehicle = $photo->vehicle;
        $photo->delete();
        $vehicle->refreshStatus();

        return response()->noContent();
    }

    /** Lance le traitement de chaque photo extérieure qui n'a pas encore de rendu actif. */
    public function renders(int $id)
    {
        $v = $this->vehicle($id);
        $v->load(['photos', 'renders']);
        $latest = $v->latestRendersByPhoto();
        $launched = [];
        foreach ($v->photos->where('kind', 'exterior') as $photo) {
            $r = $latest[$photo->id] ?? null;
            if (! $r || $r->status === 'failed') {
                $launched[] = RenderLauncher::launch($photo, attempt: $r ? $r->attempt + 1 : 1);
            }
        }
        $v->refreshStatus();

        return ['data' => collect($launched)->map(fn ($r) => $r->fresh('photo')->toApi())->values()];
    }
}
