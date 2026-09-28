<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        // The password is hashed by the "hashed" cast on the User model.
        $user = User::create($request->validated());

        return ApiResponse::success(
            $this->tokenPayload($user, $user->createToken('api')->plainTextToken),
            'User registered successfully.',
            201,
        );
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return ApiResponse::error('Invalid credentials.', 401);
        }

        $token = $user->createToken($request->validated('device_name', 'api'))->plainTextToken;

        return ApiResponse::success($this->tokenPayload($user, $token), 'Login successful.');
    }

    public function logout(Request $request): JsonResponse
    {
        // Revoke only the token used for this request.
        $request->user()->currentAccessToken()->delete();

        return ApiResponse::success(message: 'Logged out successfully.');
    }

    public function user(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()), 'Authenticated user retrieved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenPayload(User $user, string $token): array
    {
        return [
            'user' => new UserResource($user),
            'token' => $token,
            'token_type' => 'Bearer',
        ];
    }
}
