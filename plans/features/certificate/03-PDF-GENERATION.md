# Phase 3: PDF Generation

> **Phase**: 3 of 5
> **Estimated Effort**: Medium
> **Prerequisites**: Phase 1 (Domain Layer), Phase 2 (Database)

---

## Objectives

- Install and configure PDF generation library
- Create certificate template (Blade)
- Implement CertificateGenerator service
- Support multiple template styles
- Handle PDF caching for performance

---

## 3.1 Install Dependencies

```bash
composer require barryvdh/laravel-dompdf
```

### Publish Config (Optional)

```bash
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

### Configuration

```php
// config/dompdf.php (key settings)
return [
    'show_warnings' => false,
    'orientation' => 'landscape',  // Certificates are typically landscape
    'default_paper_size' => 'a4',
    'default_font' => 'sans-serif',
    'isRemoteEnabled' => true,  // Allow loading images

    'options' => [
        'isHtml5ParserEnabled' => true,
        'isPhpEnabled' => false,  // Security: disable PHP in templates
    ],
];
```

---

## 3.2 CertificateGenerator Service

### File: `app/Domain/Certificate/Services/CertificateGenerator.php`

```php
<?php

namespace App\Domain\Certificate\Services;

use App\Domain\Certificate\Contracts\CertificateGeneratorContract;
use App\Models\Certificate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CertificateGenerator implements CertificateGeneratorContract
{
    /**
     * Cache TTL for generated PDFs (24 hours).
     */
    private const CACHE_TTL = 60 * 60 * 24;

    public function generatePdf(Certificate $certificate): string
    {
        $cacheKey = $this->getCacheKey($certificate);

        // Return cached PDF if exists and certificate hasn't changed
        if ($cached = $this->getCachedPdf($certificate, $cacheKey)) {
            return $cached;
        }

        // Generate new PDF
        $pdf = Pdf::loadView(
            $this->getTemplateName($certificate),
            $this->getCertificateData($certificate)
        )
            ->setPaper('a4', 'landscape')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'dpi' => 150,
            ]);

        $pdfContent = $pdf->output();

        // Cache the PDF
        $this->cachePdf($cacheKey, $pdfContent, $certificate);

        return $pdfContent;
    }

    public function getTemplateName(Certificate $certificate): string
    {
        // Check for course-specific template
        $courseTemplate = "certificates.templates.course-{$certificate->course_id}";

        if (view()->exists($courseTemplate)) {
            return $courseTemplate;
        }

        // Check for category-specific template
        $category = $certificate->course->category;
        if ($category) {
            $categoryTemplate = "certificates.templates.category-{$category->slug}";
            if (view()->exists($categoryTemplate)) {
                return $categoryTemplate;
            }
        }

        // Default template
        return 'certificates.templates.default';
    }

    public function getCertificateData(Certificate $certificate): array
    {
        $certificate->load(['user', 'course', 'course.category']);

        return [
            'certificate' => $certificate,
            'learner' => [
                'name' => $certificate->user->name,
                'email' => $certificate->user->email,
            ],
            'course' => [
                'title' => $certificate->course->title,
                'category' => $certificate->course->category?->name,
                'duration' => $certificate->course->duration,
            ],
            'dates' => [
                'issued' => $certificate->issued_at->translatedFormat('d F Y'),
                'expires' => $certificate->expires_at?->translatedFormat('d F Y'),
            ],
            'certificateNumber' => $certificate->certificate_number,
            'verificationUrl' => $certificate->verification_url,
            'isValid' => $certificate->isValid(),
            'metadata' => $certificate->metadata ?? [],
            'qrCodeUrl' => $this->generateQrCodeUrl($certificate),
        ];
    }

    /**
     * Generate QR code URL for verification.
     */
    private function generateQrCodeUrl(Certificate $certificate): string
    {
        $verificationUrl = urlencode($certificate->verification_url);

        // Using Google Charts API for QR code (free, no dependencies)
        return "https://chart.googleapis.com/chart?chs=150x150&cht=qr&chl={$verificationUrl}&choe=UTF-8";
    }

    /**
     * Get cache key for certificate PDF.
     */
    private function getCacheKey(Certificate $certificate): string
    {
        return "certificate_pdf_{$certificate->id}_{$certificate->updated_at->timestamp}";
    }

    /**
     * Get cached PDF if valid.
     */
    private function getCachedPdf(Certificate $certificate, string $cacheKey): ?string
    {
        // Don't cache revoked certificates
        if ($certificate->isRevoked()) {
            return null;
        }

        return Cache::get($cacheKey);
    }

    /**
     * Cache generated PDF.
     */
    private function cachePdf(string $cacheKey, string $content, Certificate $certificate): void
    {
        // Don't cache revoked certificates
        if ($certificate->isRevoked()) {
            return;
        }

        Cache::put($cacheKey, $content, self::CACHE_TTL);
    }

    /**
     * Clear cached PDF for a certificate.
     */
    public function clearCache(Certificate $certificate): void
    {
        $pattern = "certificate_pdf_{$certificate->id}_*";
        Cache::forget($this->getCacheKey($certificate));
    }
}
```

---

## 3.3 Certificate Template (Default)

### File: `resources/views/certificates/templates/default.blade.php`

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Sertifikat - {{ $certificate->certificate_number }}</title>
    <style>
        @page {
            margin: 0;
            padding: 0;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            width: 297mm;
            height: 210mm;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: relative;
        }

        .certificate-container {
            width: 100%;
            height: 100%;
            padding: 15mm;
            position: relative;
        }

        .certificate-inner {
            width: 100%;
            height: 100%;
            background: white;
            border-radius: 10px;
            padding: 20mm;
            position: relative;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }

        /* Decorative border */
        .border-decoration {
            position: absolute;
            top: 5mm;
            left: 5mm;
            right: 5mm;
            bottom: 5mm;
            border: 2px solid #667eea;
            border-radius: 5px;
            pointer-events: none;
        }

        /* Header section */
        .header {
            text-align: center;
            margin-bottom: 10mm;
        }

        .header-title {
            font-size: 14pt;
            color: #667eea;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 5mm;
        }

        .main-title {
            font-size: 32pt;
            color: #1a1a2e;
            font-weight: bold;
            margin-bottom: 3mm;
        }

        .subtitle {
            font-size: 12pt;
            color: #666;
        }

        /* Body section */
        .body {
            text-align: center;
            margin-bottom: 10mm;
        }

        .presented-to {
            font-size: 11pt;
            color: #888;
            margin-bottom: 3mm;
        }

        .learner-name {
            font-size: 28pt;
            color: #1a1a2e;
            font-weight: bold;
            margin-bottom: 5mm;
            border-bottom: 2px solid #667eea;
            display: inline-block;
            padding: 0 10mm 2mm 10mm;
        }

        .completion-text {
            font-size: 11pt;
            color: #666;
            margin-bottom: 3mm;
        }

        .course-title {
            font-size: 18pt;
            color: #667eea;
            font-weight: bold;
            margin-bottom: 5mm;
        }

        .course-category {
            font-size: 10pt;
            color: #888;
        }

        /* Footer section */
        .footer {
            position: absolute;
            bottom: 25mm;
            left: 25mm;
            right: 25mm;
            display: table;
            width: calc(100% - 50mm);
        }

        .footer-left,
        .footer-center,
        .footer-right {
            display: table-cell;
            vertical-align: bottom;
        }

        .footer-left {
            width: 30%;
            text-align: left;
        }

        .footer-center {
            width: 40%;
            text-align: center;
        }

        .footer-right {
            width: 30%;
            text-align: right;
        }

        .date-section {
            font-size: 9pt;
            color: #666;
        }

        .date-label {
            color: #888;
            margin-bottom: 1mm;
        }

        .date-value {
            color: #333;
            font-weight: bold;
        }

        .qr-section {
            text-align: center;
        }

        .qr-code {
            width: 25mm;
            height: 25mm;
            margin-bottom: 2mm;
        }

        .verification-text {
            font-size: 7pt;
            color: #888;
        }

        .certificate-number {
            font-size: 8pt;
            color: #667eea;
            font-weight: bold;
        }

        .signature-section {
            text-align: right;
        }

        .signature-line {
            border-top: 1px solid #333;
            width: 50mm;
            margin-left: auto;
            padding-top: 2mm;
        }

        .signature-name {
            font-size: 10pt;
            font-weight: bold;
            color: #333;
        }

        .signature-title {
            font-size: 8pt;
            color: #666;
        }

        /* Watermark for invalid certificates */
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-30deg);
            font-size: 72pt;
            color: rgba(255, 0, 0, 0.15);
            font-weight: bold;
            text-transform: uppercase;
            pointer-events: none;
            z-index: 10;
        }
    </style>
</head>
<body>
    <div class="certificate-container">
        <div class="certificate-inner">
            <div class="border-decoration"></div>

            @if(!$isValid)
                <div class="watermark">
                    @if($certificate->isRevoked())
                        DICABUT
                    @elseif($certificate->isExpired())
                        KADALUARSA
                    @endif
                </div>
            @endif

            <!-- Header -->
            <div class="header">
                <div class="header-title">Enteraksi Learning Management System</div>
                <div class="main-title">SERTIFIKAT</div>
                <div class="subtitle">Penyelesaian Kursus</div>
            </div>

            <!-- Body -->
            <div class="body">
                <div class="presented-to">Dengan ini menyatakan bahwa</div>
                <div class="learner-name">{{ $learner['name'] }}</div>
                <div class="completion-text">telah berhasil menyelesaikan kursus</div>
                <div class="course-title">{{ $course['title'] }}</div>
                @if($course['category'])
                    <div class="course-category">Kategori: {{ $course['category'] }}</div>
                @endif
            </div>

            <!-- Footer -->
            <div class="footer">
                <div class="footer-left">
                    <div class="date-section">
                        <div class="date-label">Tanggal Terbit</div>
                        <div class="date-value">{{ $dates['issued'] }}</div>
                        @if($dates['expires'])
                            <div class="date-label" style="margin-top: 3mm;">Berlaku Hingga</div>
                            <div class="date-value">{{ $dates['expires'] }}</div>
                        @endif
                    </div>
                </div>

                <div class="footer-center">
                    <div class="qr-section">
                        <img src="{{ $qrCodeUrl }}" alt="QR Code" class="qr-code">
                        <div class="verification-text">Verifikasi di:</div>
                        <div class="certificate-number">{{ $certificateNumber }}</div>
                    </div>
                </div>

                <div class="footer-right">
                    <div class="signature-section">
                        <div class="signature-line">
                            <div class="signature-name">Administrator LMS</div>
                            <div class="signature-title">Enteraksi</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
```

---

## 3.4 Alternative Template (Compliance/OJK)

### File: `resources/views/certificates/templates/category-compliance.blade.php`

```blade
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Sertifikat Kepatuhan - {{ $certificate->certificate_number }}</title>
    <style>
        /* Similar base styles, but with compliance-focused design */
        body {
            font-family: 'DejaVu Sans', sans-serif;
            width: 297mm;
            height: 210mm;
            background: #1a365d;
            position: relative;
        }

        .certificate-inner {
            background: linear-gradient(to bottom, #ffffff 0%, #f7fafc 100%);
            /* ... similar structure with formal/compliance styling */
        }

        /* Gold accents for compliance certificates */
        .main-title {
            color: #744210;
            border-bottom: 3px double #d69e2e;
        }

        .header-title {
            color: #744210;
        }

        /* Add compliance badge */
        .compliance-badge {
            position: absolute;
            top: 15mm;
            right: 15mm;
            width: 30mm;
            text-align: center;
        }

        .compliance-badge img {
            width: 25mm;
            height: 25mm;
        }

        .compliance-text {
            font-size: 7pt;
            color: #744210;
            margin-top: 2mm;
        }
    </style>
</head>
<body>
    <!-- Similar structure with compliance-specific elements -->
    <div class="certificate-container">
        <div class="certificate-inner">
            <!-- Compliance badge -->
            <div class="compliance-badge">
                {{-- OJK or compliance logo could go here --}}
                <div class="compliance-text">Memenuhi Standar<br>Kepatuhan OJK</div>
            </div>

            <!-- Rest similar to default template -->
            <!-- ... -->
        </div>
    </div>
</body>
</html>
```

---

## 3.5 PDF Download Filename Helper

### Add to CertificateGenerator

```php
/**
 * Generate download filename for certificate.
 */
public function getDownloadFilename(Certificate $certificate): string
{
    $learnerName = Str::slug($certificate->user->name);
    $courseSlug = Str::slug($certificate->course->title);
    $date = $certificate->issued_at->format('Y-m-d');

    return "sertifikat-{$courseSlug}-{$learnerName}-{$date}.pdf";
}
```

---

## 3.6 Font Considerations

For Indonesian language support, DomPDF uses DejaVu fonts by default which support UTF-8. If custom fonts are needed:

```php
// In config/dompdf.php
'font_dir' => storage_path('fonts'),
'font_cache' => storage_path('fonts'),

// Register custom fonts programmatically if needed
```

---

## 3.7 Image Handling

For logos and images in certificates:

```blade
{{-- Option 1: Base64 encoded (most reliable) --}}
<img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/logo.png'))) }}" />

{{-- Option 2: Absolute URL (requires isRemoteEnabled) --}}
<img src="{{ asset('images/logo.png') }}" />

{{-- Option 3: File path (for local images) --}}
<img src="{{ public_path('images/logo.png') }}" />
```

---

## Implementation Checklist

- [ ] Install barryvdh/laravel-dompdf
- [ ] Configure dompdf settings
- [ ] Create CertificateGenerator service
- [ ] Implement PDF caching
- [ ] Create default Blade template
- [ ] Create compliance template variant
- [ ] Test Indonesian character rendering
- [ ] Test QR code generation
- [ ] Test PDF output quality
- [ ] Optimize PDF file size

---

## Testing PDF Generation

```php
// In tinker or test
$certificate = Certificate::factory()->create();
$generator = app(CertificateGeneratorContract::class);

// Generate PDF
$pdf = $generator->generatePdf($certificate);
file_put_contents(storage_path('test-certificate.pdf'), $pdf);

// Check file
// Open storage/test-certificate.pdf to verify output
```

---

## Performance Considerations

| Concern | Solution |
|---------|----------|
| PDF generation is slow (~1-3s) | Cache generated PDFs |
| Large file size | Use 150 DPI, optimize images |
| Memory usage | Generate one at a time, not batch |
| Concurrent requests | Queue PDF generation for bulk |

---

## Next Phase

Continue to [Phase 4: Controllers and Routes](./04-CONTROLLERS-AND-ROUTES.md)
