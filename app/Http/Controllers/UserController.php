<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Return all platform users.
     */
    public function index()
    {
        $users = User::select(
            'user_id', 'role', 'username', 'first_name', 'last_name',
            'gender', 'email', 'status', 'phone_number', 'profile_picture', 'last_login', 'created_at'
        )->get();

        return response()->json($users);
    }

    /**
     * Register a new user account.
     */
    public function store(Request $request)
    {
        $request->validate([
            'role'         => 'required|in:admin,driver,guardian',
            'username'     => 'required|string|unique:users,username',
            'first_name'   => 'required|string',
            'last_name'    => 'required|string',
            'gender'       => 'nullable|string',
            'email'        => 'required|email|unique:users,email',
            'password'     => 'required|string|min:8',
            'phone_number' => 'nullable|string',
        ]);

        $user = User::create([
            'role'         => $request->role,
            'username'     => $request->username,
            'first_name'   => $request->first_name,
            'last_name'    => $request->last_name,
            'gender'       => $request->gender,
            'email'        => $request->email,
            'password'     => Hash::make($request->password),
            'phone_number' => $request->phone_number,
            'status'       => true,
        ]);

        return response()->json([
            'message' => 'User account created successfully.',
            'user'    => $user
        ], 201);
    }

    /**
     * Update individual properties of a target user.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'first_name'      => 'sometimes|string',
            'last_name'       => 'sometimes|string',
            'email'           => 'sometimes|email|unique:users,email,' . $user->user_id . ',user_id',
            'phone_number'    => 'nullable|string',
            'profile_picture' => 'nullable|string',
        ]);

        $user->update($request->only([
            'first_name', 'last_name', 'email', 'phone_number', 'profile_picture'
        ]));

        return response()->json([
            'message' => 'User updated successfully.',
            'user'    => $user
        ]);
    }

    /**
     * Invert the active/disabled status of a user account.
     */
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);
        $user->update(['status' => !$user->status]);

        return response()->json([
            'message' => 'User status toggled successfully.',
            'status'  => $user->status
        ]);
    }
}
