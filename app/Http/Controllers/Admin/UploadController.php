<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class UploadController extends Controller
{
    public function uploadImage(Request $request): JsonResponse
    {
        if (!$request->hasFile('image')) {
            throw ValidationException::withMessages([
                'image' => ['No image file provided.'],
            ]);
        }

        $file = $request->file('image');

        if (!$file->isValid()) {
            throw ValidationException::withMessages([
                'image' => ['The uploaded file is not valid.'],
            ]);
        }

        // Validate file type
        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,svg,webp', 'max:5120'], // max 5MB
        ]);

        $folder = $request->input('folder', 'uploads');
        // Ensure folder is safe
        $folder = preg_replace('/[^a-zA-Z0-9_-]/', '', $folder) ?: 'uploads';

        $filename = Str::random(40) . '.' . $file->getClientOriginalExtension();
        
        // Store in public disk
        $path = $file->storeAs($folder, $filename, 'public');

        return response()->json([
            'url' => Storage::disk('public')->url($path),
            'path' => $path,
        ], 201);
    }
}
