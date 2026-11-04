<?php

namespace App\Support\Themes;

use App\Models\Garage;

/**
 * Liste des thèmes du site public : un dossier par thème dans resources/views/themes,
 * décrit par un theme.json. Voir resources/views/themes/README.md.
 */
class ThemeRegistry
{
    public const DEFAULT = 'classique';

    private ?array $manifests = null;

    private function path(): string
    {
        return resource_path('views/themes');
    }

    public function all(): array
    {
        if ($this->manifests === null) {
            $this->manifests = [];
            foreach (glob($this->path().'/*/theme.json') ?: [] as $file) {
                $key = basename(dirname($file));
                $json = json_decode((string) file_get_contents($file), true);
                if (is_array($json) && preg_match('/^[a-z0-9-]+$/', $key)) {
                    $this->manifests[$key] = $json;
                }
            }
            ksort($this->manifests);
        }

        return $this->manifests;
    }

    public function exists(string $key): bool
    {
        return isset($this->all()[$key]);
    }

    public function manifest(string $key): array
    {
        return $this->all()[$key] ?? [];
    }

    /** Un thème réservé (champ « garages ») n'est proposé qu'aux garages listés. */
    public function availableFor(Garage $g): array
    {
        return array_filter($this->all(), function (array $m) use ($g) {
            $only = $m['garages'] ?? [];

            return empty($only) || in_array($g->slug, $only, true);
        });
    }

    public function isAvailable(string $key, Garage $g): bool
    {
        return array_key_exists($key, $this->availableFor($g));
    }

    public function make(string $key, ?Garage $g = null): Theme
    {
        if (! $this->exists($key)) {
            $key = self::DEFAULT;
        }

        return new Theme($key, $this->manifest($key), $this, $g);
    }

    /** Thème affiché pour un garage (réglage Mending), classique par défaut. */
    public function forGarage(Garage $g): Theme
    {
        $key = $g->settings['site_theme'] ?? self::DEFAULT;

        return $this->make($this->exists($key) && $this->isAvailable($key, $g) ? $key : self::DEFAULT, $g);
    }

    /** Description d'un thème pour l'API de l'espace Mending. */
    public function describe(string $key, Garage $g): array
    {
        $m = $this->manifest($key);

        return [
            'key' => $key,
            'name' => $m['name'] ?? ucfirst($key),
            'description' => $m['description'] ?? '',
            'extends' => $m['extends'] ?? null,
            'reserved' => ! empty($m['garages']),
            'options' => $m['options'] ?? [],
            'values' => $g->settings['theme_options'][$key] ?? (object) [],
        ];
    }
}
