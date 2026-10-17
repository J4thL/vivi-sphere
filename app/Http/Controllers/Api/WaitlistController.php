<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Stylist;
use App\Models\WaitlistEntry;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WaitlistController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);

        $user = $request->user();

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
        abort_unless($request->user()->isManager() && $request->user()->canAccessBranch($waitlistEntry->branch_id), 403);
        $waitlistEntry->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Removed from waitlist.']);
    }
}
