<?php

namespace App\Http\Controllers\OAuth;

use App\Http\Controllers\Controller;
use App\Services\JwtService;
use Illuminate\Http\Response;

class JwksController extends Controller
{
    protected JwtService $jwtService;

    public function __construct(JwtService $jwtService)
    {
        $this->jwtService = $jwtService;
    }

    public function jwks()
    {
        return response()->json($this->jwtService->getJwks());
    }
}
