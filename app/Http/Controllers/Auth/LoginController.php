<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    /**
     * Login user.
     */
    public function __invoke(LoginRequest $request): JsonResponse
    {
        $credentials = $request->only(['email', 'phone', 'password']);

        $user = User::where(function ($query) use ($credentials) {
            if (isset($credentials['email'])) {
                $query->where('email', $credentials['email']);
            }
            if (isset($credentials['phone'])) {
                $query->orWhere('phone', $credentials['phone']);
            }
        })->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_CREDENTIALS',
                    'message' => 'The provided credentials are incorrect.',
                ],
            ], 401);
        }

        // Revoke existing refresh tokens
        $user->tokens()->where('name', 'refresh-token')->delete();

        $accessToken = $user->createToken('access-token', ['access'], now()->addMinutes(15))->plainTextToken;
        $refreshToken = $user->createToken('refresh-token', ['refresh'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'user' => $this->userResponse($user),
        ]);
    }

    /**
     * Format user response with stats.
     */
    protected function userResponse(User $user): array
    {
        $user->loadCount(['products', 'orders', 'wishlist', 'collection']);

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'verified_collector' => $user->verified_collector,
            'stats' => [
                'products_count' => $user->products_count,
                'orders_count' => $user->orders_count,
                'wishlist_count' => $user->wishlist_count,
                'collection_count' => $user->collection_count,
            ],
        ];
    }
}