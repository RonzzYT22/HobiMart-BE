<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    // daftar user baru
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
        ]);

        // buat token akses dan token refresh
        $accessToken = $user->createToken('access-token', ['access'], now()->addMinutes(15))->plainTextToken;
        $refreshToken = $user->createToken('refresh-token', ['refresh'], now()->addDays(30))->plainTextToken;

        return response()->json([
            'access_token' => $accessToken,
            'refresh_token' => $refreshToken,
            'user' => $this->userResponse($user),
        ], 201);
    }

    // susun data user beserta jumlah relasinya
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
