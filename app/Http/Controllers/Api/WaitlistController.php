<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;

class WaitlistController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);

        return WaitlistEntry::with(['service', 'stylist'])->where('status', 'waiting')->orderBy('preferred_date')->get();
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'client_name' => ['required_without:client_id', 'string'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'service_id' => ['required', 'exists:services,id'],
            'stylist_id' => ['nullable', 'exists:stylists,id'],
            'preferred_date' => ['required', 'date'],
        ]);

        if ($user->isClient()) {
            $client = Client::firstOrCreate(['user_id' => $user->id], ['name' => $user->name]);
            $data['client_id'] = $client->id;
            $data['client_name'] = $client->name;
        }

        if (empty($data['client_name']) && ! empty($data['client_id'])) {
            $data['client_name'] = Client::findOrFail($data['client_id'])->name;
        }

        $entry = WaitlistEntry::create($data + ['status' => 'waiting']);

        return response()->json($entry, 201);
    }

    public function destroy(Request $request, WaitlistEntry $waitlistEntry)
    {
        abort_unless($request->user()->isManager(), 403);
        $waitlistEntry->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Removed from waitlist.']);
    }
}
