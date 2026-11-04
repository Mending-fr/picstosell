<?php

namespace App\Http\Controllers\Api\Admin;

use App\Models\Garage;
use App\Support\Themes\ThemeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;

/** Choix du thème du site public d'un garage, depuis l'espace Mending. */
class SiteThemeController
{
    public function __construct(private ThemeRegistry $themes) {}

    public function index(Garage $garage)
    {
        $current = $this->themes->forGarage($garage)->key;
        $list = [];
        foreach (array_keys($this->themes->availableFor($garage)) as $key) {
            $list[] = $this->themes->describe($key, $garage) + [
                'preview_url' => URL::temporarySignedRoute('site.index', now()->addHour(), ['slug' => $garage->slug, 'theme' => $key]),
            ];
        }
        usort($list, fn ($a, $b) => [$a['key'] !== 'classique', $a['name']] <=> [$b['key'] !== 'classique', $b['name']]);

        return ['current' => $current, 'site_url' => $garage->siteUrl(), 'themes' => $list];
    }

    public function update(Request $request, Garage $garage)
    {
        $data = $request->validate([
            'theme' => 'required|string|max:60',
            'options' => 'sometimes|array',
        ]);
        $key = $data['theme'];
        if (! $this->themes->exists($key) || ! $this->themes->isAvailable($key, $garage)) {
            throw ValidationException::withMessages(['theme' => "Ce thème n'existe pas ou n'est pas proposé pour ce garage."]);
        }

        $settings = $garage->settings ?? [];
        $settings['site_theme'] = $key;
        if (array_key_exists('options', $data)) {
            $settings['theme_options'][$key] = $this->clean($key, $data['options']);
        }
        $garage->settings = $settings;
        $garage->save();

        return $this->index($garage->fresh());
    }

    /** Ne garde que les options déclarées par le thème, au bon type. */
    private function clean(string $key, array $input): array
    {
        $out = [];
        foreach ($this->themes->manifest($key)['options'] ?? [] as $o) {
            $k = $o['key'] ?? null;
            if (! $k || ! array_key_exists($k, $input)) {
                continue;
            }
            $v = $input[$k];
            $out[$k] = match ($o['type'] ?? 'text') {
                'number' => $v === null || $v === '' ? null : (is_numeric(str_replace(',', '.', (string) $v)) ? (float) str_replace(',', '.', (string) $v) : throw ValidationException::withMessages(["options.$k" => '« '.($o['label'] ?? $k).' » doit être un nombre.'])),
                'toggle' => (bool) $v,
                'textarea' => $v === null ? null : mb_substr((string) $v, 0, 2000),
                default => $v === null ? null : mb_substr((string) $v, 0, 300),
            };
        }

        return $out;
    }
}
