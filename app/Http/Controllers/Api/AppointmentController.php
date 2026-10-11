<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Client;
use App\Models\SalonSetting;
use App\Models\Service;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AppointmentController extends Controller
{
    /**
     * Admins/managers see everything. Stylists see only their own
     * appointments. Clients see only their own bookings. Enforced here
     * rather than trusted from the frontend. Optional ?date=YYYY-MM-DD
     * narrows to a day, which is what the calendar view in the dashboard uses.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $query = Appointment::with(['client', 'stylist', 'service']);

        if ($user->isStylist()) {
            $query->where('stylist_id', $user->stylist_id);
        } elseif ($user->isClient()) {
            $query->whereHas('client', fn ($q) => $q->where('user_id', $user->id));
        }

        // Range comparisons (not whereDate()) so the scheduled_at index can be used.
        if ($request->filled('date')) {
            $day = Carbon::parse($request->query('date'));
            $query->whereBetween('scheduled_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()]);
        }
        if ($request->filled('from')) {
            $query->where('scheduled_at', '>=', Carbon::parse($request->query('from'))->startOfDay());
        }
        if ($request->filled('to')) {
            $query->where('scheduled_at', '<=', Carbon::parse($request->query('to'))->endOfDay());
        }

        return $query->orderBy('scheduled_at')->get();
    }

    /**
     * True if this stylist has any live (non-cancelled) appointment whose
     * time range overlaps [start, start+durationMinutes). Exact-match was
     * not enough: a 45-minute Balayage starting at 9:00 and a 20-minute
     * Beard Trim starting at 9:15 don't share a start time but do overlap.
     */
    private function slotOverlaps(int $stylistId, Carbon $start, int $durationMinutes, ?int $excludeId = null): bool
    {
        $end = $start->copy()->addMinutes($durationMinutes);

        // Appointments never cross midnight (they must fit inside opening hours), so only
        // that stylist's appointments on the same day can overlap - no need to load their history.
        return Appointment::where('stylist_id', $stylistId)
            ->whereBetween('scheduled_at', [$start->copy()->startOfDay(), $start->copy()->endOfDay()])
            ->where('status', '!=', 'cancelled')
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->with('service')
            ->get()
            ->contains(function ($existing) use ($start, $end) {
                $existingStart = Carbon::parse($existing->scheduled_at);
                $existingEnd = $existingStart->copy()->addMinutes($existing->service->duration_minutes ?? 45);

                return $start->lt($existingEnd) && $existingStart->lt($end);
            });
    }

    /** Blocks bookings outside the salon's configured open/close hours. */
    private function withinBusinessHours(Carbon $start, int $durationMinutes): bool
    {
        $settings = SalonSetting::current();
        $open = Carbon::parse($start->toDateString().' '.$settings->open_time);
        $close = Carbon::parse($start->toDateString().' '.$settings->close_time);

        return $start->gte($open) && $start->copy()->addMinutes($durationMinutes)->lte($close);
    }

    /** Bookings must not start in the past (15 minutes of grace covers walk-ins entered on arrival). */
    private function assertNotInPast(Carbon $start): void
    {
        if ($start->lt(now()->subMinutes(15))) {
            throw ValidationException::withMessages(['scheduled_at' => 'That time has already passed. Pick a future time.']);
        }
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'client_name' => ['required_without:client_id', 'string'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'stylist_id' => ['required', Rule::exists('stylists', 'id')->where('active', true)],
            'service_id' => ['required', 'exists:services,id'],
            'scheduled_at' => ['required', 'date'],
        ]);

        $service = Service::findOrFail($data['service_id']);
        $start = Carbon::parse($data['scheduled_at']);

        $this->assertNotInPast($start);
        if (! $this->withinBusinessHours($start, $service->duration_minutes)) {
            throw ValidationException::withMessages(['scheduled_at' => 'That time is outside the salon\'s open hours.']);
        }
        if ($this->slotOverlaps((int) $data['stylist_id'], $start, $service->duration_minutes)) {
            throw ValidationException::withMessages(['scheduled_at' => 'That stylist is already booked over this time. Pick another slot or join the waitlist.']);
        }

        if ($user->isClient()) {
            $client = Client::firstOrCreate(['user_id' => $user->id], ['name' => $user->name]);
        } else {
            $client = isset($data['client_id'])
                ? Client::findOrFail($data['client_id'])
                : Client::firstOrCreate(['name' => $data['client_name']]);
        }

        $appointment = Appointment::create([
            'client_id' => $client->id,
            'stylist_id' => $data['stylist_id'],
            'service_id' => $service->id,
            'scheduled_at' => $data['scheduled_at'],
            'status' => 'confirmed',
            'price' => $service->price,
        ]);

        AuditLog::record($user, 'appointment.booked', $appointment);

        return response()->json($appointment->load(['client', 'stylist', 'service']), 201);
    }

    public function update(Request $request, Appointment $appointment)
    {
        $user = $request->user();

        if ($user->isStylist() && $appointment->stylist_id !== $user->stylist_id) {
            abort(403);
        }
        if ($user->isClient() && $appointment->client->user_id !== $user->id) {
            abort(403);
        }

        $data = $request->validate([
            'status' => ['sometimes', 'in:confirmed,in-progress,completed,cancelled'],
            'scheduled_at' => ['sometimes', 'date'],
            'stylist_id' => ['sometimes', 'exists:stylists,id'],
            'service_id' => ['sometimes', 'exists:services,id'],
        ]);

        if ($user->isStylist() && isset($data['stylist_id']) && (int) $data['stylist_id'] !== (int) $appointment->stylist_id) {
            abort(403, 'Only managers can reassign an appointment to another stylist.');
        }

        if ($user->isClient()) {
            if ($appointment->status !== 'confirmed') {
                abort(403, 'Only upcoming (confirmed) appointments can be changed.');
            }
            if (isset($data['status']) && $data['status'] !== 'cancelled') {
                abort(403, 'Clients may only cancel appointments.');
            }
            if (isset($data['stylist_id']) && $data['stylist_id'] !== $appointment->stylist_id) {
                abort(403, 'Clients cannot reassign their stylist.');
            }
        }

        // Reschedule can also change the service, which changes duration and
        // price - keep both in sync with whatever service the appointment
        // ends up with.
        $newService = isset($data['service_id']) ? Service::findOrFail($data['service_id']) : $appointment->service;
        if (isset($data['service_id'])) {
            $data['price'] = $newService->price;
        }

        $movingTime = isset($data['scheduled_at']) || isset($data['stylist_id']) || isset($data['service_id']);
        if ($movingTime) {
            $newStylistId = $data['stylist_id'] ?? $appointment->stylist_id;
            $newStart = Carbon::parse($data['scheduled_at'] ?? $appointment->scheduled_at);
            $duration = $newService->duration_minutes ?? 45;

            if (isset($data['scheduled_at'])) {
                $this->assertNotInPast($newStart);
            }
            if (! $this->withinBusinessHours($newStart, $duration)) {
                throw ValidationException::withMessages(['scheduled_at' => 'That time is outside the salon\'s open hours.']);
            }
            if ($this->slotOverlaps((int) $newStylistId, $newStart, $duration, $appointment->id)) {
                throw ValidationException::withMessages(['scheduled_at' => 'That stylist is already booked over this time. Pick another slot or join the waitlist.']);
            }
        }

        $previousStatus = $appointment->status;
        $appointment->update($data);

        if (($data['status'] ?? null) === 'completed' && $previousStatus !== 'completed') {
            $client = $appointment->client;
            $client->increment('visits');
            $client->increment('spend', $appointment->price);
            $client->update(['last_visit_at' => now()]);
        }

        if (isset($data['status']) && $data['status'] !== $previousStatus) {
            AuditLog::record($user, "appointment.{$data['status']}", $appointment, ['from' => $previousStatus]);
        } elseif ($movingTime) {
            AuditLog::record($user, 'appointment.rescheduled', $appointment);
        }

        return response()->json($appointment->fresh(['client', 'stylist', 'service']));
    }
}
