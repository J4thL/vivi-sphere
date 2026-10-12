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

class StylistController extends Controller
{
    public function index()
    {
        return Stylist::orderBy('name')->get();
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
        ]);

        // The stylist, their login and the audit entry are saved together: if the login can't be
        // created, there must not be a half-set-up stylist left behind.
        $stylist = DB::transaction(function () use ($request, $data) {
            $stylist = Stylist::create([
                'name' => $data['name'],
                'role' => $data['role'],
                'photo_url' => $data['photo_url'] ?? null,
                'bio' => $data['bio'] ?? null,
                'active' => true,
            ]);

            if (! empty($data['email'])) {
                $tempPassword = Str::random(10);
                User::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($tempPassword),
                    'role' => 'stylist',
                    'stylist_id' => $stylist->id,
                ]);
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
        ]);

        $stylist->update($data);
        AuditLog::record($request->user(), 'stylist.updated', $stylist, $data);

        return response()->json($stylist);
    }
}
