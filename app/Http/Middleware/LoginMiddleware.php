<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LoginMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        // Revisa cómo está extrayendo el token aquí:
        $token = $request->bearerToken(); 

        if (!$token) {
            return response()->json([
                'status' => false,
                'message' => "No autorizado. Debes iniciar sesión."
            ], 401);
        }

        // Aquí es donde busca el token en la BD...
        // Ejemplo: $user = User::where('api_token', $token)->first();

        return $next($request);
    }
}