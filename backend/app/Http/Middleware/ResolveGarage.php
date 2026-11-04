<?php

namespace App\Http\Middleware;

use App\Models\Garage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Détermine le dossier client (garage) de la requête. */
class ResolveGarage
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user->isSuperAdmin()) {
            $id = $request->header('X-Garage-Id');
            $garage = $id ? Garage::find($id) : null;
            if (! $garage) {
                return response()->json(['message' => 'Choisissez un garage'], 409);
            }
        } else {
            $garage = $user->garage;
            if (! $garage) {
                return response()->json(['message' => 'Aucun garage associé à ce compte'], 403);
            }
        }
        app()->instance('garage', $garage);

        return $next($request);
    }
}
