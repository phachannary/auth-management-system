<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\App;
use App\Models\CognitoAppClient;
use Illuminate\Http\Request;

class AppApiController extends Controller
{
    public function index(Request $request)
    {
        $apps = App::select('id', 'name', 'slug', 'description', 'is_active')
            ->where('is_active', true)
            ->get();

        return response()->json(['apps' => $apps]);
    }

    public function show($slug)
    {
        $app = App::where('slug', $slug)
            ->select('id', 'name', 'slug', 'description', 'is_active')
            ->firstOrFail();

        return response()->json(['app' => $app]);
    }

    public function cognitoClients(Request $request, $slug)
    {
        $app = App::where('slug', $slug)->firstOrFail();

        $clients = CognitoAppClient::where('app_id', $app->id)
            ->where('is_active', true)
            ->select('id', 'client_id', 'platform', 'is_active')
            ->get();

        return response()->json(['cognito_clients' => $clients]);
    }
}
