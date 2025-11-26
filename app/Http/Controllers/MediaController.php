<?php

namespace App\Http\Controllers;

use App\Http\Requests\Media\StoreMediaRequest;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    /**
     * Store a newly created media resource.
     */
    public function store(StoreMediaRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Get the mediable model
        $mediable = $this->getMediable($validated['mediable_type'], $validated['mediable_id']);

        if (! $mediable) {
            return response()->json([
                'message' => 'Model tidak ditemukan.',
            ], 404);
        }

        // Check authorization
        if ($mediable instanceof Course) {
            Gate::authorize('update', $mediable);
        } elseif ($mediable instanceof Lesson) {
            Gate::authorize('update', $mediable);
        }

        $file = $request->file('file');
        $collection = $validated['collection_name'] ?? 'default';

        // Determine the storage path based on type
        $path = match ($validated['mediable_type']) {
            'course' => "courses/{$mediable->id}/{$collection}",
            'lesson' => "lessons/{$mediable->id}/{$collection}",
            default => 'uploads',
        };

        // Store the file
        $storedPath = $file->store($path, 'public');

        // Get duration for video/audio files
        $durationSeconds = null;
        $mimeType = $file->getMimeType();
        if (str_starts_with($mimeType, 'video/') || str_starts_with($mimeType, 'audio/')) {
            $durationSeconds = $this->getMediaDuration(Storage::disk('public')->path($storedPath));
        }

        // Create media record
        $media = $mediable->media()->create([
            'collection_name' => $collection,
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $mimeType,
            'disk' => 'public',
            'path' => $storedPath,
            'size' => $file->getSize(),
            'duration_seconds' => $durationSeconds,
            'order_column' => $mediable->media()->where('collection_name', $collection)->count(),
        ]);

        // Update lesson's estimated duration if this is a video/audio for a lesson
        if ($mediable instanceof Lesson && $durationSeconds) {
            $durationMinutes = (int) ceil($durationSeconds / 60);
            if (! $mediable->estimated_duration_minutes || $mediable->estimated_duration_minutes < $durationMinutes) {
                $mediable->update(['estimated_duration_minutes' => $durationMinutes]);
            }
        }

        return response()->json([
            'message' => 'File berhasil diunggah.',
            'media' => [
                'id' => $media->id,
                'name' => $media->name,
                'file_name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'human_readable_size' => $media->human_readable_size,
                'url' => $media->url,
                'duration_seconds' => $media->duration_seconds,
                'duration_formatted' => $media->duration_formatted,
                'is_image' => $media->is_image,
                'is_video' => $media->is_video,
                'is_audio' => $media->is_audio,
                'is_document' => $media->is_document,
            ],
        ], 201);
    }

    /**
     * Remove the specified media resource.
     */
    public function destroy(Media $media): JsonResponse
    {
        $mediable = $media->mediable;

        // Check authorization
        if ($mediable instanceof Course) {
            Gate::authorize('update', $mediable);
        } elseif ($mediable instanceof Lesson) {
            Gate::authorize('update', $mediable);
        }

        // Delete file from storage
        Storage::disk($media->disk)->delete($media->path);

        // Delete media record
        $media->delete();

        return response()->json([
            'message' => 'File berhasil dihapus.',
        ]);
    }

    /**
     * Get the mediable model based on type and ID.
     */
    private function getMediable(string $type, int $id): Course|Lesson|null
    {
        return match ($type) {
            'course' => Course::find($id),
            'lesson' => Lesson::find($id),
            default => null,
        };
    }

    /**
     * Get duration of video/audio file using ffprobe if available.
     */
    private function getMediaDuration(string $filePath): ?int
    {
        // Try using ffprobe to get duration
        $ffprobe = trim(shell_exec('which ffprobe 2>/dev/null') ?? '');

        if ($ffprobe && file_exists($filePath)) {
            $command = sprintf(
                '%s -v error -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
                escapeshellcmd($ffprobe),
                escapeshellarg($filePath)
            );

            $output = shell_exec($command);
            if ($output) {
                $duration = (float) trim($output);
                if ($duration > 0) {
                    return (int) round($duration);
                }
            }
        }

        return null;
    }
}
