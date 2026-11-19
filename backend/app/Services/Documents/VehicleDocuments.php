<?php

namespace App\Services\Documents;

use App\Models\Vehicle;
use App\Services\Imaging;
use App\Services\Vehicle\Emissions;
use App\Support\Badges;
use App\Support\Media;
use Dompdf\Dompdf;
use Dompdf\Options;
use Throwable;

/**
 * Documents imprimables d'une voiture, en PDF A4 : affiche prix (pare-brise), étiquette CO2 et fiche offre commerciale.
 */
class VehicleDocuments
{
    public const TYPES = ['affiche' => 'Affiche prix', 'co2' => 'Étiquette CO2', 'offre' => 'Fiche offre'];

    public function pdf(Vehicle $v, string $type): string
    {
        $html = view('documents.'.$type, $this->data($v))->render();
        $cache = storage_path('fonts');
        if (! is_dir($cache)) {
            @mkdir($cache, 0775, true);
        }
        $opt = new Options;
        $opt->setChroot([resource_path(), $cache]);
        $opt->setFontDir($cache);
        $opt->setFontCache($cache);
        $opt->setIsRemoteEnabled(false);
        $opt->setDefaultFont('Carlito');
        $pdf = new Dompdf($opt);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();

        return $pdf->output();
    }

    public function data(Vehicle $v): array
    {
        $g = $v->garage;
        $critair = $v->critairValue();
        $class = Emissions::co2Class($v->co2);
        $header = trim((string) $g->setting('doc_header'));
        $lines = $header !== '' ? preg_split('/\R/', $header) : array_filter([mb_strtoupper($g->name), $g->address, $g->city]);

        return [
            'v' => $v,
            'g' => $g,
            'fonts' => resource_path('fonts'),
            'headerLines' => array_values($lines),
            'color' => $g->setting('doc_color') ?: '#1b6d8f',
            'footer' => $g->setting('doc_footer'),
            'priceNote' => $g->setting('price_note'),
            'logo' => $this->dataUri($g->logo_path, 500, 'png'),
            'critair' => $critair,
            'critairImg' => $critair ? 'data:image/png;base64,'.base64_encode(Graphics::critair($critair)) : null,
            'co2Class' => $class,
            'co2Scale' => 'data:image/png;base64,'.base64_encode(Graphics::co2Scale($class)),
            'badges' => Badges::labels($v),
            'photos' => array_values(array_filter(array_map(fn ($p) => $this->dataUri($p, 1200), array_slice($this->photoPaths($v), 0, 2)))),
            'fuel' => $v->fuelLabel(),
            'origin' => $v->origin ? (Vehicle::ORIGIN_LABELS[$v->origin] ?? $v->origin) : null,
            'price' => $v->price !== null ? number_format($v->price, 0, ',', ' ') : null,
            'km' => $v->mileage !== null ? number_format($v->mileage, 0, ',', ' ') : null,
            'genre' => match ($v->body_type) {
                'VP', null => 'Voiture particulière (VP)',
                'CTTE' => 'Camionnette (CTTE)',
                default => $v->body_type,
            },
        ];
    }

    /** Photos extérieures publiées, dans l'ordre du site. */
    private function photoPaths(Vehicle $v): array
    {
        $v->loadMissing(['photos', 'renders']);
        $latest = $v->latestRendersByPhoto();
        $out = [];
        foreach ($v->photos as $p) {
            if ($p->kind !== 'exterior') {
                continue;
            }
            $r = $latest[$p->id] ?? null;
            $out[] = $r && $r->status === 'approved' && $r->output_path ? $r->output_path : ($p->published_path ?? $p->path);
        }

        return $out;
    }

    private function dataUri(?string $path, int $max, string $fmt = 'jpeg'): ?string
    {
        if (! $path) {
            return null;
        }
        try {
            $img = Imaging::fit(Imaging::load(Media::get($path)), $max);

            return $fmt === 'png'
                ? 'data:image/png;base64,'.base64_encode(Imaging::png($img))
                : 'data:image/jpeg;base64,'.base64_encode(Imaging::jpeg($img, 82));
        } catch (Throwable) {
            return null;
        }
    }
}
