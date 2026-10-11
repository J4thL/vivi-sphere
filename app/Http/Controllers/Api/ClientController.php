<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // A client only ever sees their own record (used for "Total spent" / "Visits").
        if ($user->isClient()) {
            return Client::where('user_id', $user->id)->get();
        }

        abort_unless($user->isManager(), 403);

        return Client::orderByDesc('spend')->get();
    }
}
