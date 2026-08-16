<?php

namespace App\Http\Controllers;

use App\Models\Upload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UploadController extends Controller
{
    // upload gambar
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
        ]);

        $user = $request->user();
        $file = $request->file('file');

        // simpan ke storage local
        $path = $file->store('uploads', 'public');

        // baca dimensi gambar
        $dimensions = @getimagesize($file->getRealPath());
        $width = $dimensions ? $dimensions[0] : null;
        $height = $dimensions ? $dimensions[1] : null;

        $upload = Upload::create([
            'user_id' => $user->id,
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $width,
            'height' => $height,
        ]);

        return response()->json([
            'id' => $upload->id,
            'url' => $upload->url(),
            'originalName' => $upload->original_name,
            'size' => $upload->size,
            'width' => $upload->width,
            'height' => $upload->height,
        ], 201);
    }

    // upload multiple gambar
    public function storeMultiple(Request $request): JsonResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => ['required', 'file', 'image', 'mimes:jpeg,png,webp,gif', 'max:10240'],
        ]);

        $user = $request->user();
        $results = [];

        foreach ($request->file('files') as $file) {
            $path = $file->store('uploads', 'public');

            $dimensions = @getimagesize($file->getRealPath());
            $width = $dimensions ? $dimensions[0] : null;
            $height = $dimensions ? $dimensions[1] : null;

            $upload = Upload::create([
                'user_id' => $user->id,
                'disk' => 'public',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'width' => $width,
                'height' => $height,
            ]);

            $results[] = [
                'id' => $upload->id,
                'url' => $upload->url(),
                'originalName' => $upload->original_name,
                'size' => $upload->size,
                'width' => $upload->width,
                'height' => $upload->height,
            ];
        }

        return response()->json(['files' => $results], 201);
    }
}