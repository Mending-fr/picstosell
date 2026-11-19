<?php

namespace App\Services\Vehicle;

use Anthropic\Client;
use App\Models\Vehicle;
use Carbon\Carbon;
use RuntimeException;
use Throwable;

/**
 * Lit une photo de carte grise française (certificat d'immatriculation) et en tire les champs utiles à l'annonce.
 * On ne garde ni le nom ni l'adresse du titulaire.
 */
class RegistrationReader
{
    private const SYSTEM = <<<'TXT'
Tu lis des cartes grises françaises (certificat d'immatriculation) photographiées par un garage.
Recopie exactement les champs demandés, tels qu'imprimés, sans deviner. Un champ illisible ou absent vaut null.
Repères des champs : A immatriculation, B date de 1re immatriculation, D.1 marque, D.2 version, D.3 dénomination commerciale,
E numéro VIN (17 caractères), G.1 poids à vide national (kg), J.1 genre national (VP, CTTE…), P.2 puissance nette max (kW),
P.3 type de carburant (code de 2 lettres : ES, GO, EL, EH, EE, GH, GL, EG, GP, GN, FE…), P.6 puissance administrative (CV),
S.1 nombre de places assises, V.7 CO2 (g/km), V.9 classe environnementale (norme Euro ou directive).
N'écris jamais le nom ni l'adresse du titulaire.
TXT;

    private const FIELDS = ['plate', 'first_registration', 'make', 'version', 'commercial_name', 'vin', 'empty_weight', 'body_type', 'power_kw', 'fuel_code', 'fiscal_hp', 'seats', 'co2', 'euro_norm'];

    /** Codes carburant de la carte grise (P.3) vers les énergies de picstosell. */
    public const FUEL_CODES = [
        'ES' => 'essence', 'GO' => 'diesel', 'EL' => 'electrique', 'EH' => 'hybride', 'GH' => 'hybride',
        'EE' => 'hybride_rechargeable', 'GL' => 'hybride_rechargeable', 'EG' => 'gpl', 'GP' => 'gpl', 'FE' => 'essence',
    ];

    public static function available(): bool
    {
        return (bool) config('services.anthropic.key');
    }

    /**
     * @return array<string, mixed> champs prêts pour la fiche voiture (clés absentes quand illisibles)
     */
    public function read(string $jpegBytes): array
    {
        if (! self::available()) {
            throw new RuntimeException("La lecture de carte grise n'est pas activée (clé IA manquante).");
        }
        $props = [];
        foreach (self::FIELDS as $f) {
            $props[$f] = ['type' => ['string', 'null']];
        }
        $props['first_registration']['description'] = 'Champ B, au format JJ/MM/AAAA';
        $props['euro_norm']['description'] = 'Champ V.9 recopié tel quel';

        $client = new Client(apiKey: config('services.anthropic.key'));
        try {
            $res = $client->messages->create(
                model: config('services.anthropic.document_model') ?: config('services.anthropic.vision_model'),
                // Marge pour la réflexion du modèle, toujours active sur les modèles récents.
                maxTokens: 4000,
                system: self::SYSTEM,
                // Sortie JSON imposée par le schéma : les modèles récents refusent un outil forcé (tool_choice).
                outputConfig: [
                    'effort' => 'low', // simple recopie : pas besoin de réfléchir longtemps
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => ['type' => 'object', 'additionalProperties' => false, 'required' => self::FIELDS, 'properties' => $props],
                    ],
                ],
                messages: [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => base64_encode($jpegBytes)]],
                        ['type' => 'text', 'text' => 'Lis cette carte grise.'],
                    ],
                ]],
            );
        } catch (Throwable $e) {
            report($e);
            throw new RuntimeException("La carte grise n'a pas pu être lue. Réessayez avec une photo plus nette.");
        }
        if ($res->stopReason === 'end_turn') {
            foreach ($res->content as $block) {
                if ($block->type === 'text') {
                    $data = json_decode($block->text, true);
                    if (is_array($data)) {
                        return self::clean($data);
                    }
                }
            }
        }
        report(new RuntimeException('Lecture de carte grise sans JSON exploitable (stop_reason: '.$res->stopReason.')'));
        throw new RuntimeException("La carte grise n'a pas pu être lue. Réessayez avec une photo plus nette.");
    }

    /** Transforme les champs bruts de la carte grise en champs de la fiche voiture. */
    public static function clean(array $raw): array
    {
        $s = fn (string $k) => isset($raw[$k]) && is_string($raw[$k]) && trim($raw[$k]) !== '' ? trim($raw[$k]) : null;
        $int = function (string $k) use ($s): ?int {
            $v = $s($k);
            if ($v === null) {
                return null;
            }
            $v = preg_replace('/[^\d]/', '', $v);

            return $v === '' ? null : (int) $v;
        };

        $out = [];
        if ($p = $s('plate')) {
            $out['plate'] = Vehicle::normalizePlate($p);
        }
        if (($d = $s('first_registration')) && preg_match('#(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})#', $d, $m) && checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            $out['first_registration'] = Carbon::create((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
        }
        if ($v = $s('vin')) {
            $vin = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $v));
            if (strlen($vin) === 17) {
                $out['vin'] = $vin;
            }
        }
        if ($m = $s('make')) {
            $out['make'] = mb_convert_case(mb_strtolower($m), MB_CASE_TITLE);
        }
        if ($c = $s('commercial_name')) {
            $out['model'] = mb_convert_case(mb_strtolower($c), MB_CASE_TITLE);
        }
        if ($code = $s('fuel_code')) {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $code), 0, 2));
            if (isset(self::FUEL_CODES[$code])) {
                $out['fuel'] = self::FUEL_CODES[$code];
            }
        }
        if ($kw = $int('power_kw')) {
            $out['power_hp'] = (int) round($kw * 1.35962);
        }
        foreach (['fiscal_hp' => [1, 200], 'seats' => [1, 9], 'co2' => [0, 999], 'empty_weight' => [300, 9999]] as $k => [$lo, $hi]) {
            $v = $int($k);
            if ($v !== null && $v >= $lo && $v <= $hi) {
                $out[$k] = $v;
            }
        }
        if ($b = $s('body_type')) {
            $out['body_type'] = strtoupper(substr($b, 0, 10));
        }
        if ($e = $s('euro_norm')) {
            $out['euro_norm'] = mb_substr($e, 0, 40);
        }

        return $out;
    }
}
