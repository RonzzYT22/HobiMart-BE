<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class RefreshController extends Controller
{
    // tukar refresh token dengan access token baru
    public function __invoke(Request $request): JsonResponse
    {
        $refreshToken = $request->bearerToken();

        if (! $refreshToken) {
            return response()->json([
                'error' => [
                    'code' => 'MISSING_REFRESH_TOKEN',
                    'message' => 'Refresh token is required.',
                ],
            ], 401);
        }

        $token = PersonalAccessToken::findToken($refreshToken);

        if (! $token) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_REFRESH_TOKEN',
                    'message' => 'Invalid refresh token.',
                ],
            ], 401);
        }

        // pastikan token ini memang token refresh
        if (! $token->can('refresh')) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_TOKEN_TYPE',
                    'message' => 'Token does not have refresh capability.',
                ],
            ], 401);
        }

        // cek masa berlaku token
        if ($token->expires_at && $token->expires_at->isPast()) {
            $token->delete();
            return response()->json([
                'error' => [
                    'code' => 'REFRESH_TOKEN_EXPIRED',
                    'message' => 'Refresh token has expired.',
                ],
            ], 401);
        }

        $user = $token->tokenable;

        if (! $user) {
            return response()->json([
                'error' => [
                    'code' => 'USER_NOT_FOUND',
                    'message' => 'User associated with token not found.',
                ],
            ], 401);
        }

        // token refresh lama dihapus, terus bikin yang baru
        $token->delete();

        $accessToken = $user->createToken('access-token', ['access'], now()->addMinutes(15))->plainTextToken;
        $newRefreshToken = $user->createToken('refresh-token', ['refresh'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $newRefreshToken,
            'user' => $this->userResponse($user),
        ]);
    }

    // susun data user beserta jumlah relasinya
    protected function userResponse($user): array
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
