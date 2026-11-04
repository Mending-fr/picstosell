<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index()
    {
        return ['data' => $this->garage()->users()->orderBy('name')->get()->map->toApi()->values()];
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:160|unique:users,email',
            'role' => ['required', Rule::in(['manager', 'photographer'])],
        ]);
        $password = Str::password(12, symbols: false);
        $user = User::create($data + ['email' => mb_strtolower($data['email']), 'password' => $password, 'garage_id' => $this->garage()->id]);

        return response()->json(['user' => $user->toApi(), 'temporary_password' => $password], 201);
    }

    public function destroy(Request $request, int $id)
    {
        $user = $this->garage()->users()->findOrFail($id);
        abort_if($user->id === $request->user()->id, 422, 'Vous ne pouvez pas supprimer votre propre compte.');
        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }
}
