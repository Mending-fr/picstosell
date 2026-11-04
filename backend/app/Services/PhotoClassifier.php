<?php

namespace App\Services;

use App\Models\Photo;
use App\Models\Shot;
use Anthropic\Client;
use Throwable;

/**
 * Reconnaît une photo de voiture : extérieur (et sous quel angle), intérieur ou détail.
 * Sert à ranger les photos prises librement et à n'envoyer au showroom que l'extérieur.
 * Sans clé Anthropic, ou si l'appel échoue, renvoie null : la photo garde le type donné à l'envoi.
 */
class PhotoClassifier
{
    private const SYSTEM = <<<'TXT'
Tu ranges les photos d'une voiture d'occasion prises par un garage.
Pour chaque photo, dis s'il s'agit de l'extérieur de la voiture, de l'intérieur, ou d'un détail en gros plan.
Extérieur : on voit la voiture entière ou presque, de l'extérieur. Donne l'angle de vue, vu par quelqu'un qui regarde la voiture :
front_left (avant et côté gauche de la voiture visibles, en biais), front (de face), front_right (avant et côté droit), right (profil droit),
rear_right (arrière et côté droit), rear (de dos), rear_left (arrière et côté gauche), left (profil gauche).
Le côté gauche de la voiture est celui du conducteur en France.
Intérieur : sièges, tableau de bord, compteur, écran, coffre. Détail : gros plan sur une jante, un pneu, un phare, un défaut, un équipement, un document.
Appelle toujours l'outil report_photo.
TXT;

    private const TOOL = [
        'name' => 'report_photo',
        'description' => 'Renvoie le type de photo, l\'angle pour une vue extérieure et un libellé pour l\'intérieur ou un détail.',
        'strict' => true,
        'inputSchema' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['kind', 'view', 'label'],
            'properties' => [
                'kind' => ['type' => 'string', 'enum' => Photo::KINDS],
                'view' => ['type' => 'string', 'enum' => [...Shot::KEYS, 'none'], 'description' => "Angle pour une vue extérieure, sinon « none »."],
                'label' => ['type' => 'string', 'enum' => [...Photo::INTERIOR_LABELS, ...Photo::DETAIL_LABELS, 'none'], 'description' => "Ce que montre une photo d'intérieur ou de détail, sinon « none »."],
            ],
        ],
    ];

    public static function available(): bool
    {
        return (bool) config('services.anthropic.key');
    }

    /** @return array{kind:string,view:?string,label:?string}|null */
    public function classify(string $jpegBytes): ?array
    {
        if (! self::available()) {
            return null;
        }
        try {
            $client = new Client(apiKey: config('services.anthropic.key'));
            $res = $client->messages->create(
                model: config('services.anthropic.vision_model'),
                maxTokens: 300,
                system: self::SYSTEM,
                tools: [self::TOOL],
                toolChoice: ['type' => 'tool', 'name' => 'report_photo'],
                messages: [[
                    'role' => 'user',
                    'content' => [
                        ['type' => 'image', 'source' => ['type' => 'base64', 'mediaType' => 'image/jpeg', 'data' => base64_encode($jpegBytes)]],
                        ['type' => 'text', 'text' => 'Range cette photo.'],
                    ],
                ]],
            );
            foreach ($res->content as $block) {
                if ($block->type === 'tool_use' && $block->name === 'report_photo') {
                    return $this->clean((array) $block->input);
                }
            }
        } catch (Throwable $e) {
            report($e);
        }

        return null;
    }

    private function clean(array $in): ?array
    {
        $kind = in_array($in['kind'] ?? null, Photo::KINDS, true) ? $in['kind'] : null;
        if (! $kind) {
            return null;
        }
        $view = $kind === 'exterior' && array_key_exists($in['view'] ?? '', Photo::VIEWS) ? $in['view'] : null;
        $labels = $kind === 'interior' ? Photo::INTERIOR_LABELS : ($kind === 'detail' ? Photo::DETAIL_LABELS : []);
        $label = in_array($in['label'] ?? null, $labels, true) ? $in['label'] : null;

        return ['kind' => $kind, 'view' => $view, 'label' => $label];
    }
}
