<?php

namespace App\Http\Controllers\Api;

use App\Models\Garage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'device_name' => 'nullable|string|max:100',
        ]);
        $user = User::where('email', mb_strtolower($data['email']))->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'E-mail ou mot de passe incorrect.']);
        }
        $user->forceFill(['last_login_at' => now()])->save();
        $token = $user->createToken($data['device_name'] ?? 'web')->plainTextToken;

        return ['token' => $token, 'user' => $user->toApi()];
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function me(Request $request)
    {
        $user = $request->user();
        $garages = $user->isSuperAdmin()
            ? Garage::orderBy('name')->get()
            : collect([$user->garage])->filter();

        return [
            'user' => $user->toApi(),
            'garage' => $user->garage?->toApi(),
            'garages' => $garages->map->summary()->values(),
        ];
    }
}
