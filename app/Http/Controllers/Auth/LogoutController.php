<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class LogoutController extends Controller
{
    // logout dengan cara menghapus token refresh
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

        // pastikan yang dikirim memang token refresh
        if (! $token->can('refresh')) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_TOKEN_TYPE',
                    'message' => 'Token does not have refresh capability.',
                ],
            ], 401);
        }

        // hapus tokennya
        $token->delete();

        return response()->json([
            'message' => 'Successfully logged out.',
        ]);
    }
}
