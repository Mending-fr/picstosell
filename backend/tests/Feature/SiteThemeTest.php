<?php

namespace Tests\Feature;

use App\Models\Garage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SiteThemeTest extends TestCase
{
    use RefreshDatabase;

    private function super(): void
    {
        Sanctum::actingAs(User::create(['name' => 'M', 'email' => 'm@a.fr', 'password' => 'x', 'role' => 'super_admin']));
    }

    public function test_le_site_utilise_classique_par_defaut_puis_le_theme_choisi(): void
    {
        $g = Garage::create(['name' => 'Garage Test', 'status' => 'active']);
        $this->get("/s/{$g->slug}")->assertOk()->assertSee('Nos occasions')->assertDontSee('Fraunces');

        $this->super();
        $this->putJson("/api/v1/admin/garages/{$g->id}/site-theme", [
            'theme' => 'prestige',
            'options' => ['google_rating' => '4,7', 'warranty_months' => 12, 'inconnu' => 'x'],
        ])->assertOk()->assertJsonPath('current', 'prestige');

        $g->refresh();
        $this->assertSame(4.7, $g->settings['theme_options']['prestige']['google_rating']);
        $this->assertArrayNotHasKey('inconnu', $g->settings['theme_options']['prestige']);
        $this->get("/s/{$g->slug}")->assertOk()->assertSee('Fraunces')->assertSee('12 mois');
        // Les pages que Prestige ne réécrit pas viennent de Classique, dans la mise en page Prestige.
        $this->get("/s/{$g->slug}/contact")->assertOk()->assertSee('Nous contacter')->assertSee('Fraunces');
    }

    public function test_un_theme_inconnu_ou_reserve_est_refuse(): void
    {
        $g = Garage::create(['name' => 'Garage Test', 'status' => 'active']);
        $this->super();
        $this->putJson("/api/v1/admin/garages/{$g->id}/site-theme", ['theme' => 'nexiste-pas'])->assertStatus(422);
        $this->getJson("/api/v1/admin/garages/{$g->id}/site-theme")->assertOk()
            ->assertJsonPath('current', 'classique')
            ->assertJsonFragment(['key' => 'prestige']);
    }

    public function test_l_apercu_signe_change_le_theme_pour_la_session_seulement(): void
    {
        $g = Garage::create(['name' => 'Garage Test', 'status' => 'active']);
        $url = URL::temporarySignedRoute('site.index', now()->addHour(), ['slug' => $g->slug, 'theme' => 'prestige']);
        $this->get($url)->assertOk()->assertSee('Aperçu du thème')->assertSee('Fraunces');
        // Sans signature, le paramètre est ignoré.
        $this->flushSession();
        $this->get("/s/{$g->slug}?theme=prestige")->assertOk()->assertDontSee('Fraunces');
    }

    public function test_un_gerant_ne_peut_pas_changer_le_theme(): void
    {
        $g = Garage::create(['name' => 'Garage Test', 'status' => 'active']);
        Sanctum::actingAs(User::create(['name' => 'G', 'email' => 'g@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => $g->id]));
        $this->putJson("/api/v1/admin/garages/{$g->id}/site-theme", ['theme' => 'prestige'])->assertForbidden();
    }
}
