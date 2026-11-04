<?php

namespace Database\Seeders;

use App\Models\AiConfig;
use App\Models\Garage;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Données de base, rejouables sans risque à chaque déploiement :
 * modèles IA à comparer, garage pilote, compte super-admin (si ADMIN_EMAIL et ADMIN_PASSWORD sont définis).
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $configs = [
            ['Test sans IA (composition simple)', 'fake', 'fake-composite', 'B', '2K', 0, true],
            ['GPT Image 2.5 Flare, méthode B', 'openai', 'gpt-image-2.5-flare', 'B', '2K', 0.014, true],
            ['GPT Image 2.5 Sunburst, méthode B', 'openai', 'gpt-image-2.5-sunburst', 'B', '2K', 0.03, true],
            ['Nano Banana 2.1, méthode A', 'gemini', 'gemini-2.5-flash-image', 'A', '2K', 0.05, true],
            ['Seedream 5.0 Lite, méthode A', 'fal', 'fal-ai/bytedance/seedream/v4/edit', 'A', '2K', 0.035, true],
        ];
        foreach ($configs as [$name, $provider, $model, $method, $quality, $cost, $active]) {
            AiConfig::firstOrCreate(['name' => $name], compact('provider', 'model', 'method', 'quality', 'active') + ['unit_cost_usd' => $cost]);
        }

        $pilot = Garage::firstOrCreate(['slug' => 'valognes-automobiles'], [
            'name' => 'Valognes Automobiles',
            'city' => 'Valognes',
            'status' => 'pilot',
            'vehicle_quota' => 40,
        ]);
        if (! $pilot->ai_config_id) {
            $pilot->update(['ai_config_id' => AiConfig::where('provider', 'fake')->value('id')]);
        }
        $pilot->ensureShowroom();

        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');
        if ($email && $password) {
            $user = User::firstOrNew(['email' => mb_strtolower($email)]);
            if (! $user->exists) {
                $user->fill(['name' => env('ADMIN_NAME', 'Mending'), 'password' => $password, 'role' => 'super_admin'])->save();
            }
        }
    }
}
