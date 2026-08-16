<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Upload extends Model
{
    protected $fillable = [
        'user_id',
        'disk',
        'path',
        'original_name',
        'mime',
        'size',
        'width',
        'height',
    ];

    protected $casts = [
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    // relasi ke user yang upload
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // url publik untuk file
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }
}