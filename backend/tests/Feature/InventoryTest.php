<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\User;
use App\Services\Vehicle\PlateReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    private function jpeg(): UploadedFile
    {
        $img = imagecreatetruecolor(800, 600);
        ob_start();
        imagejpeg($img);

        return UploadedFile::fake()->createWithContent('f.jpg', ob_get_clean());
    }

    /** Le lecteur de plaques renvoie, image après image, les lectures prévues. */
    private function fakeReader(array $sequence): void
    {
        $this->app->instance(PlateReader::class, new class($sequence) extends PlateReader
        {
            public function __construct(private array $seq) {}

            public function read(string $jpegBytes): array
            {
                return array_shift($this->seq) ?? [];
            }
        });
    }

    public function test_normalisation_et_lectures_proches(): void
    {
        $this->assertSame('GX-553-WJ', PlateReader::normalize('gx 553 wj'));
        $this->assertSame('1234 AB 50', PlateReader::normalize('1234-AB-50'));
        $this->assertNull(PlateReader::normalize('RENAULT'));
        $this->assertTrue(PlateReader::similar('GX-553-WJ', 'GX-SS3-WJ'));
        $this->assertTrue(PlateReader::similar('AB-123-CD', 'AB-128-CD'));
        $this->assertFalse(PlateReader::similar('AB-123-CD', 'AB-456-CD'));
    }

    public function test_un_tour_du_parc_donne_les_trois_listes(): void
    {
        Storage::fake('local');
        $g = Garage::create(['name' => 'Valognes', 'status' => 'pilot']);
        $staff = User::create(['name' => 'Paul', 'email' => 'p@a.fr', 'password' => 'x', 'role' => 'photographer', 'garage_id' => $g->id]);
        $clio = $g->vehicles()->create(['plate' => 'GX-553-WJ', 'make' => 'Renault', 'model' => 'Clio', 'status' => 'online']);
        $zoe = $g->vehicles()->create(['plate' => 'CL-772-ZE', 'make' => 'Renault', 'model' => 'Zoe', 'status' => 'to_photograph']);
        $g->vehicles()->create(['plate' => 'AA-111-AA', 'make' => 'Peugeot', 'model' => '208', 'status' => 'sold']);
        Sanctum::actingAs($staff);
        $h = ['X-Garage-Id' => $g->id, 'Accept' => 'application/json'];

        $this->fakeReader([
            [['plate' => 'GX-553-WJ', 'confidence' => 'high']],
            [['plate' => 'GX-5S3-WJ', 'confidence' => 'low'], ['plate' => 'FT-208-AZ', 'confidence' => 'high']],
            [['plate' => 'ZZ-999-ZZ', 'confidence' => 'low']],
        ]);
        $id = $this->postJson('/api/v1/inventories', [], $h)->assertCreated()->assertJsonPath('status', 'running')->json('id');
        foreach (range(1, 3) as $_) {
            $this->post("/api/v1/inventories/{$id}/frames", ['file' => $this->jpeg()], $h)->assertCreated();
        }

        $res = $this->postJson("/api/v1/inventories/{$id}/finish", [], $h)->assertOk()
            ->assertJsonPath('status', 'done')->assertJsonPath('frames', 3)
            ->assertJsonPath('found', 1)->assertJsonPath('unknown', 2)->assertJsonPath('missing', 1);
        $this->assertSame('GX-553-WJ', $res->json('found_list.0.plate'));
        $this->assertSame(2, $res->json('found_list.0.seen'));
        $this->assertSame($clio->id, $res->json('found_list.0.vehicle.id'));
        $this->assertSame($zoe->id, $res->json('missing_list.0.id'));
        $this->assertSame(['FT-208-AZ', 'ZZ-999-ZZ'], array_column($res->json('unknown_list'), 'plate'));

        // Une plaque mal lue s'écarte ; une autre se rattache à la main à une voiture du parc.
        $bad = $res->json('unknown_list.1.id');
        $this->patchJson("/api/v1/inventories/{$id}/plates/{$bad}", ['dismissed' => true], $h)->assertOk()->assertJsonPath('unknown', 1);
        $ft = $res->json('unknown_list.0.id');
        $this->patchJson("/api/v1/inventories/{$id}/plates/{$ft}", ['vehicle_id' => $zoe->id], $h)->assertOk()->assertJsonPath('missing', 0)->assertJsonPath('found', 2);

        $this->post("/api/v1/inventories/{$id}/frames", ['file' => $this->jpeg()], $h)->assertStatus(422);
        $this->getJson('/api/v1/inventories', $h)->assertJsonPath('data.0.id', $id)->assertJsonPath('data.0.user', 'Paul');
    }

    public function test_un_autre_garage_ne_voit_pas_l_inventaire(): void
    {
        $a = Garage::create(['name' => 'A', 'status' => 'pilot']);
        $b = Garage::create(['name' => 'B', 'status' => 'pilot']);
        Sanctum::actingAs(User::create(['name' => 'A', 'email' => 'a@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => $a->id]));
        $id = $this->postJson('/api/v1/inventories', [], ['X-Garage-Id' => $a->id])->json('id');
        Sanctum::actingAs(User::create(['name' => 'B', 'email' => 'b@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => $b->id]));
        $this->getJson("/api/v1/inventories/{$id}", ['X-Garage-Id' => $b->id])->assertNotFound();
    }
}
