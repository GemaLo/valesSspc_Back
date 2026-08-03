<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials)) {
            /** @var \App\Models\User $user */
            $user = Auth::user();

            if (isset($user->active) && !$user->active) {
                return response()->json([
                    'status' => false,
                    'message' => 'Usuario inactivo'
                ], 403);
            }

            $user->load('typeUser');

            $token = $user->createToken('auth_token')->plainTextToken;

            return response()->json([
                'status' => true,
                'message' => 'Login exitoso',
                'access_token' => $token,
                'token_type' => 'Bearer',
                'data' => [
                    'user' => $user
                ]
            ], 200);
        }

        return response()->json([
            'status' => false,
            'message' => 'Credenciales incorrectas'
        ], 401);
    }
    public function logout(Request $request)
    {
        Auth::logout();

        return response()->json([
            'status' => true,
            'message' => 'Sesión cerrada correctamente'
        ], 200);
    }
}