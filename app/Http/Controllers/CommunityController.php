<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Comment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    // daftar postingan (public)
    public function posts(Request $request): JsonResponse
    {
        $category = $request->query('category');
        $query = Post::with('user:id,name,avatar')->orderByDesc('created_at');

        if ($category) {
            $query->where('category', $category);
        }

        $posts = $query->paginate(20)->through(function ($post) {
            return [
                'id' => $post->id,
                'title' => $post->title,
                'content' => mb_strimwidth($post->content, 0, 200, '...'),
                'category' => $post->category,
                'images' => $post->images,
                'likes' => $post->likes,
                'commentsCount' => $post->comments_count,
                'author' => [
                    'id' => $post->user->id ?? null,
                    'name' => $post->user->name ?? null,
                    'avatar' => $post->user->avatar ?? null,
                ],
                'createdAt' => $post->created_at?->toISOString(),
            ];
        });

        return response()->json($posts);
    }

    // detail postingan + komentar
    public function show(Post $post): JsonResponse
    {
        $post->load('user:id,name,avatar', 'comments.user:id,name,avatar');

        return response()->json([
            'id' => $post->id,
            'title' => $post->title,
            'content' => $post->content,
            'category' => $post->category,
            'images' => $post->images,
            'likes' => $post->likes,
            'commentsCount' => $post->comments_count,
            'author' => [
                'id' => $post->user->id ?? null,
                'name' => $post->user->name ?? null,
                'avatar' => $post->user->avatar ?? null,
            ],
            'comments' => $post->comments->map(function ($c) {
                return [
                    'id' => $c->id,
                    'content' => $c->content,
                    'author' => [
                        'id' => $c->user->id ?? null,
                        'name' => $c->user->name ?? null,
                        'avatar' => $c->user->avatar ?? null,
                    ],
                    'createdAt' => $c->created_at?->toISOString(),
                ];
            }),
            'createdAt' => $post->created_at?->toISOString(),
        ]);
    }

    // buat postingan baru
    public function storePost(Request $request): JsonResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:50'],
            'images' => ['nullable', 'array'],
        ]);

        $post = Post::create([
            'user_id' => $request->user()->id,
            'title' => $request->title,
            'content' => $request->content,
            'category' => $request->category,
            'images' => $request->images,
        ]);

        return response()->json(['id' => $post->id], 201);
    }

    // tambah komentar
    public function addComment(Request $request, Post $post): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $request->user()->id,
            'content' => $request->content,
        ]);

        $post->increment('comments_count');

        return response()->json([
            'id' => $comment->id,
            'content' => $comment->content,
            'author' => [
                'id' => $request->user()->id,
                'name' => $request->user()->name,
                'avatar' => $request->user()->avatar,
            ],
            'createdAt' => $comment->created_at?->toISOString(),
        ], 201);
    }

    // like post
    public function like(Post $post): JsonResponse
    {
        $post->increment('likes');
        return response()->json(['likes' => $post->likes]);
    }
}