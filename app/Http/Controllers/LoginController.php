<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => false,
                'message' => 'Credenciales incorrectas'
            ], 401);
        }

        $isActive = (int) ($user->active ?? $user->getAttribute('ACTIVE') ?? 1);

        if ($isActive === 0) {
            return response()->json([
                'status' => false,
                'message' => 'Tu cuenta está inactiva. Contacta al administrador.'
            ], 403);
        }

        $mustChange = (int) ($user->must_change_password ?? $user->getAttribute('MUST_CHANGE_PASSWORD') ?? 1);
        if ($mustChange === 0) {
            $tempToken = $user->createToken('temp_password_token')->plainTextToken;

            return response()->json([
                'status' => true,
                'require_password_change' => true,
                'message' => 'Es necesario que cambies tu contraseña.',
                'access_token' => $tempToken,
                'user' => $user
            ], 200);
        }
        $user->tokens()->delete();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'status' => true,
            'message' => 'Bienvenido',
            'access_token' => $token,
            'user' => $user
        ]);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|min:6',
        ]);

        $user = $request->user();

        $user->password = Hash::make($request->password);
        $user->must_change_password = 1;
        $user->save();

        return response()->json([
            'status' => true,
            'message' => 'Contraseña actualizada correctamente',
            'user' => $user
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => true,
            'message' => 'Sesión cerrada correctamente'
        ]);
    }
}
