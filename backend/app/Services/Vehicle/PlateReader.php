<?php

namespace App\Services\Vehicle;

use App\Models\Vehicle;
use App\Services\Ai\ClaudeJson;

/**
 * Lit les plaques d'immatriculation françaises visibles sur une photo de parking (inventaire du parc).
 * Ne garde que les numéros au format SIV (AB-123-CD) ou ancien format FNI (123 ABC 50).
 */
class PlateReader
{
    private const SYSTEM = <<<'TXT'
Tu lis les plaques d'immatriculation françaises sur des photos prises en marchant dans le parc d'un garage automobile.
Relève chaque plaque visible, même petite ou en biais, telle qu'elle est écrite. Format récent : 2 lettres, 3 chiffres, 2 lettres (AB-123-CD).
Ancien format : chiffres, lettres, puis numéro de département (1234 AB 50).
confidence vaut « high » quand chaque caractère est net, « low » s'il y a un doute sur au moins un caractère.
N'invente jamais un caractère illisible : ignore une plaque trop floue. Aucune plaque lisible : liste vide.
TXT;

    /** @return list<array{plate:string, confidence:string}> */
    public function read(string $jpegBytes): array
    {
        $data = ClaudeJson::ask(
            config('services.anthropic.vision_model'),
            self::SYSTEM,
            $jpegBytes,
            'Quelles plaques lis-tu sur cette photo ?',
            [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['plates'],
                'properties' => ['plates' => ['type' => 'array', 'items' => [
                    'type' => 'object',
                    'additionalProperties' => false,
                    'required' => ['text', 'confidence'],
                    'properties' => [
                        'text' => ['type' => 'string'],
                        'confidence' => ['type' => 'string', 'enum' => ['high', 'low']],
                    ],
                ]]],
            ],
        );
        $out = [];
        foreach ((array) ($data['plates'] ?? []) as $p) {
            $plate = self::normalize((string) ($p['text'] ?? ''));
            if ($plate && ! isset($out[$plate])) {
                $out[$plate] = ['plate' => $plate, 'confidence' => ($p['confidence'] ?? 'low') === 'high' ? 'high' : 'low'];
            }
        }

        return array_values($out);
    }

    /** Numéro de plaque propre (AB-123-CD ou 123 ABC 50), ou null si ce n'est pas une plaque française. */
    public static function normalize(string $text): ?string
    {
        $c = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $text));
        if (preg_match('/^[A-Z]{2}\d{3}[A-Z]{2}$/', $c)) {
            return Vehicle::normalizePlate($c);
        }
        if (preg_match('/^(\d{1,4})([A-Z]{1,3})(\d{2}|2A|2B|97\d)$/', $c, $m)) {
            return "{$m[1]} {$m[2]} {$m[3]}";
        }

        return null;
    }

    /** Écart d'un caractère entre deux plaques, en tenant compte des confusions de lecture courantes (0/O, 1/I, 8/B…). */
    public static function similar(string $a, string $b): bool
    {
        $a = preg_replace('/[^A-Z0-9]/', '', strtoupper($a));
        $b = preg_replace('/[^A-Z0-9]/', '', strtoupper($b));
        if ($a === $b) {
            return true;
        }
        $look = fn (string $s) => strtr($s, ['O' => '0', 'Q' => '0', 'D' => '0', 'I' => '1', 'L' => '1', 'B' => '8', 'S' => '5', 'Z' => '2', 'G' => '6']);
        if ($look($a) === $look($b)) {
            return true;
        }

        return strlen($a) === strlen($b) && levenshtein($a, $b) === 1;
    }
}
