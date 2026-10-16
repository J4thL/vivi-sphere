<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Stylist;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Staff accounts (managers and stylists) are created by an admin only. Public registration
 * only ever makes client accounts. The temporary password is returned once, in the create
 * response, and is never stored in readable form.
 */
class StaffController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        return User::whereIn('role', ['manager', 'stylist'])
            ->with('stylist:id,role')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'role', 'stylist_id', 'created_at']);
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'role' => ['required', Rule::in(['manager', 'stylist'])],
            // Job title shown on the public stylist profile (only used for stylists).
            'title' => ['nullable', 'required_if:role,stylist', 'string', 'max:255'],
        ]);

        $tempPassword = Str::random(10);

        $user = DB::transaction(function () use ($request, $data, $tempPassword) {
            $stylist = null;
            if ($data['role'] === 'stylist') {
                $stylist = Stylist::create(['name' => $data['name'], 'role' => $data['title'], 'active' => true]);
            }

            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($tempPassword),
                'role' => $data['role'],
                'stylist_id' => $stylist?->id,
            ]);

            AuditLog::record($request->user(), 'staff.created', $user, ['role' => $data['role'], 'email' => $data['email']]);

            return $user;
        });

        return response()->json([
            'user' => $user->only(['id', 'name', 'email', 'role', 'stylist_id']),
            'temp_password' => $tempPassword,
        ], 201);
    }
}
