<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MediaDisk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::with(['role.permissions', 'staff'])->where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['role.permissions', 'staff']);

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    public function changePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'message' => 'Current password is incorrect.',
            ], 422);
        }

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        return response()->json(['message' => 'Password updated successfully']);
    }

    public function updateProfileImage(Request $request)
    {
        $validated = $request->validate([
            'image' => 'required|image|max:10240',
        ]);

        $user = $request->user();

        MediaDisk::deleteIfExists($user->profile_image_path);
        $path = MediaDisk::storeUpload($request->file('image'), 'users/'.$user->id);
        $user->profile_image_path = $path;
        $user->save();

        $user->load(['role.permissions', 'staff']);

        return response()->json([
            'user' => $this->formatUser($user),
        ]);
    }

    private function formatUser(User $user)
    {
        $permissions = $user->role
            ? $user->role->permissions->pluck('permission')->toArray()
            : [];

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'profileImageUrl' => MediaDisk::publicUrl($user->profile_image_path),
            'staff' => $user->staff ? [
                'id' => $user->staff->id,
                'fullName' => $user->staff->full_name,
            ] : null,
            'role' => $user->role ? [
                'id' => $user->role->id,
                'name' => $user->role->name,
                'slug' => $user->role->slug,
                'isSuperAdmin' => $user->role->is_super_admin,
                'permissions' => $permissions,
            ] : null,
        ];
    }
}
