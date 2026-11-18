<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $r)
    {
        $d = $r->validate(['email' => 'required|email', 'password' => 'required']);
        $u = User::where('email', $d['email'])->first();
        if (! $u || ! Hash::check($d['password'], $u->password) || ! $u->is_active) {
            return response()->json(['message' => 'Invalid credentials'], 422);
        }

        ActivityLog::create([
            'user_id' => $u->id,
            'action' => 'user.login',
            'description' => 'User logged in',
            'ip_address' => $r->ip(),
            'user_agent' => $r->userAgent(),
        ]);

        return ['token' => $u->createToken('spa')->plainTextToken, 'user' => $this->payload($u)];
    }

    public function me(Request $r)
    {
        return $this->payload($r->user());
    }

    public function logout(Request $r)
    {
        $r->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    private function payload(User $u)
    {
        return $u->only('id', 'name', 'email', 'employee_id') + ['roles' => $u->getRoleNames()];
    }
}
