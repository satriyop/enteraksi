<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MediaSeederHelper
{
    private int $timeout = 30;

    private int $maxRetries = 2;

    private string $fixturesPath;

    public function __construct()
    {
        $this->fixturesPath = database_path('seeders/fixtures');
    }

    /**
     * Copy thumbnail from fixtures or download if not available.
     */
    public function copyThumbnail(string $fixtureSlug, string $targetFilename): ?string
    {
        $fixturePath = "{$this->fixturesPath}/thumbnails/{$fixtureSlug}.jpg";
        $targetPath = "courses/thumbnails/{$targetFilename}";

        if (file_exists($fixturePath)) {
            $this->ensureDirectoryExists($targetPath);
            Storage::disk('public')->put($targetPath, file_get_contents($fixturePath));

            return $targetPath;
        }

        return null;
    }

    /**
     * Copy video from fixtures or download if not available.
     */
    public function copyVideo(string $lessonId): ?string
    {
        $fixturePath = "{$this->fixturesPath}/videos/sample.mp4";
        $targetPath = "lessons/{$lessonId}/default/video.mp4";

        if (file_exists($fixturePath)) {
            $this->ensureDirectoryExists($targetPath);
            Storage::disk('public')->put($targetPath, file_get_contents($fixturePath));

            return $targetPath;
        }

        return null;
    }

    /**
     * Copy audio from fixtures or download if not available.
     */
    public function copyAudio(string $lessonId): ?string
    {
        $fixturePath = "{$this->fixturesPath}/audio/placeholder.mp3";
        $targetPath = "lessons/{$lessonId}/default/audio.mp3";

        if (file_exists($fixturePath)) {
            $this->ensureDirectoryExists($targetPath);
            Storage::disk('public')->put($targetPath, file_get_contents($fixturePath));

            return $targetPath;
        }

        return null;
    }

    /**
     * Copy PDF from fixtures.
     */
    public function copyPdf(string $fixtureName, string $lessonId): ?string
    {
        $fixturePath = "{$this->fixturesPath}/pdfs/{$fixtureName}.pdf";
        $targetPath = "lessons/{$lessonId}/default/document.pdf";

        if (file_exists($fixturePath)) {
            $this->ensureDirectoryExists($targetPath);
            Storage::disk('public')->put($targetPath, file_get_contents($fixturePath));

            return $targetPath;
        }

        return null;
    }

    /**
     * Load rich content from JSON fixture file.
     */
    public function loadRichContent(string $contentName): ?array
    {
        $fixturePath = "{$this->fixturesPath}/rich-content/{$contentName}.json";

        if (file_exists($fixturePath)) {
            $json = file_get_contents($fixturePath);

            return json_decode($json, true);
        }

        return null;
    }

    /**
     * Ensure directory exists for the given path.
     */
    private function ensureDirectoryExists(string $path): void
    {
        $directory = dirname($path);
        if (! Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }
    }

    public function downloadThumbnail(string $keywords, string $filename): ?string
    {
        // Use picsum.photos which is more reliable than Unsplash source
        // Generate a consistent seed from keywords for reproducible images
        $seed = crc32($keywords);
        $url = "https://picsum.photos/seed/{$seed}/800/450";
        $path = "courses/thumbnails/{$filename}";

        return $this->downloadFile($url, $path);
    }

    public function downloadVideo(string $url, string $lessonId): ?string
    {
        $path = "lessons/{$lessonId}/default/video.mp4";

        // Try the provided URL first, then fallback to W3Schools sample
        $result = $this->downloadFile($url, $path);
        if ($result === null) {
            // Fallback to W3Schools HTML5 sample video (small and reliable)
            $fallbackUrl = 'https://www.w3schools.com/html/mov_bbb.mp4';
            echo "  [*] Trying fallback video source...\n";
            $result = $this->downloadFile($fallbackUrl, $path);
        }

        return $result;
    }

    public function downloadAudio(string $url, string $lessonId): ?string
    {
        $path = "lessons/{$lessonId}/default/audio.mp3";

        // Try the provided URL first, then generate a placeholder audio file
        $result = $this->downloadFile($url, $path);
        if ($result === null) {
            echo "  [*] Generating placeholder audio file...\n";
            $result = $this->generatePlaceholderAudio($path);
        }

        return $result;
    }

    private function generatePlaceholderAudio(string $path): ?string
    {
        // Create a minimal valid MP3 file (silent 1 second)
        // This is a valid MP3 frame with silence
        $mp3Header = hex2bin(
            'fff3e4c4'.  // MP3 frame header (MPEG 1 Layer 3, 128kbps, 44100Hz, stereo)
            '00000000000000000000000000000000'. // Padding
            '00000000000000000000000000000000'.
            '00000000000000000000000000000000'.
            '00000000000000000000000000000000'.
            '00000000000000000000000000000000'.
            '00000000000000000000000000000000'.
            '00000000000000000000000000000000'.
            '00000000000000000000000000000000'
        );

        // Repeat for ~1 second of audio (about 38 frames at 128kbps)
        $audioContent = str_repeat($mp3Header, 38);

        try {
            $directory = dirname($path);
            if (! Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }
            Storage::disk('public')->put($path, $audioContent);

            return $path;
        } catch (\Exception $e) {
            echo "  [!] Failed to generate placeholder audio: {$e->getMessage()}\n";

            return null;
        }
    }

    /**
     * Generate a PDF document using DomPDF from HTML content.
     *
     * @param  string  $title  The document title
     * @param  string  $htmlContent  HTML content for the PDF body
     * @param  string  $lessonId  The lesson ID for storage path
     */
    public function generatePdf(string $title, string $htmlContent, string $lessonId): ?string
    {
        $path = "lessons/{$lessonId}/default/document.pdf";

        try {
            $fullHtml = $this->wrapHtmlForPdf($title, $htmlContent);
            $pdf = Pdf::loadHTML($fullHtml);
            $pdf->setPaper('a4', 'portrait');

            $directory = dirname($path);
            if (! Storage::disk('public')->exists($directory)) {
                Storage::disk('public')->makeDirectory($directory);
            }

            Storage::disk('public')->put($path, $pdf->output());

            return $path;
        } catch (\Exception $e) {
            echo "  [!] Failed to generate PDF: {$e->getMessage()}\n";

            return null;
        }
    }

    /**
     * Wrap HTML content in a full HTML document for PDF generation.
     */
    private function wrapHtmlForPdf(string $title, string $content): string
    {
        $date = now()->format('d F Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>{$title}</title>
    <style>
        @page {
            margin: 2cm;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 12pt;
            line-height: 1.6;
            color: #333;
        }
        h1 {
            font-size: 24pt;
            color: #1a1a1a;
            border-bottom: 2px solid #3b82f6;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        h2 {
            font-size: 18pt;
            color: #1e40af;
            margin-top: 30px;
            margin-bottom: 15px;
            page-break-after: avoid;
        }
        h3 {
            font-size: 14pt;
            color: #1e3a8a;
            margin-top: 20px;
            margin-bottom: 10px;
        }
        p {
            margin-bottom: 12px;
            text-align: justify;
        }
        ul, ol {
            margin-left: 20px;
            margin-bottom: 15px;
        }
        li {
            margin-bottom: 8px;
        }
        pre, code {
            font-family: 'DejaVu Sans Mono', monospace;
            background-color: #f3f4f6;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10pt;
        }
        pre {
            padding: 15px;
            overflow-x: auto;
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        th, td {
            border: 1px solid #d1d5db;
            padding: 10px;
            text-align: left;
        }
        th {
            background-color: #f3f4f6;
            font-weight: bold;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .date {
            color: #6b7280;
            font-size: 10pt;
            margin-bottom: 20px;
        }
        .page-break {
            page-break-before: always;
        }
        .highlight {
            background-color: #fef3c7;
            padding: 15px;
            border-left: 4px solid #f59e0b;
            margin-bottom: 15px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            width: 100%;
            text-align: center;
            font-size: 9pt;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{$title}</h1>
        <p class="date">Tanggal: {$date}</p>
    </div>

    {$content}
</body>
</html>
HTML;
    }

    public function cleanupStorage(): void
    {
        echo "Cleaning up existing seeded media...\n";

        if (Storage::disk('public')->exists('courses/thumbnails')) {
            Storage::disk('public')->deleteDirectory('courses/thumbnails');
        }

        $lessonDirs = Storage::disk('public')->directories('lessons');
        foreach ($lessonDirs as $dir) {
            Storage::disk('public')->deleteDirectory($dir);
        }

        Storage::disk('public')->makeDirectory('courses/thumbnails');
        echo "  [OK] Cleanup complete\n";
    }

    private function downloadFile(string $url, string $path): ?string
    {
        $directory = dirname($path);
        if (! Storage::disk('public')->exists($directory)) {
            Storage::disk('public')->makeDirectory($directory);
        }

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withOptions(['allow_redirects' => true])
                    ->get($url);

                if ($response->successful()) {
                    Storage::disk('public')->put($path, $response->body());

                    return $path;
                }

                echo "  [!] Attempt {$attempt}: HTTP {$response->status()} for {$url}\n";
            } catch (\Exception $e) {
                echo "  [!] Attempt {$attempt}: {$e->getMessage()}\n";
            }

            if ($attempt < $this->maxRetries) {
                sleep(1);
            }
        }

        return null;
    }

    public function getFileSize(string $path): int
    {
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->size($path);
        }

        return 0;
    }

    public function getMimeType(string $path): string
    {
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }
}
