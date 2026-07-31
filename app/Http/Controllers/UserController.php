<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Return all platform users.
     */
    public function index(Request $request)
    {
        $perPage = $request->query('per_page', 15);
        $version = Cache::remember('users:version_v2', 86400, fn() => 2);
        $cacheKey = 'users:list:v' . $version . ':' . md5(json_encode($request->query()));

        $data = Cache::remember($cacheKey, 300, function () use ($request, $perPage) {
            $query = User::select(
                'user_id', 'role', 'username', 'first_name', 'last_name',
                'gender', 'email', 'status', 'phone_number', 'profile_picture', 'last_login', 'created_at'
            )->with(['guardian.students']);

            if ($request->filled('role') && strtolower($request->query('role')) !== 'all users' && strtolower($request->query('role')) !== 'all') {
                $roleVal = strtolower(rtrim($request->query('role'), 's'));
                if ($roleVal === 'administrator') $roleVal = 'admin';
                $query->where('role', $roleVal);
            }

            if ($request->filled('status') && strtolower($request->query('status')) !== 'all') {
                $statusVal = strtolower($request->query('status'));
                if ($statusVal === 'active') {
                    $query->where('status', true);
                } elseif ($statusVal === 'suspended' || $statusVal === 'disabled' || $statusVal === 'inactive') {
                    $query->where('status', false);
                }
            }

            if ($request->filled('search')) {
                $search = trim($request->query('search'));
                $isPg = DB::connection()->getDriverName() === 'pgsql';
                $likeOp = $isPg ? 'ILIKE' : 'LIKE';
                $concat = $isPg ? "first_name || ' ' || last_name" : "CONCAT(first_name, ' ', last_name)";

                $tokens = array_filter(explode(' ', $search));

                $query->where(function ($q) use ($search, $tokens, $likeOp, $concat) {
                    $q->where('username', $likeOp, "%{$search}%")
                      ->orWhere('first_name', $likeOp, "%{$search}%")
                      ->orWhere('last_name', $likeOp, "%{$search}%")
                      ->orWhere('email', $likeOp, "%{$search}%")
                      ->orWhere('phone_number', $likeOp, "%{$search}%")
                      ->orWhere(DB::raw($concat), $likeOp, "%{$search}%")
                      ->orWhereHas('guardian.students', function ($sq) use ($search, $likeOp) {
                          $sq->where('first_name', $likeOp, "%{$search}%")
                             ->orWhere('last_name', $likeOp, "%{$search}%");
                      });

                    if (count($tokens) > 1) {
                        $q->orWhere(function ($tq) use ($tokens, $likeOp) {
                            foreach ($tokens as $token) {
                                $tq->where(function ($sub) use ($token, $likeOp) {
                                    $sub->where('first_name', $likeOp, "%{$token}%")
                                       ->orWhere('last_name', $likeOp, "%{$token}%")
                                       ->orWhere('username', $likeOp, "%{$token}%")
                                       ->orWhere('email', $likeOp, "%{$token}%");
                                });
                            }
                        });
                    }
                });
            }

            $users = $query->paginate($perPage);
            $responseArray = $users->toArray();

            $responseArray['summary_stats'] = Cache::remember('users:summary', 300, function () {
                return [
                    'total_users' => User::count(),
                    'drivers' => User::where('role', 'driver')->count(),
                    'guardians' => User::where('role', 'guardian')->count(),
                    'admins' => User::where('role', 'admin')->count(),
                ];
            });

            return $responseArray;
        });

        return response()->json($data);
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

        $this->invalidateUserCache();

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

        $this->invalidateUserCache();

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
        $newStatus = !$user->status;
        $user->update(['status' => $newStatus]);

        if (!$newStatus) {
            // Revoke all active Sanctum tokens for suspended user
            $user->tokens()->delete();
            Cache::put("user:suspended:{$user->user_id}", true, 86400);
        } else {
            Cache::forget("user:suspended:{$user->user_id}");
        }

        $this->invalidateUserCache();

        return response()->json([
            'message' => $newStatus ? 'User account activated successfully.' : 'User account suspended successfully.',
            'status'  => $user->status
        ]);
    }

    /**
     * Permanently remove a user account from the platform.
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);
        $user->delete();

        $this->invalidateUserCache();

        return response()->json([
            'message' => 'User deleted successfully.',
        ]);
    }

    private function invalidateUserCache()
    {
        Cache::forget('users:summary');
        Cache::increment('users:version');
    }
}
