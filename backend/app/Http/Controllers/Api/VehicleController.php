<?php

namespace App\Http\Controllers\Api;

use App\Models\Vehicle;
use App\Services\DescriptionWriter;
use App\Services\Plate\PlateLookupService;
use App\Support\Media;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use ZipArchive;

class VehicleController extends Controller
{
    public function index(Request $request)
    {
        $q = $this->garage()->vehicles()->withCount(['leads', 'photos', 'photos as exterior_photos_count' => fn ($q) => $q->where('kind', 'exterior')])->with(['photos', 'renders.photo'])->latest();
        if ($s = $request->query('status')) {
            $s === 'to_photograph' ? $q->whereIn('status', ['to_photograph', 'processing']) : $q->where('status', $s);
        }
        if ($term = trim((string) $request->query('q'))) {
            $plate = preg_replace('/[^A-Z0-9]/', '', strtoupper($term));
            $q->where(function ($w) use ($term, $plate) {
                $like = '%'.mb_strtolower($term).'%';
                $w->whereRaw('lower(make) like ?', [$like])->orWhereRaw('lower(model) like ?', [$like]);
                if ($plate !== '') {
                    $w->orWhereRaw("replace(plate, '-', '') like ?", ["%{$plate}%"]);
                }
            });
        }

        return ['data' => $q->limit(500)->get()->map->toApi()->values()];
    }

    public function lookup(string $plate, PlateLookupService $service)
    {
        try {
            $r = $service->lookup($plate, $this->garage());
        } catch (\Throwable $e) {
            report($e);

            // Le service est indisponible : on laisse saisir à la main plutôt que de bloquer.
            return ['found' => false, 'plate' => Vehicle::normalizePlate($plate), 'data' => (object) [], 'message' => 'Recherche indisponible, complétez les informations à la main.'];
        }

        return ['found' => $r['found'], 'plate' => Vehicle::normalizePlate($plate), 'data' => (object) $r['data'], 'demo' => $r['demo'] ?? false];
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, creating: true);
        $data['plate'] = Vehicle::normalizePlate($data['plate']);
        $vehicle = $this->garage()->vehicles()->create($data + ['status' => 'to_photograph']);

        return response()->json($vehicle->fresh()->toApi(true), 201);
    }

    public function show(int $id)
    {
        return $this->vehicle($id)->toApi(true);
    }

    public function update(Request $request, int $id)
    {
        $v = $this->vehicle($id);
        $data = $this->validated($request, creating: false);
        if (isset($data['plate'])) {
            $data['plate'] = Vehicle::normalizePlate($data['plate']);
        }
        $v->update($data);

        return $v->fresh()->toApi(true);
    }

    public function destroy(int $id)
    {
        $this->vehicle($id)->delete();

        return response()->noContent();
    }

    public function price(Request $request, int $id)
    {
        $data = $request->validate(['price' => 'required|integer|min:0|max:10000000']);
        $v = $this->vehicle($id);
        $v->update($data);

        return $v->toApi();
    }

    public function sold(int $id)
    {
        $v = $this->vehicle($id);
        $v->update(['status' => 'sold', 'sold_at' => now(), 'published_at' => null]);

        return $v->toApi();
    }

    public function publish(Request $request, int $id)
    {
        $published = $request->validate(['published' => 'required|boolean'])['published'];
        $v = $this->vehicle($id);
        if ($published) {
            if (! $v->price) {
                return response()->json(['message' => 'Indiquez un prix avant de mettre en ligne.', 'errors' => ['price' => ['Prix obligatoire']]], 422);
            }
            $v->update(['status' => 'online', 'published_at' => $v->published_at ?? now(), 'sold_at' => null]);
        } else {
            $v->update(['published_at' => null, 'status' => $v->status === 'online' ? 'ready' : $v->status]);
            if ($v->status === 'ready') {
                $v->refreshStatus();
            }
        }

        return $v->fresh()->toApi(true);
    }

    public function history(Request $request, int $id)
    {
        $data = $request->validate(['url' => 'required|url|max:500']);
        $v = $this->vehicle($id);
        $v->update(['history_url' => $data['url']]);

        return $v->toApi();
    }

    public function description(int $id, DescriptionWriter $writer)
    {
        return ['description' => $writer->write($this->vehicle($id))];
    }

    public function export(int $id)
    {
        $v = $this->vehicle($id);
        $v->load(['photos', 'renders']);
        $latest = $v->latestRendersByPhoto();
        $tmp = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($tmp, ZipArchive::OVERWRITE);
        $i = 1;
        foreach ($v->photos as $photo) {
            $r = $latest[$photo->id] ?? null;
            $path = match (true) {
                $photo->kind !== 'exterior', ! $r, $r->status === 'kept_original' => $photo->path,
                $r->status === 'approved' => $r->output_path,
                default => null,
            };
            if ($path) {
                $zip->addFromString(sprintf('%02d.jpg', $i++), Media::get($path));
            }
        }
        $zip->addFromString('annonce.txt', trim($v->label().' '.$v->version)."\n".($v->price ? number_format($v->price, 0, ',', ' ').' €' : '')."\n\n".$v->description);
        $zip->close();

        return response()->download($tmp, $v->plate.'.zip')->deleteFileAfterSend();
    }

    private function validated(Request $request, bool $creating): array
    {
        $req = $creating ? 'required' : 'sometimes';

        return $request->validate([
            'plate' => "$req|string|max:20",
            'vin' => 'nullable|string|max:32',
            'make' => 'nullable|string|max:80',
            'model' => 'nullable|string|max:80',
            'version' => 'nullable|string|max:120',
            'first_registration' => 'nullable|date',
            'fuel' => ['nullable', Rule::in(Vehicle::FUELS)],
            'gearbox' => ['nullable', Rule::in(Vehicle::GEARBOXES)],
            'power_hp' => 'nullable|integer|min:0|max:2000',
            'fiscal_hp' => 'nullable|integer|min:0|max:200',
            'color' => 'nullable|string|max:60',
            'doors' => 'nullable|integer|min:1|max:9',
            'seats' => 'nullable|integer|min:1|max:9',
            'mileage' => 'nullable|integer|min:0|max:2000000',
            'price' => 'nullable|integer|min:0|max:10000000',
            'description' => 'nullable|string|max:10000',
            'equipment' => 'nullable|array',
            'equipment.*.group' => 'required_with:equipment|string|max:60',
            'equipment.*.items' => 'array',
            'equipment.*.items.*' => 'string|max:120',
            'history_url' => 'nullable|url|max:500',
            'photographer_id' => ['nullable', Rule::exists('users', 'id')->where('garage_id', $this->garage()->id)],
        ]);
    }
}
