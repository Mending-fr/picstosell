<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\Controller;
use App\Models\Invitation;
use App\Models\User;
use App\Services\Team\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Espace Mending : tous les comptes (équipe Mending et garages) et toutes les invitations. */
class UserAdminController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

    public function index()
    {
        return [
            'data' => User::with('garage')->orderByRaw("role = 'super_admin' desc")->orderBy('name')->get()
                ->map(fn (User $u) => $u->toApi() + ['garage_name' => $u->garage?->name, 'last_login_at' => $u->last_login_at?->toIso8601String()])
                ->values(),
            'invitations' => Invitation::pending()->with('garage', 'inviter')->latest()->get()->map->toApi()->values(),
        ];
    }

    public function invite(Request $request)
    {
        $data = $request->validate($this->roleRules() + [
            'name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:160',
        ], ['garage_id.required_unless' => 'Choisissez le garage.']);
        $inv = $this->invitations->create($data, $request->user());

        return response()->json($inv->toApi(), 201);
    }

    public function resend(Invitation $invitation)
    {
        abort_if($invitation->accepted_at !== null, 404);

        return $this->invitations->resend($invitation)->toApi();
    }

    public function revoke(Invitation $invitation)
    {
        abort_if($invitation->accepted_at !== null, 404);
        $invitation->delete();

        return response()->noContent();
    }

    public function update(Request $request, User $user)
    {
        $data = $request->validate($this->roleRules(), ['garage_id.required_unless' => 'Choisissez le garage.']);
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas changer votre propre rôle.');
        $user->update([
            'role' => $data['role'],
            'garage_id' => $data['role'] === 'super_admin' ? null : $data['garage_id'],
        ]);

        return $user->load('garage')->toApi() + ['garage_name' => $user->garage?->name];
    }

    public function destroy(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas supprimer votre propre compte.');
        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }

    private function roleRules(): array
    {
        return [
            'role' => ['required', Rule::in(['super_admin', 'manager', 'photographer'])],
            'garage_id' => 'nullable|required_unless:role,super_admin|integer|exists:garages,id',
        ];
    }
}
