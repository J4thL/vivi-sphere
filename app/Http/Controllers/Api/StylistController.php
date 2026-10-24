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

class StylistController extends Controller
{
    public function index(Request $request)
    {
        return Stylist::orderBy('name')
            ->when($request->filled('branch_id'), fn ($q) => $q->where('branch_id', (int) $request->query('branch_id')))
            ->get();
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'unique:users,email'],
            'photo_url' => ['nullable', 'url:http,https', 'max:255'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('active', true)],
        ]);

        // A manager always adds to their own branch; an admin chooses which one.
        $user = $request->user();
        $branchId = $user->isAdmin() ? ($data['branch_id'] ?? null) : $user->branch_id;
        abort_if($branchId === null, 422, 'Please choose a branch for this stylist.');

        // Employee logins are created by the admin only. A manager can still add a stylist profile
        // (no email), but cannot hand out a login.
        abort_if(! empty($data['email']) && ! $request->user()->isAdmin(), 403, 'Only an admin can create employee accounts.');

        // The stylist, their login and the audit entry are saved together: if the login can't be
        // created, there must not be a half-set-up stylist left behind.
        $stylist = DB::transaction(function () use ($request, $data, $branchId) {
            $stylist = Stylist::create([
                'name' => $data['name'],
                'role' => $data['role'],
                'photo_url' => $data['photo_url'] ?? null,
                'bio' => $data['bio'] ?? null,
                'active' => true,
                'branch_id' => $branchId,
            ]);

            if (! empty($data['email'])) {
                $tempPassword = Str::random(10);
                User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($tempPassword),
                    'role' => 'stylist',
                    'stylist_id' => $stylist->id,
                    'branch_id' => $branchId,
                ])->forceFill(['must_change_password' => true])->save();
                $stylist->temp_password = $tempPassword;
            }

            AuditLog::record($request->user(), 'stylist.created', $stylist);

            return $stylist;
        });

        return response()->json($stylist, 201);
    }

    public function update(Request $request, Stylist $stylist)
    {
        abort_unless($request->user()->isManager(), 403);

        $data = $request->validate([
            'active' => ['sometimes', 'boolean'],
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'string', 'max:255'],
            'photo_url' => ['sometimes', 'nullable', 'url:http,https', 'max:255'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'branch_id' => ['sometimes', Rule::exists('branches', 'id')->where('active', true)],
        ]);

        // A manager can only change stylists in their own branch, and only an admin can move one.
        abort_unless($request->user()->canAccessBranch($stylist->branch_id), 403);
        if (array_key_exists('branch_id', $data) && (int) $data['branch_id'] !== (int) $stylist->branch_id) {
            abort_unless($request->user()->isAdmin(), 403);
            // Past bookings stay in the branch where they happened, so a stylist who already has some can't be moved.
            abort_if($stylist->appointments()->exists(), 422, 'This stylist already has appointments, so they cannot be moved to another branch.');
        }

        $stylist->update($data);
        if (array_key_exists('branch_id', $data)) {
            User::where('stylist_id', $stylist->id)->update(['branch_id' => $data['branch_id']]);
        }
        AuditLog::record($request->user(), 'stylist.updated', $stylist, $data);

        return response()->json($stylist);
    }
}
