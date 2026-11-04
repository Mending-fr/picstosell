<?php

namespace App\Services;

use Anthropic\Client;
use App\Models\Vehicle;
use Throwable;

/**
 * Propose un texte d'annonce à partir des seules données saisies : aucune affirmation inventée.
 * Avec ANTHROPIC_API_KEY, le texte est rédigé par Claude ; sinon (ou en cas d'échec), par le modèle ci-dessous.
 * Le gérant relit toujours avant publication.
 */
class DescriptionWriter
{
    private const SYSTEM = <<<'TXT'
Tu rédiges des annonces de voitures d'occasion pour le site d'un garage français.
Règles :
- Utilise uniquement les informations fournies. N'invente rien : ni état, ni entretien, ni historique, ni garantie, ni option absente de la liste.
- Français simple et chaleureux, comme un vendeur de garage qui parle à un particulier. Pas de superlatifs creux, pas d'emoji, pas de tiret cadratin.
- 60 à 120 mots, en 2 ou 3 courts paragraphes : ce qu'est la voiture, ses points pratiques (motorisation, boîte, équipements utiles au quotidien), puis une invitation à venir la voir ou l'essayer.
- Ne répète pas le prix. Ne mets pas de titre.
Réponds uniquement par le texte de l'annonce.
TXT;

    public function write(Vehicle $v): string
    {
        if (config('services.anthropic.key')) {
            try {
                $text = $this->withClaude($v);
                if ($text !== null) {
                    return $text;
                }
            } catch (Throwable $e) {
                report($e);
            }
        }

        return $this->template($v);
    }

    private function withClaude(Vehicle $v): ?string
    {
        $client = new Client(apiKey: config('services.anthropic.key'));
        $message = $client->messages->create(
            model: config('services.anthropic.model'),
            maxTokens: 2000,
            system: self::SYSTEM,
            outputConfig: ['effort' => 'low'],
            messages: [['role' => 'user', 'content' => "Données de la voiture :\n".json_encode($this->facts($v), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)]],
        );
        if ($message->stopReason === 'refusal') {
            return null;
        }
        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }
        $text = trim(str_replace(['—', '–'], [',', '-'], $text));

        return $text !== '' ? $text : null;
    }

    private function facts(Vehicle $v): array
    {
        return array_filter([
            'marque' => $v->make,
            'modèle' => $v->model,
            'version' => $v->version,
            'mise en circulation' => $v->first_registration?->format('m/Y'),
            'kilométrage' => $v->mileage !== null ? number_format($v->mileage, 0, ',', ' ').' km' : null,
            'carburant' => $v->fuel ? Vehicle::FUEL_LABELS[$v->fuel] ?? $v->fuel : null,
            'boîte' => $v->gearbox,
            'puissance' => $v->power_hp ? $v->power_hp.' ch' : null,
            'puissance fiscale' => $v->fiscal_hp ? $v->fiscal_hp.' CV' : null,
            'couleur' => $v->color,
            'portes' => $v->doors,
            'places' => $v->seats,
            'équipements' => collect($v->equipment ?? [])->mapWithKeys(fn ($g) => [$g['group'] ?? 'Divers' => $g['items'] ?? []])->filter()->all() ?: null,
            'historique' => $v->history_url ? 'Rapport HistoVec disponible' : null,
        ], fn ($x) => $x !== null && $x !== '');
    }

    private const MONTHS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    public function template(Vehicle $v): string
    {
        $title = trim(implode(' ', array_filter([$v->make, $v->model, $v->version])));
        $parts = [];
        $first = $title ?: 'Véhicule';
        if ($v->first_registration) {
            $first .= ' de '.self::MONTHS[$v->first_registration->month - 1].' '.$v->first_registration->year;
        }
        if ($v->mileage) {
            $first .= ', '.number_format($v->mileage, 0, ',', ' ').' km';
        }
        $parts[] = $first.'.';

        $tech = array_filter([
            $v->fuel ? Vehicle::FUEL_LABELS[$v->fuel] ?? null : null,
            $v->gearbox ? 'boîte '.$v->gearbox : null,
            $v->power_hp ? $v->power_hp.' ch' : null,
            $v->fiscal_hp ? $v->fiscal_hp.' CV fiscaux' : null,
            $v->doors ? $v->doors.' portes' : null,
            $v->seats && $v->seats != 5 ? $v->seats.' places' : null,
            $v->color ? 'couleur '.mb_strtolower($v->color) : null,
        ]);
        if ($tech) {
            $parts[] = ucfirst(implode(', ', $tech)).'.';
        }

        $items = collect($v->equipment ?? [])->flatMap(fn ($g) => $g['items'] ?? [])->filter()->take(8)->values();
        if ($items->isNotEmpty()) {
            $parts[] = 'Équipements : '.mb_strtolower($items->implode(', ')).'.';
        }
        $parts[] = 'Visible sur place, contactez-nous pour un essai.';

        return implode(' ', $parts);
    }
}
