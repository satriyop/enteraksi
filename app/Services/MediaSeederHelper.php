<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class MediaSeederHelper
{
    private int $timeout = 30;

    private int $maxRetries = 2;

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

    public function generatePdf(string $title, string $content, string $lessonId): ?string
    {
        $path = "lessons/{$lessonId}/default/document.pdf";

        $pdfContent = $this->createSimplePdf($title, $content);

        try {
            Storage::disk('public')->put($path, $pdfContent);

            return $path;
        } catch (\Exception $e) {
            echo "  [!] Failed to generate PDF: {$e->getMessage()}\n";

            return null;
        }
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

    private function createSimplePdf(string $title, string $content): string
    {
        $date = now()->format('d F Y');

        $pdf = "%PDF-1.4\n";
        $pdf .= "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n";
        $pdf .= "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n";
        $pdf .= "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n";

        $textContent = "BT\n";
        $textContent .= "/F1 18 Tf\n";
        $textContent .= "50 742 Td\n";
        $textContent .= "({$title}) Tj\n";
        $textContent .= "/F1 10 Tf\n";
        $textContent .= "0 -30 Td\n";
        $textContent .= "(Tanggal: {$date}) Tj\n";
        $textContent .= "0 -40 Td\n";
        $textContent .= "/F1 12 Tf\n";

        $lines = explode("\n", wordwrap($content, 80, "\n", true));
        foreach ($lines as $line) {
            $escapedLine = str_replace(['(', ')', '\\'], ['\\(', '\\)', '\\\\'], $line);
            $textContent .= "0 -18 Td\n";
            $textContent .= "({$escapedLine}) Tj\n";
        }

        $textContent .= 'ET';

        $streamLength = strlen($textContent);
        $pdf .= "4 0 obj << /Length {$streamLength} >> stream\n{$textContent}\nendstream endobj\n";
        $pdf .= "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\n";
        $pdf .= "xref\n0 6\n";
        $pdf .= "0000000000 65535 f \n";
        $pdf .= "0000000009 00000 n \n";
        $pdf .= "0000000058 00000 n \n";
        $pdf .= "0000000115 00000 n \n";
        $pdf .= sprintf("0000000270 00000 n \n");
        $pdf .= sprintf("%010d 00000 n \n", 270 + $streamLength + 50);
        $pdf .= "trailer << /Size 6 /Root 1 0 R >>\n";
        $pdf .= "startxref\n";
        $pdf .= (string) (350 + $streamLength);
        $pdf .= "\n%%EOF";

        return $pdf;
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
