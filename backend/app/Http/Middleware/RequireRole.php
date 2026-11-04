<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireRole
{
    public function handle(Request $request, Closure $next, string $level): Response
    {
        $user = $request->user();
        $ok = match ($level) {
            'super' => $user->isSuperAdmin(),
            'manager' => $user->isManager(),
            default => false,
        };

        return $ok ? $next($request) : response()->json(['message' => "Vous n'avez pas accès à cette action"], 403);
    }
}
