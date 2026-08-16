<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class MeController extends Controller
{
    /**
     * Get authenticated user profile with stats.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadCount(['products', 'orders', 'wishlist', 'collection']);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'verified_collector' => $user->verified_collector,
            'preferences' => $user->preferences,
            'stats' => [
                'products_count' => $user->products_count,
                'orders_count' => $user->orders_count,
                'wishlist_count' => $user->wishlist_count,
                'collection_count' => $user->collection_count,
            ],
        ]);
    }

    /**
     * Update authenticated user profile.
     */
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validated();

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Handle avatar - can be file upload or URL string
        if (isset($data['avatar'])) {
            if ($request->hasFile('avatar')) {
                // File upload
                if ($user->avatar) {
                    Storage::disk('public')->delete($user->avatar);
                }
                $path = $request->file('avatar')->store('avatars', 'public');
                $data['avatar'] = $path;
            }
            // If it's a string URL, keep it as is
        }

        $user->update($data);
        $user->loadCount(['products', 'orders', 'wishlist', 'collection']);

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'verified_collector' => $user->verified_collector,
            'preferences' => $user->preferences,
            'stats' => [
                'products_count' => $user->products_count,
                'orders_count' => $user->orders_count,
                'wishlist_count' => $user->wishlist_count,
                'collection_count' => $user->collection_count,
            ],
        ]);
    }
}