<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Services\AuthService;
use App\Traits\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ApiResponse;

    public function __construct(private readonly AuthService $authService) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->successResponse(
            $this->authService->register($request->validated()),
            'Registered successfully.',
            201
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->string('email')->toString(),
                $request->string('password')->toString()
            );

            return $this->successResponse($result, 'Logged in successfully.');
        } catch (\Throwable $e) {
            $status = $e instanceof AuthenticationException ? 401 : 500;

            return $this->errorResponse($e->getMessage(), $status);
        }
    }

    public function logout(Request $request): JsonResponse
    {
        $this->authService->logout($request->user());

        return $this->successResponse(null, 'Logged out successfully.');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->successResponse(
            $this->authService->me($request->user()),
            'User retrieved successfully.'
        );
    }
}