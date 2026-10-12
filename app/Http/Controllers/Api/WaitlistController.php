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
            // The salon's "today", not the server's UTC date.
            'preferred_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now(config('app.salon_timezone'))->toDateString()],
        ]);

        // Linking an entry to an arbitrary client record is a manager action; stylists add by name.
        abort_if(isset($data['client_id']) && ! $user->isClient() && ! $user->isManager(), 403, 'Only managers can pick an existing client.');

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
