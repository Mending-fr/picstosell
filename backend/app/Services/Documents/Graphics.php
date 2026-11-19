<?php

namespace App\Services\Documents;

use App\Services\Imaging;
use App\Services\Vehicle\Emissions;
use GdImage;

/** Dessins des documents imprimés : pastille Crit'Air et échelle CO2, en PNG pour un rendu identique partout. */
class Graphics
{
    private static function font(bool $bold = true): string
    {
        return resource_path('fonts/Carlito-'.($bold ? 'Bold' : 'Regular').'.ttf');
    }

    private static function rgb(GdImage $im, string $hex, int $alpha = 0): int
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%02x%02x%02x');

        return imagecolorallocatealpha($im, $r, $g, $b, $alpha);
    }

    private static function canvas(int $w, int $h): GdImage
    {
        $im = imagecreatetruecolor($w, $h);
        imagealphablending($im, false);
        imagesavealpha($im, true);
        imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 255, 255, 127));
        imagealphablending($im, true);
        imageantialias($im, true);

        return $im;
    }

    private static function text(GdImage $im, float $size, int $x, int $y, int $color, string $t, bool $bold = true, string $align = 'left'): void
    {
        $box = imagettfbbox($size, 0, self::font($bold), $t);
        $w = $box[2] - $box[0];
        $dx = $align === 'center' ? -$w / 2 : ($align === 'right' ? -$w : 0);
        imagettftext($im, $size, 0, (int) ($x + $dx), $y, $color, self::font($bold), $t);
    }

    /** Pastille Crit'Air ronde, couleur officielle et numéro. */
    public static function critair(string $value): string
    {
        $s = 600;
        $im = self::canvas($s, $s);
        $color = Emissions::CRITAIR_COLORS[$value] ?? '#cccccc';
        $white = self::rgb($im, '#ffffff');
        imagefilledellipse($im, (int) ($s / 2), (int) ($s / 2), $s - 8, $s - 8, self::rgb($im, '#d9d9d9'));
        imagefilledellipse($im, (int) ($s / 2), (int) ($s / 2), $s - 20, $s - 20, self::rgb($im, $color));
        imagesetthickness($im, 6);
        imagearc($im, (int) ($s / 2), (int) ($s / 2), $s - 70, $s - 70, 0, 360, $white);
        imagesetthickness($im, 1);
        $ink = in_array($value, ['2', 'non_classe'], true) ? self::rgb($im, '#303030') : $white;
        self::text($im, 54, (int) ($s / 2), 165, $ink, "CRIT'Air", true, 'center');
        if ($value === 'non_classe') {
            self::text($im, 64, (int) ($s / 2), 350, $ink, 'NON', true, 'center');
            self::text($im, 64, (int) ($s / 2), 440, $ink, 'CLASSÉ', true, 'center');
        } else {
            self::text($im, 230, (int) ($s / 2), 450, $ink, $value, true, 'center');
        }

        return Imaging::png($im);
    }

    /** Échelle CO2 de A à G ; la flèche de la classe de la voiture est dessinée à droite. */
    public static function co2Scale(?string $class): string
    {
        $w = 1400;
        $rowH = 92;
        $gap = 18;
        $rows = array_keys(Emissions::CO2_CLASSES);
        $h = count($rows) * ($rowH + $gap);
        $im = self::canvas($w, $h);
        $colors = ['A' => '#1f8a3c', 'B' => '#3aa935', 'C' => '#9ccb3b', 'D' => '#f2e600', 'E' => '#f6b21b', 'F' => '#ef7d22', 'G' => '#e2231a'];
        $labels = ['A' => 'Inférieures ou égales à 100 g/km', 'B' => 'de 101 à 120 g/km', 'C' => 'de 121 à 140 g/km', 'D' => 'de 141 à 160 g/km', 'E' => 'de 161 à 200 g/km', 'F' => 'de 201 à 250 g/km', 'G' => 'supérieures à 250 g/km'];
        $dark = self::rgb($im, '#1a1a1a');
        foreach ($rows as $i => $k) {
            $y = $i * ($rowH + $gap) + 8;
            $len = 430 + $i * 45;
            $c = self::rgb($im, $colors[$k]);
            imagefilledrectangle($im, 0, $y, $len, $y + $rowH, $c);
            imagefilledpolygon($im, [$len, $y, $len + 50, (int) ($y + $rowH / 2), $len, $y + $rowH], $c);
            $ink = in_array($k, ['C', 'D', 'E'], true) ? $dark : self::rgb($im, '#ffffff');
            if ($k === 'A') {
                self::text($im, 22, 14, (int) ($y + $rowH / 2 - 6), $ink, 'Inférieures ou', false);
                self::text($im, 22, 14, (int) ($y + $rowH / 2 + 26), $ink, 'égales à 100 g/km', false);
            } else {
                self::text($im, 22, 14, (int) ($y + $rowH / 2 + 9), $ink, $labels[$k], false);
            }
            self::text($im, 52, $len - 10, (int) ($y + $rowH / 2 + 20), $ink, $k, false, 'right');
            if ($k === $class) {
                $x0 = 860;
                imagefilledpolygon($im, [$x0, (int) ($y + $rowH / 2), $x0 + 70, $y - 6, $w - 10, $y - 6, $w - 10, $y + $rowH + 6, $x0 + 70, $y + $rowH + 6], $c);
                self::text($im, 62, (int) (($x0 + 70 + $w - 10) / 2), (int) ($y + $rowH / 2 + 24), $ink, $k, true, 'center');
            }
        }

        return Imaging::png($im);
    }
}
