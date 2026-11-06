<?php

namespace Tests\Feature;

use App\Mail\InvitationMail;
use App\Models\Garage;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InvitationTest extends TestCase
{
    use RefreshDatabase;

    private function garage(): Garage
    {
        return Garage::create(['name' => 'Valognes Auto', 'slug' => 'valognes', 'status' => 'pilot']);
    }

    public function test_le_gerant_invite_par_email_et_la_personne_cree_son_acces(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();
        $g = $this->garage();
        $boss = User::create(['name' => 'Gérant', 'email' => 'g@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => $g->id]);
        Sanctum::actingAs($boss);

        $inv = $this->postJson('/api/v1/invitations', ['name' => 'Paul', 'email' => 'Paul@Garage.fr', 'role' => 'photographer'], ['X-Garage-Id' => $g->id])
            ->assertCreated()->assertJsonPath('email', 'paul@garage.fr')->assertJsonPath('role_label', 'Photographe')->json();
        $this->assertNotNull($inv['emailed_at']);
        Mail::assertSent(InvitationMail::class, fn ($m) => $m->hasTo('paul@garage.fr'));
        $this->getJson('/api/v1/users', ['X-Garage-Id' => $g->id])->assertJsonCount(1, 'invitations');

        $token = basename($inv['link']);
        $this->getJson("/api/v1/invitations/{$token}")->assertOk()->assertJsonPath('garage_name', 'Valognes Auto')->assertJsonPath('email', 'paul@garage.fr');
        $this->postJson("/api/v1/invitations/{$token}/accept", ['name' => 'Paul Martin', 'password' => 'court'])->assertStatus(422);
        $this->postJson("/api/v1/invitations/{$token}/accept", ['name' => 'Paul Martin', 'password' => 'motdepasse1'])
            ->assertCreated()->assertJsonPath('user.role', 'photographer')->assertJsonPath('user.garage_id', $g->id)->assertJsonStructure(['token']);
        $this->postJson("/api/v1/invitations/{$token}/accept", ['name' => 'Paul', 'password' => 'motdepasse1'])->assertStatus(410);
        $this->postJson('/api/v1/auth/login', ['email' => 'paul@garage.fr', 'password' => 'motdepasse1'])->assertOk();
    }

    public function test_un_simple_lien_sans_email_et_les_regles_du_gerant(): void
    {
        Mail::fake();
        $g = $this->garage();
        $boss = User::create(['name' => 'Gérant', 'email' => 'g@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => $g->id]);
        Sanctum::actingAs($boss);
        $h = ['X-Garage-Id' => $g->id];

        $this->postJson('/api/v1/invitations', ['role' => 'super_admin'], $h)->assertStatus(422);
        $this->postJson('/api/v1/invitations', ['email' => 'g@a.fr', 'role' => 'manager'], $h)->assertStatus(422)->assertJsonValidationErrors('email');

        $inv = $this->postJson('/api/v1/invitations', ['role' => 'manager'], $h)->assertCreated()->json();
        $this->assertNull($inv['emailed_at']);
        Mail::assertNothingSent();
        $token = basename($inv['link']);
        $this->postJson("/api/v1/invitations/{$token}/accept", ['name' => 'Julie', 'password' => 'motdepasse1'])->assertStatus(422)->assertJsonValidationErrors('email');

        // Renvoyer change le lien : l'ancien ne marche plus.
        $new = $this->postJson("/api/v1/invitations/{$inv['id']}/resend", [], $h)->assertOk()->json('link');
        $this->getJson("/api/v1/invitations/{$token}")->assertNotFound();
        $this->postJson('/api/v1/invitations/'.basename($new).'/accept', ['name' => 'Julie', 'email' => 'julie@a.fr', 'password' => 'motdepasse1'])
            ->assertCreated()->assertJsonPath('user.role', 'manager');

        $julie = User::where('email', 'julie@a.fr')->first();
        $this->patchJson("/api/v1/users/{$julie->id}", ['role' => 'photographer'], $h)->assertOk()->assertJsonPath('role', 'photographer');
        $this->patchJson("/api/v1/users/{$boss->id}", ['role' => 'photographer'], $h)->assertStatus(422);

        $other = $this->postJson('/api/v1/invitations', ['email' => 'x@a.fr', 'role' => 'photographer'], $h)->json('id');
        $this->deleteJson("/api/v1/invitations/{$other}", [], $h)->assertNoContent();
        $this->assertSame(0, Invitation::pending()->count());
    }

    public function test_sans_serveur_d_envoi_on_ne_pretend_pas_avoir_envoye_l_email(): void
    {
        config(['mail.default' => 'log']);
        $g = $this->garage();
        Sanctum::actingAs(User::create(['name' => 'G', 'email' => 'g@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => $g->id]));
        $this->postJson('/api/v1/invitations', ['email' => 'p@a.fr', 'role' => 'photographer'], ['X-Garage-Id' => $g->id])
            ->assertCreated()->assertJsonPath('emailed_at', null);
    }

    public function test_une_invitation_expiree_ne_sert_plus(): void
    {
        $g = $this->garage();
        Invitation::create(['token' => 'vieux', 'role' => 'manager', 'garage_id' => $g->id, 'expires_at' => now()->subDay()]);
        $this->getJson('/api/v1/invitations/vieux')->assertOk()->assertJsonPath('expired', true);
        $this->postJson('/api/v1/invitations/vieux/accept', ['name' => 'A', 'email' => 'a@a.fr', 'password' => 'motdepasse1'])->assertStatus(410);
    }

    public function test_mending_invite_un_admin_et_gere_tous_les_comptes(): void
    {
        Mail::fake();
        $g = $this->garage();
        $me = User::create(['name' => 'Romain', 'email' => 'r@m.fr', 'password' => 'x', 'role' => 'super_admin']);
        $staff = User::create(['name' => 'Paul', 'email' => 'p@a.fr', 'password' => 'x', 'role' => 'photographer', 'garage_id' => $g->id]);
        Sanctum::actingAs($me);

        $this->postJson('/api/v1/admin/invitations', ['email' => 'maxime@m.fr', 'role' => 'manager'])->assertStatus(422)->assertJsonValidationErrors('garage_id');
        $inv = $this->postJson('/api/v1/admin/invitations', ['email' => 'maxime@m.fr', 'role' => 'super_admin', 'garage_id' => $g->id])
            ->assertCreated()->assertJsonPath('garage_id', null)->assertJsonPath('role_label', 'Admin Mending')->json();
        $this->getJson('/api/v1/admin/users')->assertJsonCount(2, 'data')->assertJsonPath('data.0.role', 'super_admin')->assertJsonCount(1, 'invitations');

        $this->patchJson("/api/v1/admin/users/{$staff->id}", ['role' => 'manager', 'garage_id' => $g->id])->assertOk()->assertJsonPath('role', 'manager');
        $this->patchJson("/api/v1/admin/users/{$me->id}", ['role' => 'manager', 'garage_id' => $g->id])->assertStatus(422);
        $this->deleteJson("/api/v1/admin/users/{$staff->id}")->assertNoContent();
        $this->deleteJson("/api/v1/admin/invitations/{$inv['id']}")->assertNoContent();

        Sanctum::actingAs(User::create(['name' => 'G', 'email' => 'g@a.fr', 'password' => 'x', 'role' => 'manager', 'garage_id' => $g->id]));
        $this->getJson('/api/v1/admin/users')->assertForbidden();
    }
}
