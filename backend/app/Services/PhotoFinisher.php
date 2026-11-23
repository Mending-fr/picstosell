<?php

namespace App\Services;

use App\Models\Garage;
use App\Services\Ai\ClaudeJson;
use App\Support\Media;
use GdImage;
use Throwable;

/**
 * Dernière touche avant publication : plaque cachée par une plaque aux couleurs du garage, et logo en coin.
 * Selon les réglages du garage (hide_plate, add_logo). Sans clé IA, la plaque ne peut pas être repérée : on la laisse.
 */
class PhotoFinisher
{
    private const FONT = 'fonts/Carlito-Bold.ttf';

    public function finish(GdImage $img, Garage $garage): GdImage
    {
        if ($garage->setting('hide_plate')) {
            foreach ($this->findPlates($img) as $quad) {
                $this->coverPlate($img, $quad, $garage);
            }
        }
        if ($garage->setting('add_logo') && $garage->logo_path) {
            $this->stampLogo($img, $garage);
        }

        return $img;
    }

    /**
     * Repère les plaques visibles. Renvoie, pour chacune, ses 4 coins en pixels de l'image.
     *
     * @return list<list<array{0:int,1:int}>>
     */
    public function findPlates(GdImage $img): array
    {
        if (! config('services.anthropic.key')) {
            return [];
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $small = Imaging::fit($img, 1024);
        $sw = imagesx($small);
        $sh = imagesy($small);
        $point = ['type' => 'object', 'additionalProperties' => false, 'required' => ['x', 'y'], 'properties' => ['x' => ['type' => 'integer'], 'y' => ['type' => 'integer']]];
        $data = ClaudeJson::ask(
            config('services.anthropic.vision_model'),
            "Tu repères les plaques d'immatriculation sur des photos de voitures. Pour chaque plaque lisible ou partiellement visible, donne ses 4 coins dans l'ordre haut gauche, haut droit, bas droit, bas gauche, en pixels d'une image de {$sw} × {$sh} pixels, en suivant l'inclinaison de la plaque. Aucune plaque : liste vide.",
            Imaging::jpeg($small, 85),
            'Où sont les plaques ?',
            [
                'type' => 'object', 'additionalProperties' => false, 'required' => ['plates'],
                'properties' => ['plates' => ['type' => 'array', 'items' => [
                    'type' => 'object', 'additionalProperties' => false, 'required' => ['corners'],
                    'properties' => ['corners' => ['type' => 'array', 'items' => $point]],
                ]]],
            ],
        );
        if (! $data) {
            return [];
        }
        $out = [];
        foreach ((array) ($data['plates'] ?? []) as $p) {
            $corners = (array) ($p['corners'] ?? []);
            if (count($corners) !== 4) {
                continue;
            }
            $quad = [];
            foreach ($corners as $c) {
                $quad[] = [(int) round(((int) ($c['x'] ?? 0)) * $w / $sw), (int) round(((int) ($c['y'] ?? 0)) * $h / $sh)];
            }
            $out[] = $quad;
        }

        return $out;
    }

    /** Couvre la plaque d'un cache blanc légèrement plus grand, au nom ou au logo du garage. */
    public function coverPlate(GdImage $img, array $quad, Garage $garage): void
    {
        $cx = array_sum(array_column($quad, 0)) / 4;
        $cy = array_sum(array_column($quad, 1)) / 4;
        // 12 % de marge : le repérage est approximatif, mieux vaut déborder que laisser dépasser un caractère.
        $grown = array_map(fn ($p) => [(int) round($cx + ($p[0] - $cx) * 1.12), (int) round($cy + ($p[1] - $cy) * 1.18)], $quad);
        $xs = array_column($grown, 0);
        $ys = array_column($grown, 1);
        $bw = max($xs) - min($xs);
        $bh = max($ys) - min($ys);
        if ($bw < 8 || $bh < 4) {
            return;
        }
        imagealphablending($img, true);
        $white = imagecolorallocate($img, 255, 255, 255);
        $edge = imagecolorallocate($img, 190, 190, 190);
        $flat = array_merge(...$grown);
        imagefilledpolygon($img, $flat, $white);
        imagepolygon($img, $flat, $edge);

        // Contenu : le logo s'il existe, sinon le nom du garage dans sa couleur.
        $innerW = (int) ($bw * 0.78);
        $innerH = (int) ($bh * 0.6);
        if ($garage->logo_path) {
            try {
                $logo = Imaging::load(Media::get($garage->logo_path));
                $ratio = min($innerW / imagesx($logo), $innerH / imagesy($logo));
                $lw = max(1, (int) (imagesx($logo) * $ratio));
                $lh = max(1, (int) (imagesy($logo) * $ratio));
                imagecopyresampled($img, $logo, (int) ($cx - $lw / 2), (int) ($cy - $lh / 2), 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));

                return;
            } catch (Throwable) {
                // logo illisible : on écrit le nom
            }
        }
        $this->centeredText($img, mb_strtoupper($garage->name), (int) $cx, (int) $cy, $innerW, $innerH, $garage->brand_color ?: '#303030');
    }

    public function stampLogo(GdImage $img, Garage $garage): void
    {
        try {
            $logo = Imaging::load(Media::get($garage->logo_path));
        } catch (Throwable) {
            return;
        }
        $w = imagesx($img);
        $h = imagesy($img);
        $target = (int) ($w * 0.14);
        $ratio = min($target / imagesx($logo), ($h * 0.1) / imagesy($logo));
        $lw = max(1, (int) (imagesx($logo) * $ratio));
        $lh = max(1, (int) (imagesy($logo) * $ratio));
        $pad = (int) ($w * 0.012);
        $x = $w - $lw - $pad * 3;
        $y = $h - $lh - $pad * 3;
        $bg = imagecolorallocatealpha($img, 255, 255, 255, 25);
        imagefilledrectangle($img, $x - $pad, $y - $pad, $x + $lw + $pad, $y + $lh + $pad, $bg);
        imagecopyresampled($img, $logo, $x, $y, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));
    }

    private function centeredText(GdImage $img, string $text, int $cx, int $cy, int $maxW, int $maxH, string $hex): void
    {
        $font = resource_path(self::FONT);
        if (! is_file($font) || $text === '') {
            return;
        }
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');
        $color = imagecolorallocate($img, $r ?? 48, $g ?? 48, $b ?? 48);
        $size = max(6, $maxH);
        for ($i = 0; $i < 30; $i++) {
            $box = imagettfbbox($size, 0, $font, $text);
            $tw = $box[2] - $box[0];
            $th = $box[1] - $box[7];
            if ($tw <= $maxW && $th <= $maxH) {
                break;
            }
            $size *= 0.9;
        }
        $box = imagettfbbox($size, 0, $font, $text);
        $tw = $box[2] - $box[0];
        $th = $box[1] - $box[7];
        imagettftext($img, $size, 0, (int) ($cx - $tw / 2 - $box[0]), (int) ($cy + $th / 2 - $box[1]), $color, $font, $text);
    }
}
