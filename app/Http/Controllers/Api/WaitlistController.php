<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Service;
use App\Models\Stylist;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WaitlistController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // A client sees only their own waiting requests (so they can check on, or withdraw, them).
        if ($user->isClient()) {
            return WaitlistEntry::with(['service', 'stylist'])->where('status', 'waiting')
                ->whereIn('client_id', Client::where('user_id', $user->id)->select('id'))
                ->orderBy('preferred_date')->limit(config('salon.waitlist_max'))->get();
        }

        abort_unless($user->isManager(), 403);

        return WaitlistEntry::with(['service', 'stylist'])->where('status', 'waiting')
            // A manager sees their own branch; an admin sees all, or one with ?branch_id=.
            ->when(! $user->isAdmin(), fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($user->isAdmin() && $request->filled('branch_id'), fn ($q) => $q->where('branch_id', (int) $request->query('branch_id')))
            ->orderBy('preferred_date')->limit(config('salon.waitlist_max'))->get();
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'client_name' => ['required_without:client_id', 'string', 'max:255'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'service_id' => ['required', 'exists:services,id'],
            'stylist_id' => ['nullable', 'exists:stylists,id'],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('active', true)],
            // The salon's "today", not the server's UTC date.
            'preferred_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now(config('app.salon_timezone'))->toDateString()],
        ]);

        // Linking an entry to an arbitrary client record is a manager action; stylists add by name.
        abort_if(isset($data['client_id']) && ! $user->isClient() && ! $user->isManager(), 403, 'Only managers can pick an existing client.');

        // Staff (other than the admin) always add to their own branch; clients and the admin choose.
        if ($user->isManager() && ! $user->isAdmin() || $user->isStylist()) {
            $data['branch_id'] = $user->branch_id;
        }
        if (empty($data['branch_id']) && ! empty($data['stylist_id'])) {
            $data['branch_id'] = Stylist::whereKey($data['stylist_id'])->value('branch_id');
        }
        if (empty($data['branch_id'])) {
            throw ValidationException::withMessages(['branch_id' => 'Please choose a branch.']);
        }
        if (! empty($data['stylist_id']) && (int) Stylist::whereKey($data['stylist_id'])->value('branch_id') !== (int) $data['branch_id']) {
            throw ValidationException::withMessages(['stylist_id' => 'That stylist works at a different branch.']);
        }

        if (! Service::findOrFail($data['service_id'])->termsFor((int) $data['branch_id'])->available) {
            throw ValidationException::withMessages(['service_id' => 'That service is not offered at this branch.']);
        }

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
        $user = $request->user();

        // A client may withdraw their own request, and nobody else's.
        $ownsIt = $user->isClient() && $waitlistEntry->client_id !== null && $waitlistEntry->client_id === $user->client?->id;
        abort_unless($ownsIt || ($user->isManager() && $user->canAccessBranch($waitlistEntry->branch_id)), 403);
        $waitlistEntry->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Removed from waitlist.']);
    }
}
