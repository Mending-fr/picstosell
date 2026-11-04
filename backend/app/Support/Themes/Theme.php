<?php

namespace App\Support\Themes;

use App\Models\Garage;
use Illuminate\Support\Facades\View;

/** Un thème du site public, lié au garage qui l'affiche. */
class Theme
{
    public function __construct(
        public readonly string $key,
        public readonly array $manifest,
        private readonly ThemeRegistry $registry,
        private readonly ?Garage $garage = null,
    ) {}

    public function name(): string
    {
        return $this->manifest['name'] ?? ucfirst($this->key);
    }

    /** Chaîne des thèmes à parcourir : celui-ci, ses parents, puis classique. */
    public function chain(): array
    {
        $chain = [];
        $key = $this->key;
        while ($key && ! in_array($key, $chain, true) && $this->registry->exists($key)) {
            $chain[] = $key;
            $key = $this->registry->manifest($key)['extends'] ?? null;
        }
        if (! in_array(ThemeRegistry::DEFAULT, $chain, true)) {
            $chain[] = ThemeRegistry::DEFAULT;
        }

        return $chain;
    }

    /** Nom de la vue Blade pour une page, avec repli sur le thème parent. */
    public function view(string $page): string
    {
        foreach ($this->chain() as $key) {
            if (View::exists("themes.$key.$page")) {
                return "themes.$key.$page";
            }
        }

        return 'themes.'.ThemeRegistry::DEFAULT.'.'.$page;
    }

    /** Valeur d'une option : celle du garage, sinon la valeur par défaut du thème. */
    public function opt(string $key, mixed $fallback = null): mixed
    {
        $saved = $this->garage?->settings['theme_options'][$this->key][$key] ?? null;
        if ($saved !== null && $saved !== '') {
            return $saved;
        }
        foreach ($this->manifest['options'] ?? [] as $o) {
            if (($o['key'] ?? null) === $key && ($o['default'] ?? '') !== '') {
                return $o['default'];
            }
        }

        return $fallback;
    }

    public function options(): array
    {
        return $this->manifest['options'] ?? [];
    }
}
