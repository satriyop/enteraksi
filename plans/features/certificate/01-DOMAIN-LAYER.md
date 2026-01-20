# Phase 1: Domain Layer

> **Phase**: 1 of 5
> **Estimated Effort**: Medium
> **Prerequisites**: None

---

## Objectives

- Create domain contracts (interfaces)
- Implement CertificateService
- Create DTOs for data transfer
- Define domain events
- Set up event listeners for auto-issuance

## Directory Structure

```
app/Domain/Certificate/
├── Contracts/
│   ├── CertificateServiceContract.php
│   └── CertificateGeneratorContract.php
├── Services/
│   ├── CertificateService.php
│   └── CertificateNumberGenerator.php
├── DTOs/
│   ├── CertificateData.php
│   └── IssueCertificateDTO.php
├── Events/
│   ├── CertificateIssued.php
│   └── CertificateRevoked.php
├── Listeners/
│   └── IssueCertificateOnCompletion.php
├── Exceptions/
│   ├── CertificateAlreadyExistsException.php
│   ├── CertificateNotFoundException.php
│   └── CertificateRevokedException.php
└── ValueObjects/
    └── CertificateNumber.php
```

---

## 1.1 Contracts

### CertificateServiceContract

```php
<?php

namespace App\Domain\Certificate\Contracts;

use App\Domain\Certificate\DTOs\CertificateData;
use App\Domain\Certificate\DTOs\IssueCertificateDTO;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;

interface CertificateServiceContract
{
    /**
     * Issue a certificate for a completed enrollment.
     */
    public function issue(Enrollment $enrollment): Certificate;

    /**
     * Issue certificate with custom data (manual issuance).
     */
    public function issueManual(IssueCertificateDTO $dto): Certificate;

    /**
     * Revoke a certificate.
     */
    public function revoke(Certificate $certificate, User $revokedBy, string $reason): Certificate;

    /**
     * Verify a certificate by its number.
     * Returns null if not found or revoked.
     */
    public function verify(string $certificateNumber): ?CertificateData;

    /**
     * Check if enrollment already has a certificate.
     */
    public function hasCertificate(Enrollment $enrollment): bool;

    /**
     * Get all certificates for a user.
     */
    public function getUserCertificates(User $user): Collection;

    /**
     * Regenerate PDF for existing certificate.
     */
    public function regenerate(Certificate $certificate): Certificate;
}
```

### CertificateGeneratorContract

```php
<?php

namespace App\Domain\Certificate\Contracts;

use App\Models\Certificate;

interface CertificateGeneratorContract
{
    /**
     * Generate PDF binary for certificate.
     */
    public function generatePdf(Certificate $certificate): string;

    /**
     * Get the Blade template name for the course.
     */
    public function getTemplateName(Certificate $certificate): string;

    /**
     * Generate certificate data array for template.
     */
    public function getCertificateData(Certificate $certificate): array;
}
```

---

## 1.2 DTOs

### CertificateData (Read DTO)

```php
<?php

namespace App\Domain\Certificate\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;
use Carbon\Carbon;

final class CertificateData extends DataTransferObject
{
    public function __construct(
        public readonly int $id,
        public readonly string $certificateNumber,
        public readonly string $learnerName,
        public readonly string $learnerEmail,
        public readonly string $courseTitle,
        public readonly string $courseSlug,
        public readonly Carbon $issuedAt,
        public readonly ?Carbon $expiresAt,
        public readonly bool $isValid,
        public readonly ?string $revocationReason,
        public readonly ?array $metadata,
    ) {}

    public static function fromCertificate(Certificate $certificate): self
    {
        return new self(
            id: $certificate->id,
            certificateNumber: $certificate->certificate_number,
            learnerName: $certificate->user->name,
            learnerEmail: $certificate->user->email,
            courseTitle: $certificate->course->title,
            courseSlug: $certificate->course->slug,
            issuedAt: $certificate->issued_at,
            expiresAt: $certificate->expires_at,
            isValid: $certificate->isValid(),
            revocationReason: $certificate->revocation_reason,
            metadata: $certificate->metadata,
        );
    }
}
```

### IssueCertificateDTO (Write DTO)

```php
<?php

namespace App\Domain\Certificate\DTOs;

use App\Domain\Shared\DTOs\DataTransferObject;
use Carbon\Carbon;

final class IssueCertificateDTO extends DataTransferObject
{
    public function __construct(
        public readonly int $userId,
        public readonly int $courseId,
        public readonly ?int $enrollmentId = null,
        public readonly ?Carbon $expiresAt = null,
        public readonly ?array $metadata = null,
    ) {}
}
```

---

## 1.3 Value Objects

### CertificateNumber

```php
<?php

namespace App\Domain\Certificate\ValueObjects;

use InvalidArgumentException;

final class CertificateNumber
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $value;
    }

    /**
     * Generate a new certificate number.
     * Format: CERT-{YEAR}-{RANDOM6}
     * Example: CERT-2026-A1B2C3
     */
    public static function generate(): self
    {
        $year = date('Y');
        $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

        return new self("CERT-{$year}-{$random}");
    }

    /**
     * Create from existing string (for database hydration).
     */
    public static function fromString(string $value): self
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException("Invalid certificate number format: {$value}");
        }

        return new self($value);
    }

    /**
     * Validate certificate number format.
     */
    public static function isValid(string $value): bool
    {
        return preg_match('/^CERT-\d{4}-[A-Z0-9]{6}$/', $value) === 1;
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(CertificateNumber $other): bool
    {
        return $this->value === $other->value;
    }
}
```

---

## 1.4 Service Implementation

### CertificateService

```php
<?php

namespace App\Domain\Certificate\Services;

use App\Domain\Certificate\Contracts\CertificateServiceContract;
use App\Domain\Certificate\DTOs\CertificateData;
use App\Domain\Certificate\DTOs\IssueCertificateDTO;
use App\Domain\Certificate\Events\CertificateIssued;
use App\Domain\Certificate\Events\CertificateRevoked;
use App\Domain\Certificate\Exceptions\CertificateAlreadyExistsException;
use App\Domain\Certificate\ValueObjects\CertificateNumber;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CertificateService implements CertificateServiceContract
{
    public function issue(Enrollment $enrollment): Certificate
    {
        // Check if certificate already exists
        if ($this->hasCertificate($enrollment)) {
            throw new CertificateAlreadyExistsException($enrollment);
        }

        // Verify enrollment is completed
        if ($enrollment->status !== 'completed') {
            throw new \DomainException('Cannot issue certificate for incomplete enrollment');
        }

        return DB::transaction(function () use ($enrollment) {
            $certificate = Certificate::create([
                'user_id' => $enrollment->user_id,
                'course_id' => $enrollment->course_id,
                'enrollment_id' => $enrollment->id,
                'certificate_number' => CertificateNumber::generate()->toString(),
                'issued_at' => now(),
                'expires_at' => $this->calculateExpiryDate($enrollment),
                'metadata' => $this->buildMetadata($enrollment),
            ]);

            event(new CertificateIssued($certificate));

            return $certificate;
        });
    }

    public function issueManual(IssueCertificateDTO $dto): Certificate
    {
        return DB::transaction(function () use ($dto) {
            $certificate = Certificate::create([
                'user_id' => $dto->userId,
                'course_id' => $dto->courseId,
                'enrollment_id' => $dto->enrollmentId,
                'certificate_number' => CertificateNumber::generate()->toString(),
                'issued_at' => now(),
                'expires_at' => $dto->expiresAt,
                'metadata' => $dto->metadata ?? [],
            ]);

            event(new CertificateIssued($certificate));

            return $certificate;
        });
    }

    public function revoke(Certificate $certificate, User $revokedBy, string $reason): Certificate
    {
        $certificate->update([
            'revoked_at' => now(),
            'revoked_by' => $revokedBy->id,
            'revocation_reason' => $reason,
        ]);

        event(new CertificateRevoked($certificate, $revokedBy, $reason));

        return $certificate->fresh();
    }

    public function verify(string $certificateNumber): ?CertificateData
    {
        $certificate = Certificate::where('certificate_number', $certificateNumber)
            ->with(['user', 'course'])
            ->first();

        if (!$certificate) {
            return null;
        }

        return CertificateData::fromCertificate($certificate);
    }

    public function hasCertificate(Enrollment $enrollment): bool
    {
        return Certificate::where('enrollment_id', $enrollment->id)
            ->whereNull('revoked_at')
            ->exists();
    }

    public function getUserCertificates(User $user): Collection
    {
        return Certificate::where('user_id', $user->id)
            ->with(['course'])
            ->orderByDesc('issued_at')
            ->get();
    }

    public function regenerate(Certificate $certificate): Certificate
    {
        // PDF regeneration is handled by CertificateGenerator
        // This method just updates the timestamp for cache busting
        $certificate->touch();

        return $certificate;
    }

    /**
     * Calculate expiry date based on course settings.
     * Some compliance courses may require annual recertification.
     */
    private function calculateExpiryDate(Enrollment $enrollment): ?\Carbon\Carbon
    {
        // Check if course has expiry settings in metadata
        $courseMetadata = $enrollment->course->metadata ?? [];

        if (isset($courseMetadata['certificate_validity_months'])) {
            return now()->addMonths($courseMetadata['certificate_validity_months']);
        }

        return null; // No expiry
    }

    /**
     * Build metadata for certificate.
     */
    private function buildMetadata(Enrollment $enrollment): array
    {
        return [
            'completion_date' => $enrollment->completed_at?->toISOString(),
            'progress_percentage' => $enrollment->progress_percentage,
            'enrolled_at' => $enrollment->enrolled_at?->toISOString(),
            'course_version' => $enrollment->course->updated_at?->toISOString(),
        ];
    }
}
```

---

## 1.5 Events

### CertificateIssued

```php
<?php

namespace App\Domain\Certificate\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\Certificate;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CertificateIssued implements DomainEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Certificate $certificate
    ) {}

    public function getEventName(): string
    {
        return 'certificate.issued';
    }

    public function getPayload(): array
    {
        return [
            'certificate_id' => $this->certificate->id,
            'certificate_number' => $this->certificate->certificate_number,
            'user_id' => $this->certificate->user_id,
            'course_id' => $this->certificate->course_id,
            'issued_at' => $this->certificate->issued_at,
        ];
    }
}
```

### CertificateRevoked

```php
<?php

namespace App\Domain\Certificate\Events;

use App\Domain\Shared\Contracts\DomainEvent;
use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CertificateRevoked implements DomainEvent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Certificate $certificate,
        public readonly User $revokedBy,
        public readonly string $reason
    ) {}

    public function getEventName(): string
    {
        return 'certificate.revoked';
    }

    public function getPayload(): array
    {
        return [
            'certificate_id' => $this->certificate->id,
            'certificate_number' => $this->certificate->certificate_number,
            'revoked_by' => $this->revokedBy->id,
            'reason' => $this->reason,
            'revoked_at' => $this->certificate->revoked_at,
        ];
    }
}
```

---

## 1.6 Listener (Auto-Issuance)

### IssueCertificateOnCompletion

```php
<?php

namespace App\Domain\Certificate\Listeners;

use App\Domain\Certificate\Contracts\CertificateServiceContract;
use App\Domain\Certificate\Exceptions\CertificateAlreadyExistsException;
use App\Domain\Enrollment\Events\EnrollmentCompleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

class IssueCertificateOnCompletion implements ShouldQueue
{
    public function __construct(
        private CertificateServiceContract $certificateService
    ) {}

    public function handle(EnrollmentCompleted $event): void
    {
        try {
            $certificate = $this->certificateService->issue($event->enrollment);

            Log::info('Certificate issued', [
                'certificate_number' => $certificate->certificate_number,
                'user_id' => $event->enrollment->user_id,
                'course_id' => $event->enrollment->course_id,
            ]);
        } catch (CertificateAlreadyExistsException $e) {
            Log::info('Certificate already exists for enrollment', [
                'enrollment_id' => $event->enrollment->id,
            ]);
        }
    }

    public function failed(EnrollmentCompleted $event, \Throwable $exception): void
    {
        Log::error('Failed to issue certificate', [
            'enrollment_id' => $event->enrollment->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

---

## 1.7 Exceptions

### CertificateAlreadyExistsException

```php
<?php

namespace App\Domain\Certificate\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;
use App\Models\Enrollment;

class CertificateAlreadyExistsException extends DomainException
{
    public function __construct(Enrollment $enrollment)
    {
        parent::__construct(
            "Certificate already exists for enrollment {$enrollment->id}"
        );
    }
}
```

### CertificateNotFoundException

```php
<?php

namespace App\Domain\Certificate\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

class CertificateNotFoundException extends DomainException
{
    public function __construct(string $certificateNumber)
    {
        parent::__construct(
            "Certificate not found: {$certificateNumber}"
        );
    }
}
```

---

## 1.8 Service Provider Registration

Add to `AppServiceProvider`:

```php
// In register() method
$this->app->bind(
    \App\Domain\Certificate\Contracts\CertificateServiceContract::class,
    \App\Domain\Certificate\Services\CertificateService::class
);

$this->app->bind(
    \App\Domain\Certificate\Contracts\CertificateGeneratorContract::class,
    \App\Domain\Certificate\Services\CertificateGenerator::class
);
```

Add to `EventServiceProvider`:

```php
protected $listen = [
    // ... existing listeners

    \App\Domain\Enrollment\Events\EnrollmentCompleted::class => [
        \App\Domain\Enrollment\Listeners\SendCompletionCongratulations::class,
        \App\Domain\Certificate\Listeners\IssueCertificateOnCompletion::class, // NEW
    ],
];
```

---

## Implementation Checklist

- [ ] Create directory structure
- [ ] Create `CertificateServiceContract`
- [ ] Create `CertificateGeneratorContract`
- [ ] Create `CertificateData` DTO
- [ ] Create `IssueCertificateDTO`
- [ ] Create `CertificateNumber` value object
- [ ] Implement `CertificateService`
- [ ] Create `CertificateIssued` event
- [ ] Create `CertificateRevoked` event
- [ ] Create `IssueCertificateOnCompletion` listener
- [ ] Create exception classes
- [ ] Register bindings in `AppServiceProvider`
- [ ] Register listener in `EventServiceProvider`
- [ ] Write unit tests for service
- [ ] Write unit tests for value objects

---

## Next Phase

Continue to [Phase 2: Database and Model](./02-DATABASE-AND-MODEL.md)
