<?php

namespace App\Http\Controllers;

use App\Models\PasswordReset;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    /**
     * Log a reset token and dispatch a recovery link.
     */
    public function sendResetLink(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $user = User::where('email', $request->email)->first();

        // Delete any existing reset tokens for this user
        PasswordReset::where('user_id', $user->user_id)->delete();

        $token = Str::random(64);

        PasswordReset::create([
            'user_id'    => $user->user_id,
            'reset_token' => $token,
            'expires_at' => now()->addHour(),
        ]);

        // TODO: Dispatch email with reset link containing the token
        // Mail::to($user->email)->send(new PasswordResetMail($token));

        return response()->json([
            'message' => 'Password reset link sent successfully.',
            'reset_token' => $token, // Expose token in dev; remove in production
        ]);
    }

    /**
     * Validate token and update the user's password.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email'                 => 'required|email|exists:users,email',
            'reset_token'           => 'required|string',
            'password'              => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        $user = User::where('email', $request->email)->first();

        $resetRecord = PasswordReset::where('user_id', $user->user_id)
            ->where('reset_token', $request->reset_token)
            ->where('expires_at', '>', now())
            ->first();

        if (!$resetRecord) {
            return response()->json([
                'message' => 'Invalid or expired reset token.'
            ], 422);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        // Revoke the used reset token
        $resetRecord->delete();

        return response()->json([
            'message' => 'Password has been reset successfully.'
        ]);
    }
}
