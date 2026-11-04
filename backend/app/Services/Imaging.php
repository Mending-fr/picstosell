<?php

namespace App\Services;

use GdImage;
use RuntimeException;

/** Petites opérations d'image avec GD : redimensionnement, miniatures, composition. */
class Imaging
{
    public static function load(string $bytes): GdImage
    {
        $img = @imagecreatefromstring($bytes);
        if (! $img) {
            throw new RuntimeException('Image illisible');
        }
        imagealphablending($img, true);
        imagesavealpha($img, true);

        return $img;
    }

    public static function jpeg(GdImage $img, int $quality = 85): string
    {
        ob_start();
        imagejpeg($img, null, $quality);

        return (string) ob_get_clean();
    }

    public static function png(GdImage $img): string
    {
        imagesavealpha($img, true);
        ob_start();
        imagepng($img);

        return (string) ob_get_clean();
    }

    /** Réduit l'image pour que son plus grand côté fasse au plus $max pixels. */
    public static function fit(GdImage $img, int $max): GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $ratio = min(1, $max / max($w, $h));
        if ($ratio >= 1) {
            return $img;
        }
        $nw = (int) round($w * $ratio);
        $nh = (int) round($h * $ratio);
        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagealphablending($out, true);

        return $out;
    }

    /** Normalise une photo reçue : JPEG, 2560 px max. Renvoie [original, miniature]. */
    public static function prepareUpload(string $bytes): array
    {
        $img = self::load($bytes);
        $main = self::fit($img, 2560);
        $thumb = self::fit($img, 480);

        return [self::jpeg($main, 86), self::jpeg($thumb, 78)];
    }

    /** Fond neutre de showroom quand le garage n'a pas encore photographié l'angle. */
    public static function placeholderBackground(int $w = 2048, int $h = 1280): GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        $wall = imagecolorallocate($img, 217, 212, 202);
        $floor = imagecolorallocate($img, 189, 182, 168);
        imagefilledrectangle($img, 0, 0, $w, (int) ($h * 0.62), $wall);
        imagefilledrectangle($img, 0, (int) ($h * 0.62), $w, $h, $floor);

        return $img;
    }

    /**
     * Pose la voiture (idéalement détourée, PNG transparent) dans le décor.
     * $placement : scale (% de la largeur du décor), bottom (% de la hauteur depuis le bas), offset_x (% du centre).
     * Renvoie [image composée, rectangle de la voiture [x, y, w, h]].
     */
    public static function composite(GdImage $background, GdImage $car, array $placement, bool $shadow = true): array
    {
        $bw = imagesx($background);
        $bh = imagesy($background);
        $out = imagecreatetruecolor($bw, $bh);
        imagecopy($out, $background, 0, 0, 0, 0, $bw, $bh);
        imagealphablending($out, true);

        [$cx, $cy, $cw, $ch] = self::contentBox($car);
        if (isset($placement['height'])) {
            // Hauteur fixe : une voiture a la même hauteur de face comme de profil, seule sa longueur change.
            $th = (int) round($bh * max(10, min(90, (float) $placement['height'])) / 100);
            $tw = (int) round($cw * ($th / max(1, $ch)));
            if ($tw > $bw * 0.96) {
                $tw = (int) round($bw * 0.96);
                $th = (int) round($ch * ($tw / max(1, $cw)));
            }
        } else {
            $scale = max(10, min(100, (float) ($placement['scale'] ?? 66))) / 100;
            $tw = (int) round($bw * $scale);
            $th = (int) round($ch * ($tw / max(1, $cw)));
        }
        $x = (int) round(($bw - $tw) / 2 + $bw * ((float) ($placement['offset_x'] ?? 0)) / 100);
        $y = (int) round($bh - $th - $bh * ((float) ($placement['bottom'] ?? 12)) / 100);

        if ($shadow) {
            $sh = imagecolorallocatealpha($out, 0, 0, 0, 80);
            imagefilledellipse($out, $x + (int) ($tw / 2), $y + $th - (int) ($th * 0.02), (int) ($tw * 0.96), max(8, (int) ($th * 0.12)), $sh);
        }
        imagecopyresampled($out, $car, $x, $y, $cx, $cy, $tw, $th, $cw, $ch);

        return [$out, [$x, $y, $tw, $th, $cx, $cy, $cw, $ch]];
    }

    /** Recolle les pixels opaques de la voiture d'origine par-dessus un rendu (garantit une carrosserie intacte). */
    public static function pasteBack(GdImage $render, GdImage $car, array $rect): GdImage
    {
        [$x, $y, $tw, $th, $cx, $cy, $cw, $ch] = $rect;
        $bw = imagesx($render);
        $bh = imagesy($render);
        $base = imagecreatetruecolor($bw, $bh);
        imagecopyresampled($base, $render, 0, 0, 0, 0, $bw, $bh, imagesx($render), imagesy($render));
        imagealphablending($base, true);
        imagecopyresampled($base, $car, $x, $y, $cx, $cy, $tw, $th, $cw, $ch);

        return $base;
    }

    /** Boîte englobante des pixels non transparents (toute l'image si pas de transparence). */
    public static function contentBox(GdImage $img): array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $step = max(1, (int) floor(max($w, $h) / 400));
        $minX = $w; $minY = $h; $maxX = -1; $maxY = -1;
        for ($y = 0; $y < $h; $y += $step) {
            for ($x = 0; $x < $w; $x += $step) {
                $a = (imagecolorat($img, $x, $y) >> 24) & 0x7F;
                if ($a < 100) {
                    $minX = min($minX, $x); $maxX = max($maxX, $x);
                    $minY = min($minY, $y); $maxY = max($maxY, $y);
                }
            }
        }
        if ($maxX < 0) {
            return [0, 0, $w, $h];
        }

        return [$minX, $minY, min($w, $maxX + $step) - $minX, min($h, $maxY + $step) - $minY];
    }

    /** Silhouette de visée (SVG) pour un angle. */
    public static function overlaySvg(string $key): string
    {
        $side = '<path d="M20 140 L40 95 Q60 70 110 62 L200 58 Q245 60 270 85 L305 100 Q318 110 315 140 Z"/><circle cx="85" cy="145" r="26"/><circle cx="255" cy="145" r="26"/>';
        $front = '<path d="M60 150 L60 95 Q70 60 110 52 L220 52 Q260 60 270 95 L270 150 Z"/><rect x="95" y="62" width="140" height="38" rx="6"/><circle cx="90" cy="118" r="12"/><circle cx="240" cy="118" r="12"/><rect x="70" y="150" width="40" height="22" rx="4"/><rect x="220" y="150" width="40" height="22" rx="4"/>';
        $body = match ($key) {
            'main' => '<g transform="translate(330,0) scale(-1,1)">'.$side.'</g>',
            'front', 'rear' => $front,
            'left', 'front_left', 'rear_right' => '<g transform="translate(330,0) scale(-1,1)">'.$side.'</g>', // capot à gauche
            default => $side,
        };

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 330 190" fill="none" stroke="#7FA2FF" stroke-width="4" stroke-dasharray="12 9">'.$body.'</svg>';
    }
}
