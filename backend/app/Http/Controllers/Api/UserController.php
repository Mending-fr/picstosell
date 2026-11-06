<?php

namespace App\Http\Controllers\Api;

use App\Models\Invitation;
use App\Services\Team\InvitationService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** L'équipe d'un garage : ses membres et ses invitations en attente. Réservé au gérant. */
class UserController extends Controller
{
    public function __construct(private InvitationService $invitations) {}

    public function index()
    {
        $g = $this->garage();

        return [
            'data' => $g->users()->orderBy('name')->get()->map->toApi()->values(),
            'invitations' => Invitation::pending()->where('garage_id', $g->id)->with('garage', 'inviter')->latest()->get()->map->toApi()->values(),
        ];
    }

    public function update(Request $request, int $id)
    {
        $user = $this->garage()->users()->findOrFail($id);
        $data = $request->validate(['role' => ['required', Rule::in(['manager', 'photographer'])]]);
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas changer votre propre rôle.');
        $user->update($data);

        return $user->toApi();
    }

    public function destroy(Request $request, int $id)
    {
        $user = $this->garage()->users()->findOrFail($id);
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas supprimer votre propre compte.');
        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }

    public function invite(Request $request)
    {
        $data = $request->validate([
            'name' => 'nullable|string|max:120',
            'email' => 'nullable|email|max:160',
            'role' => ['required', Rule::in(['manager', 'photographer'])],
        ]);
        $inv = $this->invitations->create($data + ['garage_id' => $this->garage()->id], $request->user());

        return response()->json($inv->toApi(), 201);
    }

    public function resend(int $id)
    {
        return $this->invitations->resend($this->pending($id))->toApi();
    }

    public function revoke(int $id)
    {
        $this->pending($id)->delete();

        return response()->noContent();
    }

    private function pending(int $id): Invitation
    {
        return Invitation::pending()->where('garage_id', $this->garage()->id)->findOrFail($id);
    }
}
