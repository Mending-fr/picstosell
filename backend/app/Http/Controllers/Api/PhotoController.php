<?php

namespace App\Http\Controllers\Api;

use App\Models\Photo;
use App\Models\Shot;
use App\Models\Vehicle;
use App\Services\Imaging;
use App\Services\PhotoClassifier;
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
            'files.*.kind' => ['required', Rule::in([...Photo::KINDS, 'auto'])],
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
            // « auto » : prise de vue libre, l'IA dit ce que montre la photo.
            'kind' => ['required', Rule::in([...Photo::KINDS, 'auto'])],
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
        $label = null;
        $classified = null;
        if ($kind === 'auto') {
            $found = app(PhotoClassifier::class)->classify($thumb);
            // Sans réponse de l'IA, on suppose une vue extérieure : le garage corrige sur la fiche si besoin.
            $kind = $found['kind'] ?? 'exterior';
            $shotKey = $found['view'] ?? null;
            $label = $found['label'] ?? null;
            $classified = $found ? 'ai' : null;
        } elseif ($kind === 'exterior' && $shotKey) {
            // Ancienne prise de vue guidée : une nouvelle photo pour un angle remplace l'ancienne.
            $v->photos()->where('kind', 'exterior')->where('shot_key', $shotKey)->get()->each->delete();
        }
        $photo = $v->photos()->create([
            'shot_key' => $shotKey,
            'kind' => $kind,
            'label' => $label,
            'classified' => $classified,
            'path' => "$base.jpg",
            'thumb_path' => "{$base}_thumb.jpg",
            'position' => Photo::rank($kind, $shotKey, $label),
        ]);
        $v->refreshStatus();

        return response()->json($photo->toApi(), 201);
    }

    /** Correction à la main : type, angle ou libellé. */
    public function update(Request $request, int $id)
    {
        $photo = Photo::whereHas('vehicle', fn ($q) => $q->where('garage_id', $this->garage()->id))->findOrFail($id);
        $data = $request->validate([
            'kind' => ['required', Rule::in(Photo::KINDS)],
            'shot_key' => ['nullable', Rule::in(Shot::KEYS)],
            'label' => ['nullable', 'string', 'max:60'],
        ]);
        $wasExterior = $photo->kind === 'exterior';
        $photo->update([
            'kind' => $data['kind'],
            'shot_key' => $data['kind'] === 'exterior' ? ($data['shot_key'] ?? null) : null,
            'label' => $data['kind'] === 'exterior' ? null : ($data['label'] ?? null),
            'classified' => 'user',
            'position' => Photo::rank($data['kind'], $data['shot_key'] ?? null, $data['label'] ?? null),
        ]);
        if ($wasExterior && $data['kind'] !== 'exterior') {
            // Une photo d'intérieur n'a rien à faire dans le showroom : on retire ses rendus.
            $photo->renders()->whereNull('comparison_id')->delete();
        }
        $vehicle = $photo->vehicle;
        $vehicle->refreshStatus();

        return $photo->fresh()->toApi();
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
