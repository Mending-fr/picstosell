<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Vehicle\Emissions;
use App\Services\Vehicle\RegistrationReader;
use App\Support\Badges;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhaseOneTest extends TestCase
{
    use RefreshDatabase;

    public function test_critair_et_classe_co2(): void
    {
        $d = fn (string $s) => Carbon::parse($s);
        $this->assertSame('1', Emissions::critair('essence', null, $d('2024-06-26')));
        $this->assertSame('1', Emissions::critair('gpl', null, $d('2005-01-01')));
        $this->assertSame('0', Emissions::critair('electrique', null, null));
        $this->assertSame('2', Emissions::critair('diesel', 'EURO 6D-TEMP', $d('2019-01-01')));
        $this->assertSame('2', Emissions::critair('essence', null, $d('2008-03-01')));
        $this->assertSame('3', Emissions::critair('diesel', null, $d('2008-03-01')));
        $this->assertSame('4', Emissions::critair('diesel', '98/69A', $d('2006-03-01')));
        $this->assertSame('5', Emissions::critair('diesel', null, $d('1998-03-01')));
        $this->assertSame('non_classe', Emissions::critair('essence', null, $d('1995-03-01')));
        $this->assertSame(6, Emissions::euroLevel('715/2007*2018/1832AP EURO6', null));
        $this->assertNull(Emissions::critair('essence', null, null));
        $this->assertSame('B', Emissions::co2Class(108));
        $this->assertSame('A', Emissions::co2Class(100));
        $this->assertSame('G', Emissions::co2Class(251));
    }

    public function test_lecture_carte_grise_nettoie_les_champs(): void
    {
        $f = RegistrationReader::clean([
            'plate' => 'gx553wj', 'first_registration' => '26/06/2024', 'vin' => 'VF1RJA0097283854 9', 'make' => 'RENAULT',
            'commercial_name' => 'CLIO', 'fuel_code' => 'EG', 'power_kw' => '67', 'fiscal_hp' => '5', 'seats' => '5',
            'co2' => '108', 'empty_weight' => '1088', 'body_type' => 'VP', 'euro_norm' => 'EURO6', 'version' => null,
        ]);
        $this->assertSame('GX-553-WJ', $f['plate']);
        $this->assertSame('2024-06-26', $f['first_registration']);
        $this->assertSame('VF1RJA00972838549', $f['vin']);
        $this->assertSame('Renault', $f['make']);
        $this->assertSame('gpl', $f['fuel']);
        $this->assertSame(91, $f['power_hp']);
        $this->assertSame(108, $f['co2']);
        $this->assertSame(1088, $f['empty_weight']);
        $this->assertArrayNotHasKey('version', $f);
    }

    public function test_vignettes_proposees(): void
    {
        $v = new Vehicle(['mileage' => 27400, 'first_registration' => '2022-06-01', 'seats' => 7, 'gearbox' => 'automatique', 'first_hand' => true, 'warranty' => 'OR 12 mois',
            'equipment' => [['group' => 'Confort', 'items' => ['Radar de recul', 'Attelage']]]]);
        $s = Badges::suggest($v);
        foreach (['low_km', 'first_hand', 'towbar', 'seats_7', 'park_assist', 'automatic', 'warranty'] as $k) {
            $this->assertContains($k, $s);
        }
        $v->badges = ['warranty', 'Révision faite'];
        $this->assertSame(['Garantie OR 12 mois', 'Révision faite'], Badges::labels($v));
    }

    public function test_champs_api_et_documents_pdf(): void
    {
        $g = Garage::create(['name' => 'Valognes Automobiles', 'status' => 'pilot', 'settings' => ['doc_footer' => 'Renault Occasions', 'hide_plate' => false]]);
        Sanctum::actingAs(User::create(['name' => 'G', 'email' => 'g@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => $g->id]));
        $h = ['X-Garage-Id' => $g->id];
        $id = $this->postJson('/api/v1/vehicles', ['plate' => 'GX553WJ', 'make' => 'Renault', 'model' => 'Clio 5'], $h)->assertCreated()->json('id');
        $this->patchJson("/api/v1/vehicles/{$id}", [
            'fuel' => 'gpl', 'first_registration' => '2024-06-26', 'co2' => 108, 'empty_weight' => 1088, 'price' => 15500, 'mileage' => 27400,
            'file_number' => 'B-104', 'origin' => 'particulier', 'warranty' => 'OR 12 mois', 'badges' => ['low_km'],
        ], $h)->assertOk()->assertJsonPath('critair_value', '1')->assertJsonPath('co2_class', 'B')->assertJsonPath('badge_labels.0', 'Faible kilométrage');
        $this->patchJson("/api/v1/vehicles/{$id}", ['critair' => '7'], $h)->assertStatus(422);

        foreach (['affiche', 'co2', 'offre'] as $t) {
            $res = $this->get("/api/v1/vehicles/{$id}/documents/{$t}", $h)->assertOk();
            $this->assertStringStartsWith('%PDF', $res->getContent());
        }
        $this->get("/api/v1/vehicles/{$id}/documents/autre", $h)->assertNotFound();
    }

    public function test_sans_cle_ia_la_carte_grise_renvoie_un_message(): void
    {
        config(['services.anthropic.key' => null]);
        $g = Garage::create(['name' => 'G', 'status' => 'pilot']);
        Sanctum::actingAs(User::create(['name' => 'P', 'email' => 'p@a.fr', 'password' => 'x', 'role' => 'photographer', 'garage_id' => $g->id]));
        $img = imagecreatetruecolor(400, 300);
        ob_start();
        imagejpeg($img);
        $file = UploadedFile::fake()->createWithContent('cg.jpg', ob_get_clean());
        $this->post('/api/v1/lookup/registration', ['file' => $file], ['X-Garage-Id' => $g->id, 'Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('message', "La lecture de carte grise n'est pas activée (clé IA manquante).");
    }
}
