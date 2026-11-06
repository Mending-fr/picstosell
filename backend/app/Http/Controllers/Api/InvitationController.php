<?php

namespace App\Http\Controllers\Api;

use App\Services\Team\InvitationService;
use Illuminate\Http\Request;

/** Pages publiques de l'invitation : voir à quoi elle donne accès, puis créer son compte. */
class InvitationController extends Controller
{
    public function __construct(private InvitationService $service) {}

    public function show(string $token)
    {
        $inv = $this->service->findUsable($token);

        return [
            'name' => $inv->name,
            'email' => $inv->email,
            'role' => $inv->role,
            'role_label' => $inv->roleLabel(),
            'garage_name' => $inv->garage?->name,
            'expired' => $inv->isExpired(),
            'accepted' => $inv->accepted_at !== null,
        ];
    }

    public function accept(Request $request, string $token)
    {
        $inv = $this->service->findUsable($token);
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'nullable|email|max:160',
            'password' => 'required|string|min:8|max:200',
            'device_name' => 'nullable|string|max:100',
        ], [
            'password.min' => 'Le mot de passe doit faire au moins 8 caractères.',
            'name.required' => 'Indiquez votre prénom et votre nom.',
        ]);
        $user = $this->service->accept($inv, $data);

        return response()->json([
            'token' => $user->createToken($data['device_name'] ?? 'web')->plainTextToken,
            'user' => $user->toApi(),
        ], 201);
    }
}
