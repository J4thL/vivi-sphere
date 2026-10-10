<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request)
    {
        abort_unless($request->user()->isManager(), 403);

        return Client::orderByDesc('spend')->get();
    }
}
