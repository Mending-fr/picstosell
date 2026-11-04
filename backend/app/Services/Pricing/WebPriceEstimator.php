<?php

namespace App\Services\Pricing;

use Anthropic\Client;
use RuntimeException;

/**
 * Estimation par Claude avec recherche web : annonces comparables publiées en France, puis fourchette.
 * Le modèle doit citer chaque annonce retenue (lien, prix, année, kilométrage) ; rien n'est inventé.
 */
class WebPriceEstimator
{
    private const SYSTEM = <<<'TXT'
Tu aides un garage français à fixer le prix de vente d'une voiture d'occasion.
Cherche sur le web des annonces individuelles de voitures comparables à vendre en France : même marque et modèle, version ou motorisation proche, année à un an près, kilométrage proche.
Fais plusieurs recherches et varie les sites : La Centrale, AutoScout24, Leboncoin, ParuVendu, Aramisauto, Spoticar, Renew (Renault), réseaux de concessionnaires.
Une page de liste de résultats ne compte pas comme une annonce : retiens des annonces précises, avec leur lien, leur prix, et si possible l'année et le kilométrage.
Retiens de 3 à 8 annonces réelles que tu as vues dans tes résultats. N'invente aucune annonce, aucun prix, aucun lien.
Déduis-en une fourchette réaliste pour un garage (prix affiché TTC), et un prix conseillé dans cette fourchette, en tenant compte du kilométrage et de la version.
Si tu trouves moins de 3 annonces comparables, dis-le dans le commentaire et mets la confiance à « low ».
Termine toujours en appelant l'outil report_estimate. Le commentaire est en français simple, 2 phrases maximum, sans tiret cadratin, sans parler de toi.
TXT;

    private const TOOL = [
        'name' => 'report_estimate',
        'description' => "Renvoie les annonces comparables retenues et la fourchette de prix proposée.",
        'strict' => true,
        'inputSchema' => [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['comparables', 'low', 'high', 'suggested', 'confidence', 'comment'],
            'properties' => [
                'comparables' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['title', 'url', 'price', 'year', 'mileage', 'site'],
                        'properties' => [
                            'title' => ['type' => 'string'],
                            'url' => ['type' => 'string'],
                            'price' => ['type' => 'integer'],
                            'year' => ['type' => ['integer', 'null']],
                            'mileage' => ['type' => ['integer', 'null']],
                            'site' => ['type' => 'string'],
                        ],
                    ],
                ],
                'low' => ['type' => 'integer'],
                'high' => ['type' => 'integer'],
                'suggested' => ['type' => 'integer'],
                'confidence' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
                'comment' => ['type' => 'string'],
            ],
        ],
    ];

    public static function available(): bool
    {
        return (bool) config('services.anthropic.key');
    }

    /** @return array{low:int,high:int,suggested:int,confidence:string,comment:string,comparables:array} */
    public function estimate(array $car): array
    {
        $client = new Client(apiKey: config('services.anthropic.key'));
        $tools = [
            ['type' => 'web_search_20260209', 'name' => 'web_search', 'maxUses' => 10, 'userLocation' => ['type' => 'approximate', 'country' => 'FR']],
            self::TOOL,
        ];
        $messages = [['role' => 'user', 'content' => "Voiture à estimer :\n".json_encode($car, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]];

        for ($turn = 0; $turn < 4; $turn++) {
            $res = $client->messages->create(
                model: config('services.anthropic.model'),
                maxTokens: 8000,
                system: self::SYSTEM,
                outputConfig: ['effort' => 'medium'],
                tools: $tools,
                messages: $messages,
            );
            foreach ($res->content as $block) {
                if ($block->type === 'tool_use' && $block->name === 'report_estimate') {
                    return $this->clean((array) $block->input);
                }
            }
            if ($res->stopReason === 'refusal') {
                throw new RuntimeException('Estimation refusée par le modèle');
            }
            // pause_turn (recherche longue) ou réponse sans l'outil : on relance la même conversation.
            $messages[] = ['role' => 'assistant', 'content' => $res->content];
            if ($res->stopReason !== 'pause_turn') {
                $messages[] = ['role' => 'user', 'content' => 'Appelle maintenant report_estimate avec ce que tu as trouvé.'];
            }
        }
        throw new RuntimeException("L'estimation n'a pas abouti");
    }

    private function clean(array $in): array
    {
        $comps = [];
        foreach ($in['comparables'] ?? [] as $c) {
            $c = (array) $c;
            $url = (string) ($c['url'] ?? '');
            if (! preg_match('#^https?://#', $url) || (int) ($c['price'] ?? 0) <= 0) {
                continue;
            }
            $comps[] = [
                'title' => mb_substr((string) $c['title'], 0, 140),
                'url' => $url,
                'price' => (int) $c['price'],
                'year' => isset($c['year']) ? (int) $c['year'] : null,
                'mileage' => isset($c['mileage']) ? (int) $c['mileage'] : null,
                'site' => mb_substr((string) ($c['site'] ?? parse_url($url, PHP_URL_HOST)), 0, 60),
            ];
        }
        $low = (int) ($in['low'] ?? 0);
        $high = (int) ($in['high'] ?? 0);
        if ($low > $high) {
            [$low, $high] = [$high, $low];
        }

        return [
            'low' => $low,
            'high' => $high,
            'suggested' => min(max((int) ($in['suggested'] ?? 0), $low), $high),
            'confidence' => in_array($in['confidence'] ?? '', ['low', 'medium', 'high'], true) ? $in['confidence'] : 'low',
            'comment' => str_replace(['—', '–'], [',', '-'], (string) ($in['comment'] ?? '')),
            'comparables' => $comps,
        ];
    }
}
