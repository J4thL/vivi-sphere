<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    /** Public: the booking form and landing page list the active branches. An admin can ask for inactive ones too. */
    public function index(Request $request)
    {
        $query = Branch::orderBy('name');
        $viewer = $request->user('sanctum');
        if (! ($viewer?->isAdmin() && $request->boolean('include_inactive'))) {
            $query->where('active', true);
        }

        return $query->get()->map(fn (Branch $b) => $this->present($b))->values();
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $branch = Branch::create($this->validated($request));
        AuditLog::record($request->user(), 'branch.created', $branch, ['name' => $branch->name]);

        return response()->json($this->present($branch), 201);
    }

    public function update(Request $request, Branch $branch)
    {
        abort_unless($request->user()->isAdmin(), 403);

        $data = $this->validated($request, $branch);
        $branch->update($data);
        AuditLog::record($request->user(), 'branch.updated', $branch, ['name' => $branch->name]);

        return response()->json($this->present($branch));
    }

    private function validated(Request $request, ?Branch $branch = null): array
    {
        $sometimes = $branch ? 'sometimes' : 'required';
        $data = $request->validate([
            'name' => [$sometimes, 'string', 'max:255', 'unique:branches,name'.($branch ? ",{$branch->id}" : '')],
            'address' => ['sometimes', 'nullable', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'map_query' => ['sometimes', 'nullable', 'string', 'max:255'],
            'open_time' => ['sometimes', 'date_format:H:i'],
            'close_time' => ['sometimes', 'date_format:H:i'],
            'active' => ['sometimes', 'boolean'],
        ]);

        // Judge the hours as they would be after this save.
        $open = $data['open_time'] ?? ($branch ? substr((string) $branch->open_time, 0, 5) : '09:00');
        $close = $data['close_time'] ?? ($branch ? substr((string) $branch->close_time, 0, 5) : '19:00');
        abort_if($close <= $open, 422, 'Closing time must be after opening time.');

        return $data;
    }

    private function present(Branch $b): array
    {
        return [
            'id' => $b->id,
            'name' => $b->name,
            'address' => $b->address,
            'phone' => $b->phone,
            'map_query' => $b->map_query,
            'open_time' => substr((string) $b->open_time, 0, 5),
            'close_time' => substr((string) $b->close_time, 0, 5),
            'active' => $b->active,
        ];
    }
}
