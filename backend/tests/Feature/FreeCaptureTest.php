<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\User;
use App\Services\PhotoClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FreeCaptureTest extends TestCase
{
    use RefreshDatabase;

    private Garage $g;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->g = Garage::create(['name' => 'Garage Test', 'status' => 'active']);
        Sanctum::actingAs(User::create(['name' => 'G', 'email' => 'g@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => $this->g->id]));
    }

    private function photo(): UploadedFile
    {
        $img = imagecreatetruecolor(800, 600);
        ob_start();
        imagejpeg($img);

        return UploadedFile::fake()->createWithContent('p.jpg', ob_get_clean());
    }

    private function classifierReturns(?array $result): void
    {
        $this->app->instance(PhotoClassifier::class, new class($result) extends PhotoClassifier
        {
            public function __construct(private ?array $r) {}

            public function classify(string $jpegBytes): ?array
            {
                return $this->r;
            }
        });
    }

    public function test_un_seul_decor_par_showroom(): void
    {
        $shots = $this->getJson('/api/v1/showrooms')->assertOk()->json('data.0.shots');
        $this->assertCount(1, $shots);
        $this->assertSame('main', $shots[0]['key']);
        $this->assertSame(['height' => 42, 'bottom' => 12, 'offset_x' => 0], $shots[0]['placement']);
    }

    public function test_photo_libre_rangee_par_l_ia(): void
    {
        $v = $this->g->vehicles()->create(['plate' => 'AA-111-AA']);
        $this->classifierReturns(['kind' => 'interior', 'view' => null, 'label' => 'Compteur']);
        $this->post("/api/v1/vehicles/{$v->id}/photos", ['file' => $this->photo(), 'kind' => 'auto'])
            ->assertCreated()->assertJsonPath('kind', 'interior')->assertJsonPath('display_label', 'Compteur')->assertJsonPath('classified', 'ai');

        $this->classifierReturns(['kind' => 'exterior', 'view' => 'rear', 'label' => null]);
        $this->post("/api/v1/vehicles/{$v->id}/photos", ['file' => $this->photo(), 'kind' => 'auto'])->assertCreated()->assertJsonPath('shot_key', 'rear');
        // Deux photos du même angle en prise libre : aucune n'écrase l'autre.
        $this->post("/api/v1/vehicles/{$v->id}/photos", ['file' => $this->photo(), 'kind' => 'auto'])->assertCreated();
        $this->assertSame(3, $v->photos()->count());
        // L'extérieur passe avant l'intérieur sur l'annonce.
        $this->assertSame('exterior', $v->photos()->first()->kind);
    }

    public function test_sans_ia_la_photo_est_supposee_exterieure_et_se_corrige(): void
    {
        $v = $this->g->vehicles()->create(['plate' => 'AA-111-AA']);
        $this->classifierReturns(null);
        $id = $this->post("/api/v1/vehicles/{$v->id}/photos", ['file' => $this->photo(), 'kind' => 'auto'])
            ->assertCreated()->assertJsonPath('kind', 'exterior')->assertJsonPath('classified', null)->json('id');

        $this->patchJson("/api/v1/photos/{$id}", ['kind' => 'detail', 'label' => 'Jante'])
            ->assertOk()->assertJsonPath('kind', 'detail')->assertJsonPath('display_label', 'Jante')->assertJsonPath('classified', 'user');
    }
}
