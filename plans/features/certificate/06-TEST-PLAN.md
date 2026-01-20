# Phase 6: Test Plan

> **Phase**: 6 of 6
> **Estimated Effort**: High
> **Prerequisites**: Phase 1-5

---

## Overview

This document outlines comprehensive testing for the Certificate Module, covering:
- Unit tests for domain layer
- Feature tests for controllers
- Edge case tests
- Integration tests

---

## Test Structure

```
tests/
├── Unit/
│   └── Domain/
│       └── Certificate/
│           ├── Services/
│           │   ├── CertificateServiceTest.php
│           │   └── CertificateGeneratorTest.php
│           ├── ValueObjects/
│           │   └── CertificateNumberTest.php
│           └── DTOs/
│               └── CertificateDataTest.php
├── Feature/
│   ├── Certificate/
│   │   ├── CertificateIssuanceTest.php
│   │   ├── CertificateDownloadTest.php
│   │   ├── CertificateVerificationTest.php
│   │   ├── CertificateRevocationTest.php
│   │   └── CertificateAutoIssuanceTest.php
│   └── Admin/
│       └── Certificate/
│           ├── AdminCertificateListTest.php
│           ├── AdminCertificateIssueTest.php
│           └── AdminCertificateRevokeTest.php
└── Integration/
    └── Certificate/
        └── CertificateLifecycleTest.php
```

---

## Unit Tests

### 1. CertificateServiceTest

```php
<?php

use App\Domain\Certificate\Contracts\CertificateServiceContract;
use App\Domain\Certificate\DTOs\IssueCertificateDTO;
use App\Domain\Certificate\Exceptions\CertificateAlreadyExistsException;
use App\Domain\Certificate\Services\CertificateService;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(CertificateServiceContract::class);
});

describe('CertificateService', function () {

    describe('issue()', function () {

        it('issues certificate for completed enrollment', function () {
            $enrollment = Enrollment::factory()->completed()->create();

            $certificate = $this->service->issue($enrollment);

            expect($certificate)->toBeInstanceOf(Certificate::class);
            expect($certificate->user_id)->toBe($enrollment->user_id);
            expect($certificate->course_id)->toBe($enrollment->course_id);
            expect($certificate->enrollment_id)->toBe($enrollment->id);
            expect($certificate->certificate_number)->toMatch('/^CERT-\d{4}-[A-Z0-9]{6}$/');
            expect($certificate->issued_at)->not->toBeNull();
            expect($certificate->revoked_at)->toBeNull();
        });

        it('throws exception when certificate already exists', function () {
            $enrollment = Enrollment::factory()->completed()->create();
            Certificate::factory()->create(['enrollment_id' => $enrollment->id]);

            $this->service->issue($enrollment);
        })->throws(CertificateAlreadyExistsException::class);

        it('throws exception for incomplete enrollment', function () {
            $enrollment = Enrollment::factory()->active()->create([
                'status' => 'active',
                'progress_percentage' => 50,
            ]);

            $this->service->issue($enrollment);
        })->throws(\DomainException::class);

        it('dispatches CertificateIssued event', function () {
            Event::fake();
            $enrollment = Enrollment::factory()->completed()->create();

            $this->service->issue($enrollment);

            Event::assertDispatched(\App\Domain\Certificate\Events\CertificateIssued::class);
        });

        it('sets expiry date from course metadata', function () {
            $course = Course::factory()->create([
                'metadata' => ['certificate_validity_months' => 12],
            ]);
            $enrollment = Enrollment::factory()->completed()->create([
                'course_id' => $course->id,
            ]);

            $certificate = $this->service->issue($enrollment);

            expect($certificate->expires_at)->not->toBeNull();
            expect($certificate->expires_at->year)->toBe(now()->addYear()->year);
        });

    });

    describe('issueManual()', function () {

        it('issues certificate manually without enrollment', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();

            $dto = new IssueCertificateDTO(
                userId: $user->id,
                courseId: $course->id,
            );

            $certificate = $this->service->issueManual($dto);

            expect($certificate->user_id)->toBe($user->id);
            expect($certificate->course_id)->toBe($course->id);
            expect($certificate->enrollment_id)->toBeNull();
        });

        it('accepts custom expiry date', function () {
            $user = User::factory()->create();
            $course = Course::factory()->published()->create();
            $expiresAt = now()->addMonths(6);

            $dto = new IssueCertificateDTO(
                userId: $user->id,
                courseId: $course->id,
                expiresAt: $expiresAt,
            );

            $certificate = $this->service->issueManual($dto);

            expect($certificate->expires_at->toDateString())->toBe($expiresAt->toDateString());
        });

    });

    describe('revoke()', function () {

        it('revokes a certificate', function () {
            $certificate = Certificate::factory()->create();
            $admin = User::factory()->lmsAdmin()->create();

            $revoked = $this->service->revoke($certificate, $admin, 'Fraudulent activity');

            expect($revoked->revoked_at)->not->toBeNull();
            expect($revoked->revoked_by)->toBe($admin->id);
            expect($revoked->revocation_reason)->toBe('Fraudulent activity');
            expect($revoked->isRevoked())->toBeTrue();
            expect($revoked->isValid())->toBeFalse();
        });

        it('dispatches CertificateRevoked event', function () {
            Event::fake();
            $certificate = Certificate::factory()->create();
            $admin = User::factory()->lmsAdmin()->create();

            $this->service->revoke($certificate, $admin, 'Test reason');

            Event::assertDispatched(\App\Domain\Certificate\Events\CertificateRevoked::class);
        });

    });

    describe('verify()', function () {

        it('returns certificate data for valid certificate', function () {
            $certificate = Certificate::factory()->create();

            $data = $this->service->verify($certificate->certificate_number);

            expect($data)->not->toBeNull();
            expect($data->certificateNumber)->toBe($certificate->certificate_number);
            expect($data->isValid)->toBeTrue();
        });

        it('returns null for non-existent certificate', function () {
            $data = $this->service->verify('CERT-9999-NOTFOUND');

            expect($data)->toBeNull();
        });

        it('returns data with isValid=false for revoked certificate', function () {
            $certificate = Certificate::factory()->revoked()->create();

            $data = $this->service->verify($certificate->certificate_number);

            expect($data)->not->toBeNull();
            expect($data->isValid)->toBeFalse();
        });

        it('returns data with isValid=false for expired certificate', function () {
            $certificate = Certificate::factory()->expired()->create();

            $data = $this->service->verify($certificate->certificate_number);

            expect($data)->not->toBeNull();
            expect($data->isValid)->toBeFalse();
        });

    });

    describe('hasCertificate()', function () {

        it('returns true when enrollment has valid certificate', function () {
            $enrollment = Enrollment::factory()->completed()->create();
            Certificate::factory()->create(['enrollment_id' => $enrollment->id]);

            expect($this->service->hasCertificate($enrollment))->toBeTrue();
        });

        it('returns false when enrollment has no certificate', function () {
            $enrollment = Enrollment::factory()->completed()->create();

            expect($this->service->hasCertificate($enrollment))->toBeFalse();
        });

        it('returns false when certificate is revoked', function () {
            $enrollment = Enrollment::factory()->completed()->create();
            Certificate::factory()->revoked()->create(['enrollment_id' => $enrollment->id]);

            expect($this->service->hasCertificate($enrollment))->toBeFalse();
        });

    });

    describe('getUserCertificates()', function () {

        it('returns all certificates for user', function () {
            $user = User::factory()->create();
            Certificate::factory()->count(3)->create(['user_id' => $user->id]);
            Certificate::factory()->count(2)->create(); // Other users

            $certificates = $this->service->getUserCertificates($user);

            expect($certificates)->toHaveCount(3);
        });

        it('orders by issued_at descending', function () {
            $user = User::factory()->create();
            $old = Certificate::factory()->create([
                'user_id' => $user->id,
                'issued_at' => now()->subYear(),
            ]);
            $new = Certificate::factory()->create([
                'user_id' => $user->id,
                'issued_at' => now(),
            ]);

            $certificates = $this->service->getUserCertificates($user);

            expect($certificates->first()->id)->toBe($new->id);
            expect($certificates->last()->id)->toBe($old->id);
        });

    });

});
```

### 2. CertificateNumberTest

```php
<?php

use App\Domain\Certificate\ValueObjects\CertificateNumber;

describe('CertificateNumber', function () {

    describe('generate()', function () {

        it('generates valid certificate number format', function () {
            $number = CertificateNumber::generate();

            expect($number->toString())->toMatch('/^CERT-\d{4}-[A-Z0-9]{6}$/');
        });

        it('includes current year', function () {
            $number = CertificateNumber::generate();
            $currentYear = date('Y');

            expect($number->toString())->toContain("CERT-{$currentYear}-");
        });

        it('generates unique numbers', function () {
            $numbers = collect(range(1, 100))->map(fn () => CertificateNumber::generate()->toString());

            expect($numbers->unique()->count())->toBe(100);
        });

    });

    describe('fromString()', function () {

        it('accepts valid certificate number', function () {
            $number = CertificateNumber::fromString('CERT-2026-ABC123');

            expect($number->toString())->toBe('CERT-2026-ABC123');
        });

        it('throws exception for invalid format', function () {
            CertificateNumber::fromString('INVALID-NUMBER');
        })->throws(InvalidArgumentException::class);

        it('rejects lowercase characters', function () {
            CertificateNumber::fromString('CERT-2026-abc123');
        })->throws(InvalidArgumentException::class);

        it('rejects wrong prefix', function () {
            CertificateNumber::fromString('SERT-2026-ABC123');
        })->throws(InvalidArgumentException::class);

    });

    describe('isValid()', function () {

        it('validates correct format', function () {
            expect(CertificateNumber::isValid('CERT-2026-ABC123'))->toBeTrue();
            expect(CertificateNumber::isValid('CERT-2025-XYZ789'))->toBeTrue();
            expect(CertificateNumber::isValid('CERT-2030-000000'))->toBeTrue();
        });

        it('rejects invalid formats', function () {
            expect(CertificateNumber::isValid('INVALID'))->toBeFalse();
            expect(CertificateNumber::isValid('CERT-26-ABC123'))->toBeFalse();
            expect(CertificateNumber::isValid('CERT-2026-ABC'))->toBeFalse();
            expect(CertificateNumber::isValid('CERT-2026-ABC1234'))->toBeFalse();
            expect(CertificateNumber::isValid(''))->toBeFalse();
        });

    });

    describe('equals()', function () {

        it('returns true for same number', function () {
            $a = CertificateNumber::fromString('CERT-2026-ABC123');
            $b = CertificateNumber::fromString('CERT-2026-ABC123');

            expect($a->equals($b))->toBeTrue();
        });

        it('returns false for different numbers', function () {
            $a = CertificateNumber::fromString('CERT-2026-ABC123');
            $b = CertificateNumber::fromString('CERT-2026-XYZ789');

            expect($a->equals($b))->toBeFalse();
        });

    });

});
```

### 3. Certificate Model Test

```php
<?php

use App\Models\Certificate;
use App\Models\User;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Certificate Model', function () {

    describe('relationships', function () {

        it('belongs to user', function () {
            $user = User::factory()->create();
            $certificate = Certificate::factory()->create(['user_id' => $user->id]);

            expect($certificate->user->id)->toBe($user->id);
        });

        it('belongs to course', function () {
            $course = Course::factory()->create();
            $certificate = Certificate::factory()->create(['course_id' => $course->id]);

            expect($certificate->course->id)->toBe($course->id);
        });

        it('belongs to enrollment', function () {
            $enrollment = Enrollment::factory()->completed()->create();
            $certificate = Certificate::factory()->create(['enrollment_id' => $enrollment->id]);

            expect($certificate->enrollment->id)->toBe($enrollment->id);
        });

    });

    describe('scopes', function () {

        it('filters valid certificates', function () {
            Certificate::factory()->count(2)->create(); // Valid
            Certificate::factory()->revoked()->create();
            Certificate::factory()->expired()->create();

            expect(Certificate::valid()->count())->toBe(2);
        });

        it('filters revoked certificates', function () {
            Certificate::factory()->count(2)->create();
            Certificate::factory()->revoked()->count(3)->create();

            expect(Certificate::revoked()->count())->toBe(3);
        });

        it('filters expired certificates', function () {
            Certificate::factory()->count(2)->create();
            Certificate::factory()->expired()->count(2)->create();

            expect(Certificate::expired()->count())->toBe(2);
        });

    });

    describe('accessors', function () {

        it('calculates is_valid correctly', function () {
            $valid = Certificate::factory()->create();
            $revoked = Certificate::factory()->revoked()->create();
            $expired = Certificate::factory()->expired()->create();

            expect($valid->is_valid)->toBeTrue();
            expect($revoked->is_valid)->toBeFalse();
            expect($expired->is_valid)->toBeFalse();
        });

        it('generates verification URL', function () {
            $certificate = Certificate::factory()->create();

            expect($certificate->verification_url)->toContain('/verify/');
            expect($certificate->verification_url)->toContain($certificate->certificate_number);
        });

    });

    describe('methods', function () {

        it('checks if revoked', function () {
            $valid = Certificate::factory()->create();
            $revoked = Certificate::factory()->revoked()->create();

            expect($valid->isRevoked())->toBeFalse();
            expect($revoked->isRevoked())->toBeTrue();
        });

        it('checks if expired', function () {
            $noExpiry = Certificate::factory()->create(['expires_at' => null]);
            $futureExpiry = Certificate::factory()->create(['expires_at' => now()->addYear()]);
            $pastExpiry = Certificate::factory()->expired()->create();

            expect($noExpiry->isExpired())->toBeFalse();
            expect($futureExpiry->isExpired())->toBeFalse();
            expect($pastExpiry->isExpired())->toBeTrue();
        });

    });

});
```

---

## Feature Tests

### 4. CertificateAutoIssuanceTest

```php
<?php

use App\Domain\Enrollment\Events\EnrollmentCompleted;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

describe('Certificate Auto-Issuance', function () {

    it('issues certificate when EnrollmentCompleted event is dispatched', function () {
        $enrollment = Enrollment::factory()->completed()->create();

        event(new EnrollmentCompleted($enrollment));

        // Process queued job
        $this->artisan('queue:work --once');

        expect(Certificate::where('enrollment_id', $enrollment->id)->exists())->toBeTrue();
    });

    it('does not duplicate certificate if already exists', function () {
        $enrollment = Enrollment::factory()->completed()->create();
        Certificate::factory()->create(['enrollment_id' => $enrollment->id]);

        event(new EnrollmentCompleted($enrollment));
        $this->artisan('queue:work --once');

        expect(Certificate::where('enrollment_id', $enrollment->id)->count())->toBe(1);
    });

    it('issues certificate through ProgressTrackingService completion', function () {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        // Simulate course completion through progress service
        $service = app(\App\Domain\Progress\Contracts\ProgressTrackingServiceContract::class);

        // Mark all lessons complete (triggers EnrollmentCompleted)
        // ... setup and complete all lessons ...

        $this->artisan('queue:work --once');

        // Certificate should be issued
        expect($enrollment->fresh()->hasCertificate())->toBeTrue();
    });

});
```

### 5. CertificateDownloadTest

```php
<?php

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Certificate Download', function () {

    it('allows owner to download certificate', function () {
        $user = User::factory()->create();
        $certificate = Certificate::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('certificates.download', $certificate));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition');
    });

    it('prevents non-owner from downloading', function () {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $certificate = Certificate::factory()->create(['user_id' => $owner->id]);

        $response = $this->actingAs($other)
            ->get(route('certificates.download', $certificate));

        $response->assertForbidden();
    });

    it('allows admin to download any certificate', function () {
        $admin = User::factory()->lmsAdmin()->create();
        $certificate = Certificate::factory()->create();

        $response = $this->actingAs($admin)
            ->get(route('certificates.download', $certificate));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
    });

    it('includes correct filename in download', function () {
        $user = User::factory()->create(['name' => 'John Doe']);
        $course = Course::factory()->create(['title' => 'Laravel Testing']);
        $certificate = Certificate::factory()->create([
            'user_id' => $user->id,
            'course_id' => $course->id,
        ]);

        $response = $this->actingAs($user)
            ->get(route('certificates.download', $certificate));

        $contentDisposition = $response->headers->get('Content-Disposition');
        expect($contentDisposition)->toContain('sertifikat-');
        expect($contentDisposition)->toContain('.pdf');
    });

});
```

### 6. CertificateVerificationTest (Public)

```php
<?php

use App\Models\Certificate;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Certificate Public Verification', function () {

    it('displays valid certificate information', function () {
        $certificate = Certificate::factory()->create();

        $response = $this->get(route('certificates.verify', $certificate->certificate_number));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('certificates/Verify')
            ->has('certificate')
            ->where('certificate.is_valid', true)
            ->where('certificate.certificate_number', $certificate->certificate_number)
        );
    });

    it('displays revoked certificate with warning', function () {
        $certificate = Certificate::factory()->revoked()->create([
            'revocation_reason' => 'Test revocation',
        ]);

        $response = $this->get(route('certificates.verify', $certificate->certificate_number));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('certificate.is_valid', false)
            ->where('certificate.revocation_reason', 'Test revocation')
        );
    });

    it('displays expired certificate with notice', function () {
        $certificate = Certificate::factory()->expired()->create();

        $response = $this->get(route('certificates.verify', $certificate->certificate_number));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->where('certificate.is_valid', false)
        );
    });

    it('shows not found page for invalid certificate number', function () {
        $response = $this->get(route('certificates.verify', 'CERT-9999-NOTFOUND'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('certificates/VerifyNotFound')
            ->where('certificateNumber', 'CERT-9999-NOTFOUND')
        );
    });

    it('does not require authentication', function () {
        $certificate = Certificate::factory()->create();

        $response = $this->get(route('certificates.verify', $certificate->certificate_number));

        $response->assertOk();
    });

});
```

### 7. AdminCertificateTest

```php
<?php

use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Admin Certificate Management', function () {

    describe('listing', function () {

        it('allows admin to view all certificates', function () {
            $admin = User::factory()->lmsAdmin()->create();
            Certificate::factory()->count(5)->create();

            $response = $this->actingAs($admin)
                ->get(route('admin.certificates.index'));

            $response->assertOk();
            $response->assertInertia(fn ($page) => $page
                ->component('admin/certificates/Index')
                ->has('certificates.data', 5)
            );
        });

        it('prevents non-admin from viewing', function () {
            $learner = User::factory()->create();

            $response = $this->actingAs($learner)
                ->get(route('admin.certificates.index'));

            $response->assertForbidden();
        });

        it('filters by search term', function () {
            $admin = User::factory()->lmsAdmin()->create();
            $user = User::factory()->create(['name' => 'John Doe']);
            Certificate::factory()->create(['user_id' => $user->id]);
            Certificate::factory()->count(3)->create();

            $response = $this->actingAs($admin)
                ->get(route('admin.certificates.index', ['search' => 'John']));

            $response->assertInertia(fn ($page) => $page
                ->has('certificates.data', 1)
            );
        });

        it('filters by status', function () {
            $admin = User::factory()->lmsAdmin()->create();
            Certificate::factory()->count(2)->create();
            Certificate::factory()->revoked()->count(3)->create();

            $response = $this->actingAs($admin)
                ->get(route('admin.certificates.index', ['status' => 'revoked']));

            $response->assertInertia(fn ($page) => $page
                ->has('certificates.data', 3)
            );
        });

    });

    describe('manual issuance', function () {

        it('allows admin to issue certificate manually', function () {
            $admin = User::factory()->lmsAdmin()->create();
            $learner = User::factory()->create();
            $course = Course::factory()->published()->create();

            $response = $this->actingAs($admin)
                ->post(route('admin.certificates.store'), [
                    'user_id' => $learner->id,
                    'course_id' => $course->id,
                ]);

            $response->assertRedirect();
            expect(Certificate::where('user_id', $learner->id)->exists())->toBeTrue();
        });

        it('validates required fields', function () {
            $admin = User::factory()->lmsAdmin()->create();

            $response = $this->actingAs($admin)
                ->post(route('admin.certificates.store'), []);

            $response->assertSessionHasErrors(['user_id', 'course_id']);
        });

    });

    describe('revocation', function () {

        it('allows admin to revoke certificate', function () {
            $admin = User::factory()->lmsAdmin()->create();
            $certificate = Certificate::factory()->create();

            $response = $this->actingAs($admin)
                ->post(route('admin.certificates.revoke', $certificate), [
                    'reason' => 'Certificate issued in error',
                ]);

            $response->assertRedirect();
            expect($certificate->fresh()->isRevoked())->toBeTrue();
        });

        it('requires revocation reason', function () {
            $admin = User::factory()->lmsAdmin()->create();
            $certificate = Certificate::factory()->create();

            $response = $this->actingAs($admin)
                ->post(route('admin.certificates.revoke', $certificate), []);

            $response->assertSessionHasErrors(['reason']);
        });

        it('prevents revoking already revoked certificate', function () {
            $admin = User::factory()->lmsAdmin()->create();
            $certificate = Certificate::factory()->revoked()->create();

            $response = $this->actingAs($admin)
                ->post(route('admin.certificates.revoke', $certificate), [
                    'reason' => 'Another reason',
                ]);

            $response->assertSessionHas('error');
        });

    });

});
```

---

## Edge Case Tests

### 8. CertificateEdgeCasesTest

```php
<?php

use App\Domain\Certificate\Contracts\CertificateServiceContract;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Certificate Edge Cases', function () {

    describe('concurrent issuance', function () {

        it('handles race condition when issuing certificate', function () {
            $enrollment = Enrollment::factory()->completed()->create();
            $service = app(CertificateServiceContract::class);

            // Simulate concurrent requests
            $results = collect(range(1, 3))->map(function () use ($service, $enrollment) {
                try {
                    return $service->issue($enrollment);
                } catch (\Throwable $e) {
                    return $e;
                }
            });

            // Only one should succeed
            $successes = $results->filter(fn ($r) => $r instanceof Certificate);
            expect($successes)->toHaveCount(1);

            // Others should throw exception
            $failures = $results->filter(fn ($r) => $r instanceof \Throwable);
            expect($failures->count())->toBeGreaterThanOrEqual(0);

            // Only one certificate in database
            expect(Certificate::where('enrollment_id', $enrollment->id)->count())->toBe(1);
        });

    });

    describe('deleted relationships', function () {

        it('handles soft-deleted course', function () {
            $course = Course::factory()->create();
            $certificate = Certificate::factory()->create(['course_id' => $course->id]);
            $course->delete();

            // Certificate should still be accessible
            expect($certificate->fresh())->not->toBeNull();

            // Course relationship should work with trashed
            expect($certificate->fresh()->course()->withTrashed()->first())->not->toBeNull();
        });

        it('handles deleted user gracefully', function () {
            $user = User::factory()->create();
            $certificate = Certificate::factory()->create(['user_id' => $user->id]);

            // Verification should still work
            $service = app(CertificateServiceContract::class);
            $data = $service->verify($certificate->certificate_number);

            expect($data)->not->toBeNull();
        });

    });

    describe('expiration edge cases', function () {

        it('certificate expiring at exact current time is expired', function () {
            $certificate = Certificate::factory()->create([
                'expires_at' => now(),
            ]);

            expect($certificate->isExpired())->toBeTrue();
        });

        it('certificate expiring 1 second in future is valid', function () {
            $certificate = Certificate::factory()->create([
                'expires_at' => now()->addSecond(),
            ]);

            expect($certificate->isExpired())->toBeFalse();
            expect($certificate->isValid())->toBeTrue();
        });

    });

    describe('certificate number uniqueness', function () {

        it('generates unique numbers even with high collision risk', function () {
            // Generate many certificates rapidly
            $certificates = collect(range(1, 50))->map(function () {
                return Certificate::factory()->create();
            });

            $uniqueNumbers = $certificates->pluck('certificate_number')->unique();
            expect($uniqueNumbers)->toHaveCount(50);
        });

    });

    describe('metadata handling', function () {

        it('handles null metadata gracefully', function () {
            $certificate = Certificate::factory()->create(['metadata' => null]);

            expect($certificate->metadata)->toBeNull();
            expect($certificate->isValid())->toBeTrue();
        });

        it('preserves complex metadata structure', function () {
            $complexMetadata = [
                'completion_date' => now()->toISOString(),
                'scores' => ['quiz1' => 85, 'quiz2' => 92],
                'instructor_notes' => 'Excellent performance',
            ];

            $certificate = Certificate::factory()->create(['metadata' => $complexMetadata]);

            expect($certificate->fresh()->metadata)->toEqual($complexMetadata);
        });

    });

    describe('special characters in names', function () {

        it('handles Indonesian names with special characters', function () {
            $user = User::factory()->create(['name' => "M. Syahrul Amin Bin H. M. Nur'aini"]);
            $certificate = Certificate::factory()->create(['user_id' => $user->id]);

            $service = app(CertificateServiceContract::class);
            $data = $service->verify($certificate->certificate_number);

            expect($data->learnerName)->toBe("M. Syahrul Amin Bin H. M. Nur'aini");
        });

        it('generates valid PDF with special characters', function () {
            $user = User::factory()->create(['name' => 'José García']);
            $course = Course::factory()->create(['title' => 'Café Management']);
            $certificate = Certificate::factory()->create([
                'user_id' => $user->id,
                'course_id' => $course->id,
            ]);

            $generator = app(\App\Domain\Certificate\Contracts\CertificateGeneratorContract::class);
            $pdf = $generator->generatePdf($certificate);

            expect($pdf)->not->toBeEmpty();
            expect(strlen($pdf))->toBeGreaterThan(1000);
        });

    });

});
```

---

## Integration Test

### 9. CertificateLifecycleTest

```php
<?php

use App\Domain\Certificate\Contracts\CertificateServiceContract;
use App\Domain\Progress\Contracts\ProgressTrackingServiceContract;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Certificate Full Lifecycle', function () {

    it('complete flow: enrollment → progress → completion → certificate → verification', function () {
        // Setup
        $learner = User::factory()->create();
        $course = Course::factory()->published()->create();
        $section = CourseSection::factory()->create(['course_id' => $course->id]);
        $lesson = Lesson::factory()->create(['course_section_id' => $section->id]);

        // Step 1: Enroll
        $enrollment = Enrollment::factory()->active()->create([
            'user_id' => $learner->id,
            'course_id' => $course->id,
        ]);

        expect($enrollment->status)->toBe('active');
        expect($enrollment->progress_percentage)->toBe(0);

        // Step 2: Complete lesson
        $progressService = app(ProgressTrackingServiceContract::class);
        $progressService->markLessonComplete($enrollment, $lesson);

        // Process queue (certificate issuance is queued)
        $this->artisan('queue:work --once');

        // Step 3: Verify enrollment completed
        $enrollment->refresh();
        expect($enrollment->status)->toBe('completed');
        expect($enrollment->progress_percentage)->toBe(100.0);

        // Step 4: Verify certificate issued
        $certificate = Certificate::where('enrollment_id', $enrollment->id)->first();
        expect($certificate)->not->toBeNull();
        expect($certificate->isValid())->toBeTrue();

        // Step 5: Public verification
        $certService = app(CertificateServiceContract::class);
        $verificationData = $certService->verify($certificate->certificate_number);

        expect($verificationData)->not->toBeNull();
        expect($verificationData->isValid)->toBeTrue();
        expect($verificationData->learnerName)->toBe($learner->name);
        expect($verificationData->courseTitle)->toBe($course->title);

        // Step 6: Download PDF
        $response = $this->actingAs($learner)
            ->get(route('certificates.download', $certificate));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        // Step 7: Admin revokes certificate
        $admin = User::factory()->lmsAdmin()->create();
        $certService->revoke($certificate, $admin, 'Found to be fraudulent');

        // Step 8: Verification now shows invalid
        $verificationData = $certService->verify($certificate->certificate_number);
        expect($verificationData->isValid)->toBeFalse();
        expect($verificationData->revocationReason)->toBe('Found to be fraudulent');
    });

});
```

---

## Test Coverage Summary

| Category | Test Count | Coverage |
|----------|------------|----------|
| CertificateService | 15 | issue, issueManual, revoke, verify, hasCertificate, getUserCertificates |
| CertificateNumber | 10 | generate, fromString, isValid, equals |
| Certificate Model | 12 | relationships, scopes, accessors, methods |
| Auto-Issuance | 3 | event handling, duplicate prevention |
| Download | 4 | authorization, PDF response |
| Public Verification | 5 | valid, revoked, expired, not found |
| Admin Management | 8 | listing, filtering, issuance, revocation |
| Edge Cases | 10 | concurrent, deleted relationships, expiration |
| Integration | 1 | Full lifecycle |

**Total: ~68 test cases**

---

## Running Tests

```bash
# Run all certificate tests
php artisan test --filter=Certificate

# Run specific test file
php artisan test tests/Unit/Domain/Certificate/Services/CertificateServiceTest.php

# Run with coverage
php artisan test --filter=Certificate --coverage

# Run in parallel
php artisan test --filter=Certificate --parallel
```

---

## Implementation Checklist

- [ ] Create all unit test files
- [ ] Create all feature test files
- [ ] Create edge case test file
- [ ] Create integration test file
- [ ] Run full test suite
- [ ] Achieve >80% code coverage
- [ ] Fix any failing tests
- [ ] Document any known limitations

---

## Related Documents

- [00-INDEX.md](./00-INDEX.md) - Main plan index
- [01-DOMAIN-LAYER.md](./01-DOMAIN-LAYER.md) - Domain implementation
