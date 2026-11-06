<?php

namespace App\Services\Team;

use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Création, renvoi et acceptation des invitations. Utilisé côté garage (son équipe) et côté Mending (tout le monde).
 */
class InvitationService
{
    /**
     * @param  array{name?: ?string, email?: ?string, role: string, garage_id?: ?int}  $data
     */
    public function create(array $data, User $by): Invitation
    {
        $email = isset($data['email']) && trim((string) $data['email']) !== '' ? mb_strtolower(trim($data['email'])) : null;
        if ($email && User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Un compte existe déjà avec cette adresse.']);
        }
        // Une nouvelle invitation pour la même adresse remplace l'ancienne.
        if ($email) {
            Invitation::pending()->where('email', $email)->delete();
        }

        $inv = Invitation::create([
            'token' => Str::random(48),
            'email' => $email,
            'name' => isset($data['name']) && trim((string) $data['name']) !== '' ? trim($data['name']) : null,
            'role' => $data['role'],
            'garage_id' => $data['role'] === 'super_admin' ? null : ($data['garage_id'] ?? null),
            'invited_by' => $by->id,
            'expires_at' => now()->addDays(Invitation::DAYS),
        ]);
        $this->send($inv);

        return $inv->load('garage', 'inviter');
    }

    /** Nouveau lien (l'ancien ne marche plus), nouvelle durée, et nouvel e-mail s'il y a une adresse. */
    public function resend(Invitation $inv): Invitation
    {
        $inv->update(['token' => Str::random(48), 'expires_at' => now()->addDays(Invitation::DAYS)]);
        $this->send($inv);

        return $inv->load('garage', 'inviter');
    }

    private function send(Invitation $inv): void
    {
        if (! $inv->email) {
            return;
        }
        try {
            Mail::to($inv->email)->send(new InvitationMail($inv->load('garage', 'inviter')));
            // Avec le pilote « log » ou « array », rien ne part vraiment : on ne dit pas « e-mail envoyé ».
            if (! in_array(config('mail.default'), ['log', 'array'], true)) {
                $inv->forceFill(['emailed_at' => now()])->save();
            }
        } catch (\Throwable $e) {
            // L'e-mail peut échouer (serveur d'envoi pas réglé) : le lien reste utilisable, on le signale dans la réponse.
            Log::warning('Invitation : e-mail non envoyé', ['invitation' => $inv->id, 'error' => $e->getMessage()]);
        }
    }

    public function findUsable(string $token): Invitation
    {
        $inv = Invitation::with('garage')->where('token', $token)->first();
        abort_if(! $inv, 404, "Cette invitation n'existe pas ou a été remplacée par une plus récente.");

        return $inv;
    }

    /**
     * @param  array{name: string, email?: ?string, password: string}  $data
     */
    public function accept(Invitation $inv, array $data): User
    {
        abort_if($inv->accepted_at !== null, 410, 'Cette invitation a déjà servi. Connectez-vous avec votre adresse.');
        abort_if($inv->isExpired(), 410, "Cette invitation a expiré. Demandez à la personne qui vous a invité de vous en renvoyer une.");
        if ($inv->role !== 'super_admin') {
            abort_if(! $inv->garage, 410, "Le garage de cette invitation n'existe plus.");
        }

        $email = $inv->email ?? mb_strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Indiquez une adresse e-mail valide.']);
        }
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'Un compte existe déjà avec cette adresse. Connectez-vous.']);
        }

        return DB::transaction(function () use ($inv, $data, $email) {
            $user = User::create([
                'name' => trim($data['name']),
                'email' => $email,
                'password' => $data['password'],
                'role' => $inv->role,
                'garage_id' => $inv->role === 'super_admin' ? null : $inv->garage_id,
            ]);
            $user->forceFill(['email_verified_at' => $inv->email ? now() : null, 'last_login_at' => now()])->save();
            $inv->update(['accepted_at' => now()]);

            return $user;
        });
    }
}
