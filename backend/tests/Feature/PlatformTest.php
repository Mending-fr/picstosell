<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->seed();
    }

    private function photoFile(): UploadedFile
    {
        $img = imagecreatetruecolor(800, 600);
        imagefill($img, 0, 0, imagecolorallocate($img, 120, 120, 140));
        ob_start();
        imagejpeg($img);

        return UploadedFile::fake()->createWithContent('p.jpg', ob_get_clean());
    }

    public function test_un_gerant_ne_voit_pas_les_voitures_d_un_autre_garage(): void
    {
        $a = Garage::first();
        $b = Garage::create(['name' => 'Autre garage']);
        $other = $b->vehicles()->create(['plate' => 'ZZ-999-ZZ']);
        Sanctum::actingAs(User::create(['name' => 'G', 'email' => 'g@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => $a->id]));

        $this->getJson('/api/v1/vehicles/'.$other->id)->assertNotFound();
        $this->getJson('/api/v1/vehicles')->assertOk()->assertJsonCount(0, 'data');
        // L'en-tête X-Garage-Id est ignoré pour un compte garage.
        $this->getJson('/api/v1/vehicles', ['X-Garage-Id' => $b->id])->assertJsonCount(0, 'data');
    }

    public function test_un_employe_photo_ne_peut_pas_publier(): void
    {
        $g = Garage::first();
        $v = $g->vehicles()->create(['plate' => 'AA-111-AA', 'price' => 1000]);
        Sanctum::actingAs(User::create(['name' => 'P', 'email' => 'p@a.fr', 'password' => 'x', 'role' => 'photographer', 'garage_id' => $g->id]));

        $this->postJson("/api/v1/vehicles/{$v->id}/publish", ['published' => true])->assertForbidden();
        $this->getJson('/api/v1/vehicles')->assertOk();
    }

    public function test_un_super_admin_doit_choisir_un_garage(): void
    {
        Sanctum::actingAs(User::create(['name' => 'M', 'email' => 'm@a.fr', 'password' => 'x', 'role' => 'super_admin']));
        $this->getJson('/api/v1/vehicles')->assertStatus(409);
        $this->getJson('/api/v1/vehicles', ['X-Garage-Id' => Garage::first()->id])->assertOk();
        $this->getJson('/api/v1/admin/overview')->assertOk();
    }

    public function test_parcours_complet_de_la_plaque_a_la_mise_en_ligne(): void
    {
        $g = Garage::first();
        Sanctum::actingAs(User::create(['name' => 'G', 'email' => 'g@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => $g->id]));

        $lookup = $this->getJson('/api/v1/lookup/plate/ab123cd')->assertOk()->json();
        $this->assertSame('AB-123-CD', $lookup['plate']);

        $id = $this->postJson('/api/v1/vehicles', ['plate' => 'ab123cd', 'mileage' => 1000, 'price' => 9990] + $lookup['data'])
            ->assertCreated()->assertJsonPath('plate', 'AB-123-CD')->json('id');

        foreach (['front_left', 'front'] as $key) {
            $this->post("/api/v1/vehicles/{$id}/photos", ['file' => $this->photoFile(), 'kind' => 'exterior', 'shot_key' => $key], ['Accept' => 'application/json'])->assertCreated();
        }
        $this->postJson("/api/v1/vehicles/{$id}/renders")->assertOk()->assertJsonCount(2, 'data');
        $this->assertSame('to_review', Vehicle::find($id)->status);

        $this->postJson("/api/v1/vehicles/{$id}/approve-all")->assertOk()->assertJsonPath('status', 'ready');
        $this->postJson("/api/v1/vehicles/{$id}/publish", ['published' => true])->assertOk()->assertJsonPath('status', 'online');

        $this->get("/s/{$g->slug}/voiture/{$id}")->assertOk()->assertSee('9 990');
        $this->postJson("/api/v1/vehicles/{$id}/sold")->assertOk()->assertJsonPath('status', 'sold');
        $this->get("/s/{$g->slug}/voiture/{$id}")->assertNotFound();
    }

    public function test_le_formulaire_du_site_cree_une_demande(): void
    {
        $g = Garage::first();
        $this->post("/s/{$g->slug}/contact", ['type' => 'trade_in', 'name' => 'Client', 'phone' => '0600000000', 'consent' => '1', 'trade_in_plate' => 'AB-123-CD'])
            ->assertRedirect("/s/{$g->slug}/merci");
        $this->assertDatabaseHas('leads', ['garage_id' => $g->id, 'type' => 'trade_in', 'status' => 'new']);

        $this->post("/s/{$g->slug}/contact", ['type' => 'other', 'name' => 'Sans accord', 'phone' => '06'])->assertSessionHasErrors('consent');
    }

    public function test_normalisation_des_donnees(): void
    {
        $this->assertSame('AB-123-CD', Vehicle::normalizePlate(' ab 123 cd '));
        $this->assertSame('diesel', Vehicle::normalizeFuel('GASOIL'));
        $this->assertSame('hybride', Vehicle::normalizeFuel('MYLD HYBRID'));
        $this->assertSame('hybride', Vehicle::normalizeFuel('HYDRIDE'));
        $this->assertSame('manuelle', Vehicle::normalizeGearbox('MANUEL'));
        $this->assertSame('automatique', Vehicle::normalizeGearbox('EDC'));
    }

    public function test_la_recherche_par_plaque_lit_la_reponse_du_fournisseur(): void
    {
        config(['services.plate.provider' => 'apiplaque', 'services.plate.api_key' => 'test']);
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['data' => [
            'erreur' => '', 'marque' => 'FIAT', 'modele' => 'PANDA', 'sra_commercial' => 'ALESSI 1.2 8V', 'energie' => 1, 'energieNGC' => 'ESSENCE',
            'date1erCir_us' => '2009-06-02', 'boite_vitesse' => 'M', 'puisFisc' => '4', 'puisFiscReel' => '60', 'couleur' => 'JAUNE CLAIR',
            'nb_portes' => 4, 'nr_passagers' => 4, 'vin' => 'ZFA16900001426851',
        ]])]);
        Sanctum::actingAs(User::create(['name' => 'G', 'email' => 'g@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => Garage::first()->id]));

        $this->getJson('/api/v1/lookup/plate/AA123ZE')->assertOk()
            ->assertJsonPath('data.make', 'Fiat')->assertJsonPath('data.fuel', 'essence')
            ->assertJsonPath('data.gearbox', 'manuelle')->assertJsonPath('data.first_registration', '2009-06-02');
        // Deuxième appel : servi par le cache, pas de nouvel appel payant.
        $this->getJson('/api/v1/lookup/plate/AA-123-ZE')->assertOk();
        \Illuminate\Support\Facades\Http::assertSentCount(1);
    }

    public function test_un_garage_en_demo_n_appelle_pas_le_service_de_plaque(): void
    {
        config(['services.plate.provider' => 'apiplaque', 'services.plate.api_key' => 'test']);
        \Illuminate\Support\Facades\Http::fake();
        $g = Garage::create(['name' => 'Garage démo', 'status' => 'demo']);
        $this->assertNotNull($g->fresh()->phone);
        $this->assertSame(3, $g->leads()->count());
        Sanctum::actingAs(User::create(['name' => 'G', 'email' => 'd@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => $g->id]));

        $this->getJson('/api/v1/lookup/plate/WW123AA')->assertOk()->assertJsonPath('found', true)->assertJsonPath('demo', true);
        \Illuminate\Support\Facades\Http::assertNothingSent();
        $this->assertDatabaseMissing('plate_lookups', ['plate' => 'WW123AA']);
        $this->get("/s/{$g->slug}/")->assertOk()->assertSee('noindex', false)->assertSee('Site de démonstration');
    }

    public function test_un_compte_mending_peut_se_designer_pour_les_photos(): void
    {
        $g = Garage::first();
        $admin = User::create(['name' => 'M', 'email' => 'm2@a.fr', 'password' => 'x', 'role' => 'super_admin']);
        Sanctum::actingAs($admin);
        $this->postJson('/api/v1/vehicles', ['plate' => 'AB-321-CD', 'photographer_id' => $admin->id], ['X-Garage-Id' => $g->id])->assertCreated();
        $other = Garage::create(['name' => 'Autre']);
        $stranger = User::create(['name' => 'S', 'email' => 's@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => $other->id]);
        $this->postJson('/api/v1/vehicles', ['plate' => 'AB-322-CD', 'photographer_id' => $stranger->id], ['X-Garage-Id' => $g->id])->assertStatus(422);
    }
}
