<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchService;
use App\Models\Service;
use Illuminate\Http\Request;

/** Per-branch price, length and availability for services. Reading is public (the booking form needs it); changing is admin only. */
class BranchServiceController extends Controller
{
    public function index()
    {
        return BranchService::orderBy('branch_id')->orderBy('service_id')->get()->map(fn (BranchService $b) => $this->present($b));
    }

    public function update(Request $request, Branch $branch, Service $service)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $request->validate([
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:999999.99'],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:1440'],
            'available' => ['sometimes', 'boolean'],
        ]);

        $row = BranchService::firstOrNew(['branch_id' => $branch->id, 'service_id' => $service->id]);
        $row->fill($data);
        $row->available ??= true;

        // Nothing special left (no own price, no own length, still offered): drop the row so the branch follows the normal values again.
        if ($row->exists && $row->price === null && $row->duration_minutes === null && $row->available) {
            $row->delete();
            AuditLog::record($request->user(), 'branch_service.reset', $service, ['branch' => $branch->name]);

            return response()->json(['branch_id' => $branch->id, 'service_id' => $service->id, 'price' => null, 'duration_minutes' => null, 'available' => true]);
        }
        if (! $row->exists && $row->price === null && $row->duration_minutes === null && $row->available) {
            return response()->json(['branch_id' => $branch->id, 'service_id' => $service->id, 'price' => null, 'duration_minutes' => null, 'available' => true]);
        }

        $row->save();
        AuditLog::record($request->user(), 'branch_service.updated', $service, ['branch' => $branch->name] + $data);

        return response()->json($this->present($row));
    }

    private function present(BranchService $b): array
    {
        return [
            'branch_id' => $b->branch_id,
            'service_id' => $b->service_id,
            'price' => $b->price !== null ? (float) $b->price : null,
            'duration_minutes' => $b->duration_minutes,
            'available' => $b->available,
        ];
    }
}
