<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        // 1. Validar campos
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // 2. Buscar al usuario
        $user = User::where('email', $request->email)->first();

        // 3. Verificar contraseña
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        // 4. Eliminar tokens anteriores (opcional, para mantener uno activo por sesión)
        $user->tokens()->delete();

        // 5. Crear el nuevo token con Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        // 6. Retornar la respuesta con el token completo (incluyendo el prefijo numérico y la pleca)
        return response()->json([
            'status' => true,
            'message' => 'Bienvenido',
            'access_token' => $token,
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        // Revocar el token actual del usuario autenticado
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Sesión cerrada correctamente'
        ]);
    }
}