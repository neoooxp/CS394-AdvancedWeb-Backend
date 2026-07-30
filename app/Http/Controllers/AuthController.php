<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Authenticate user and return token and role.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string',
            'password' => 'required|string',
            'portal' => 'nullable|string',
            'role' => 'nullable|string',
        ]);

        $loginInput = $request->email;

        $user = User::where('email', $loginInput)
            ->orWhere('username', $loginInput)
            ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password.'
            ], 401);
        }

        if (!$user->status) {
            return response()->json([
                'message' => 'Your account has been disabled.'
            ], 403);
        }

        // Validate target portal / role access
        $targetPortal = strtolower($request->input('portal', $request->input('role', '')));

        if ($targetPortal === 'admin') {
            if ($user->role !== 'admin') {
                return response()->json([
                    'message' => 'Access denied. Only administrators can log in to the admin portal.'
                ], 403);
            }
        } elseif ($targetPortal === 'driver') {
            if ($user->role !== 'driver') {
                return response()->json([
                    'message' => 'Access denied. Admins and non-driver users cannot log in to the driver portal.'
                ], 403);
            }
        } elseif (!empty($targetPortal) && $user->role !== $targetPortal) {
            return response()->json([
                'message' => "Access denied. Your account role ({$user->role}) is not authorized for this portal."
            ], 403);
        }

        // Update last login timestamp
        $user->update([
            'last_login' => now()
        ]);

        // Generate Sanctum token
        $token = $user->createToken('auth_token')->plainTextToken;

        $user->load(['guardian', 'driver']);

        return response()->json([
            'token' => $token,
            'role' => $user->role,
            'user' => $user
        ]);
    }

    /**
     * Revoke current user session token.
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $user->currentAccessToken()->delete();
        }

        return response()->json([
            'message' => 'Logged out successfully.'
        ]);
    }
}
