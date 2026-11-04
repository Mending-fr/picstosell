<?php

namespace App\Support\Themes;

/** Mensualité d'exemple affichée par les thèmes qui proposent un financement. */
class Finance
{
    /** Mensualité arrondie à l'euro, ou null si le thème n'a pas de taux renseigné. */
    public static function monthly(Theme $t, ?int $price): ?int
    {
        $rate = $t->opt('finance_rate');
        $months = (int) $t->opt('finance_months', 60);
        if ($rate === null || ! $price || $months < 6) {
            return null;
        }
        $capital = max(0, $price - (int) $t->opt('finance_deposit', 0));
        if ($capital <= 0) {
            return null;
        }
        $r = ((float) $rate) / 100 / 12;
        $m = $r > 0 ? $capital * $r / (1 - (1 + $r) ** -$months) : $capital / $months;

        return (int) ceil($m);
    }

    public static function enabled(Theme $t): bool
    {
        return $t->opt('finance_rate') !== null;
    }
}
