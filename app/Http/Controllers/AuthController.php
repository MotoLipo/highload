<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AuthService $service): JsonResponse
    {
        $token = $service->login($request->validated('email'), $request->validated('password'));

        return response()->json(['token' => $token]);
    }
}
