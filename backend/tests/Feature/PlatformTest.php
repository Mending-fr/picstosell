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
}
