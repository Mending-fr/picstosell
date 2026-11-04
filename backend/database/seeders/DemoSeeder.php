<?php

namespace Database\Seeders;

use App\Models\Garage;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Imaging;
use App\Support\Media;
use Illuminate\Database\Seeder;

/** Données de démonstration pour le développement local uniquement. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(DatabaseSeeder::class);
        $g = Garage::where('slug', 'valognes-automobiles')->first();
        User::firstOrCreate(['email' => 'admin@demo.test'], ['name' => 'Admin démo', 'password' => 'password', 'role' => 'super_admin']);
        User::firstOrCreate(['email' => 'gerant@demo.test'], ['name' => 'Gérant démo', 'password' => 'password', 'role' => 'manager', 'garage_id' => $g->id]);
        User::firstOrCreate(['email' => 'photo@demo.test'], ['name' => 'Photographe démo', 'password' => 'password', 'role' => 'photographer', 'garage_id' => $g->id]);
        $g->update(['phone' => '02 00 00 00 00', 'address' => '1 rue de démonstration', 'hours' => "Lundi au vendredi : 9 h - 18 h\nSamedi : 9 h - 12 h"]);

        $cars = [
            ['GH-512-KL', 'Renault', 'Clio V', '1.0 TCe 90 Evolution', 'essence', 'manuelle', 38200, 13490],
            ['FT-208-AZ', 'Dacia', 'Duster', '1.5 Blue dCi 115 Prestige', 'diesel', 'manuelle', 61000, 16900],
            ['CL-772-ZE', 'Renault', 'Zoe', 'R135 Zen', 'electrique', 'automatique', 41700, 14900],
        ];
        foreach ($cars as $i => [$plate, $make, $model, $version, $fuel, $gearbox, $km, $price]) {
            $v = $g->vehicles()->firstOrCreate(['plate' => $plate], compact('make', 'model', 'version', 'fuel', 'gearbox', 'price') + ['mileage' => $km, 'first_registration' => '2021-03-01', 'doors' => 5, 'seats' => 5]);
            if ($v->photos()->count() === 0) {
                foreach (['front_left', 'front', 'right'] as $k => $key) {
                    $img = imagecreatetruecolor(1600, 1200);
                    imagefill($img, 0, 0, imagecolorallocate($img, 90 + 40 * $i, 120, 140 + 20 * $k));
                    imagefilledrectangle($img, 300, 500, 1300, 900, imagecolorallocate($img, 30, 30, 40));
                    $path = "garages/{$g->id}/vehicles/{$v->id}/photos/demo-{$key}.jpg";
                    Media::put($path, Imaging::jpeg($img));
                    $v->photos()->create(['shot_key' => $key, 'kind' => 'exterior', 'path' => $path, 'position' => $k]);
                }
            }
            $v->refreshStatus();
        }
        $online = Vehicle::where('plate', 'CL-772-ZE')->first();
        $online->update(['status' => 'online', 'published_at' => now()]);
        $g->leads()->firstOrCreate(['name' => 'Client démo'], ['type' => 'vehicle', 'vehicle_id' => $online->id, 'phone' => '06 00 00 00 00', 'message' => 'Est-elle toujours disponible ?', 'status' => 'new', 'source' => 'fiche']);
    }
}
