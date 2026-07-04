<?php

use Illuminate\Support\Facades\Route;
use App\Services\CognitoService;

Route::get('/test-cognito', function() {
    try {
        $service = new CognitoService();
        $result = $service->signUp('test' . time() . '@example.com', 'TestPassword123!', 'test' . time() . '@example.com');
        return response()->json($result);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});
