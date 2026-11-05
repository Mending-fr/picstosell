<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BackdropTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function jpeg(int $w = 1200, int $h = 800): UploadedFile
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        imagejpeg($img);

        return UploadedFile::fake()->createWithContent('p.jpg', ob_get_clean());
    }

    public function test_mending_ajoute_des_decors_et_le_premier_devient_le_defaut(): void
    {
        Sanctum::actingAs(User::create(['name' => 'M', 'email' => 'm@a.fr', 'password' => 'x', 'role' => 'super_admin']));
        $a = $this->post('/api/v1/admin/backdrops', ['name' => 'Studio clair', 'file' => $this->jpeg()])->assertCreated()->assertJsonPath('is_default', true)->json('id');
        $b = $this->post('/api/v1/admin/backdrops', ['name' => 'Atelier', 'file' => $this->jpeg()])->assertCreated()->assertJsonPath('is_default', false)->json('id');
        $this->patchJson("/api/v1/admin/backdrops/{$b}", ['is_default' => true])->assertOk();
        $this->getJson('/api/v1/admin/backdrops')->assertJsonPath('data.0.id', $b)->assertJsonPath('data.1.is_default', false);
        $this->deleteJson("/api/v1/admin/backdrops/{$b}")->assertNoContent();
        $this->getJson('/api/v1/admin/backdrops')->assertJsonPath('data.0.id', $a)->assertJsonPath('data.0.is_default', true);
    }

    public function test_un_garage_choisit_un_decor_et_fait_un_essai_sans_toucher_a_la_voiture(): void
    {
        Sanctum::actingAs(User::create(['name' => 'M', 'email' => 'm@a.fr', 'password' => 'x', 'role' => 'super_admin']));
        $lib = $this->post('/api/v1/admin/backdrops', ['name' => 'Studio clair', 'file' => $this->jpeg()])->json('id');

        $g = Garage::create(['name' => 'Garage Test', 'status' => 'active']);
        Sanctum::actingAs(User::create(['name' => 'G', 'email' => 'g@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => $g->id]));
        // Sans photo du garage, le décor Mending par défaut est utilisé.
        $this->getJson('/api/v1/backdrop')->assertOk()->assertJsonPath('selected', "lib:{$lib}")->assertJsonPath('library.0.name', 'Studio clair');
        $this->putJson('/api/v1/backdrop', ['backdrop' => 'own'])->assertOk()->assertJsonPath('chosen', 'own');
        $this->putJson('/api/v1/backdrop', ['backdrop' => 'lib:999'])->assertStatus(422);

        $v = $g->vehicles()->create(['plate' => 'AA-111-AA']);
        $photo = $this->post("/api/v1/vehicles/{$v->id}/photos", ['file' => $this->jpeg(800, 600), 'kind' => 'exterior', 'shot_key' => 'front_left'])->json('id');
        $this->getJson('/api/v1/backdrop/photos')->assertJsonPath('data.0.id', $photo);

        $id = $this->postJson('/api/v1/backdrop/test', ['photo_id' => $photo, 'backdrop' => "lib:{$lib}"])->assertCreated()->assertJsonPath('is_test', true)->json('id');
        $this->getJson("/api/v1/backdrop/test/{$id}")->assertOk()->assertJsonPath('backdrop', "lib:{$lib}");
        // L'essai n'apparaît pas dans les rendus de la voiture.
        $this->getJson("/api/v1/vehicles/{$v->id}")->assertJsonCount(0, 'renders');
    }
}
