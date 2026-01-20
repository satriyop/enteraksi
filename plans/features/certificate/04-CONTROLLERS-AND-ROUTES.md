# Phase 4: Controllers and Routes

> **Phase**: 4 of 5
> **Estimated Effort**: Medium
> **Prerequisites**: Phase 1-3

---

## Objectives

- Create routes for certificate operations
- Implement controllers (learner, admin, public)
- Create authorization policy
- Set up form requests for validation

---

## 4.1 Route Definitions

### File: `routes/certificates.php` (new file)

```php
<?php

use App\Http\Controllers\CertificateController;
use App\Http\Controllers\CertificateVerificationController;
use App\Http\Controllers\Admin\CertificateAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Certificate Routes
|--------------------------------------------------------------------------
*/

// Public verification (no auth required)
Route::get('/verify/{certificateNumber}', [CertificateVerificationController::class, 'show'])
    ->name('certificates.verify');

// Authenticated routes
Route::middleware(['auth', 'verified'])->group(function () {

    // Learner certificate routes
    Route::prefix('certificates')->name('certificates.')->group(function () {
        Route::get('/', [CertificateController::class, 'index'])
            ->name('index');

        Route::get('/{certificate}', [CertificateController::class, 'show'])
            ->name('show');

        Route::get('/{certificate}/download', [CertificateController::class, 'download'])
            ->name('download');

        Route::post('/{certificate}/regenerate', [CertificateController::class, 'regenerate'])
            ->name('regenerate');
    });

    // Admin certificate routes
    Route::prefix('admin/certificates')->name('admin.certificates.')->middleware('can:viewAny,App\Models\Certificate')->group(function () {
        Route::get('/', [CertificateAdminController::class, 'index'])
            ->name('index');

        Route::get('/create', [CertificateAdminController::class, 'create'])
            ->name('create');

        Route::post('/', [CertificateAdminController::class, 'store'])
            ->name('store');

        Route::get('/{certificate}', [CertificateAdminController::class, 'show'])
            ->name('show');

        Route::delete('/{certificate}', [CertificateAdminController::class, 'destroy'])
            ->name('destroy');

        Route::post('/{certificate}/revoke', [CertificateAdminController::class, 'revoke'])
            ->name('revoke');
    });
});
```

### Register in `bootstrap/app.php`

```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
    then: function () {
        Route::middleware('web')
            ->group(base_path('routes/certificates.php')); // Add this
    },
)
```

---

## 4.2 Certificate Policy

### File: `app/Policies/CertificatePolicy.php`

```php
<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CertificatePolicy
{
    use HandlesAuthorization;

    /**
     * Admin can do anything.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isLmsAdmin()) {
            return true;
        }

        return null;
    }

    /**
     * Determine if user can view list of certificates.
     * Admins can view all, learners see only their own.
     */
    public function viewAny(User $user): bool
    {
        return true; // Everyone can view their own list
    }

    /**
     * Determine if user can view a specific certificate.
     */
    public function view(User $user, Certificate $certificate): bool
    {
        return $user->id === $certificate->user_id;
    }

    /**
     * Determine if user can download a certificate.
     */
    public function download(User $user, Certificate $certificate): bool
    {
        return $user->id === $certificate->user_id;
    }

    /**
     * Determine if user can regenerate a certificate PDF.
     */
    public function regenerate(User $user, Certificate $certificate): bool
    {
        // Only owner can regenerate, and certificate must be valid
        return $user->id === $certificate->user_id && $certificate->isValid();
    }

    /**
     * Determine if user can manually issue certificates.
     * Only admins (handled by before()).
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine if user can revoke a certificate.
     * Only admins (handled by before()).
     */
    public function revoke(User $user, Certificate $certificate): bool
    {
        return false;
    }

    /**
     * Determine if user can delete/revoke a certificate.
     * Only admins (handled by before()).
     */
    public function delete(User $user, Certificate $certificate): bool
    {
        return false;
    }
}
```

### Register Policy

```php
// In app/Providers/AppServiceProvider.php boot() method
use App\Models\Certificate;
use App\Policies\CertificatePolicy;
use Illuminate\Support\Facades\Gate;

Gate::policy(Certificate::class, CertificatePolicy::class);
```

---

## 4.3 Learner Certificate Controller

### File: `app/Http/Controllers/CertificateController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Domain\Certificate\Contracts\CertificateGeneratorContract;
use App\Domain\Certificate\Contracts\CertificateServiceContract;
use App\Models\Certificate;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CertificateController extends Controller
{
    public function __construct(
        private CertificateServiceContract $certificateService,
        private CertificateGeneratorContract $certificateGenerator
    ) {}

    /**
     * Display learner's certificates.
     */
    public function index(Request $request): InertiaResponse
    {
        $certificates = $this->certificateService
            ->getUserCertificates($request->user())
            ->load(['course:id,title,slug,thumbnail_path']);

        return Inertia::render('certificates/Index', [
            'certificates' => $certificates->map(fn ($cert) => [
                'id' => $cert->id,
                'certificate_number' => $cert->certificate_number,
                'course' => [
                    'title' => $cert->course->title,
                    'slug' => $cert->course->slug,
                    'thumbnail_url' => $cert->course->thumbnail_url,
                ],
                'issued_at' => $cert->issued_at->toISOString(),
                'formatted_issue_date' => $cert->formatted_issue_date,
                'expires_at' => $cert->expires_at?->toISOString(),
                'is_valid' => $cert->isValid(),
                'is_expired' => $cert->isExpired(),
                'verification_url' => $cert->verification_url,
            ]),
        ]);
    }

    /**
     * Display a specific certificate.
     */
    public function show(Certificate $certificate): InertiaResponse
    {
        $this->authorize('view', $certificate);

        $certificate->load(['course', 'enrollment']);

        return Inertia::render('certificates/Show', [
            'certificate' => [
                'id' => $certificate->id,
                'certificate_number' => $certificate->certificate_number,
                'course' => [
                    'id' => $certificate->course->id,
                    'title' => $certificate->course->title,
                    'slug' => $certificate->course->slug,
                ],
                'issued_at' => $certificate->issued_at->toISOString(),
                'formatted_issue_date' => $certificate->formatted_issue_date,
                'expires_at' => $certificate->expires_at?->toISOString(),
                'formatted_expiry_date' => $certificate->formatted_expiry_date,
                'is_valid' => $certificate->isValid(),
                'is_expired' => $certificate->isExpired(),
                'is_revoked' => $certificate->isRevoked(),
                'revocation_reason' => $certificate->revocation_reason,
                'verification_url' => $certificate->verification_url,
                'metadata' => $certificate->metadata,
            ],
        ]);
    }

    /**
     * Download certificate as PDF.
     */
    public function download(Certificate $certificate): Response
    {
        $this->authorize('download', $certificate);

        $pdf = $this->certificateGenerator->generatePdf($certificate);
        $filename = $this->certificateGenerator->getDownloadFilename($certificate);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Content-Length' => strlen($pdf),
        ]);
    }

    /**
     * Regenerate certificate PDF (clear cache).
     */
    public function regenerate(Certificate $certificate)
    {
        $this->authorize('regenerate', $certificate);

        $this->certificateService->regenerate($certificate);
        $this->certificateGenerator->clearCache($certificate);

        return back()->with('success', 'Sertifikat berhasil diperbarui.');
    }
}
```

---

## 4.4 Public Verification Controller

### File: `app/Http/Controllers/CertificateVerificationController.php`

```php
<?php

namespace App\Http\Controllers;

use App\Domain\Certificate\Contracts\CertificateServiceContract;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CertificateVerificationController extends Controller
{
    public function __construct(
        private CertificateServiceContract $certificateService
    ) {}

    /**
     * Public certificate verification page.
     */
    public function show(string $certificateNumber): InertiaResponse
    {
        $certificateData = $this->certificateService->verify($certificateNumber);

        if (!$certificateData) {
            return Inertia::render('certificates/VerifyNotFound', [
                'certificateNumber' => $certificateNumber,
            ]);
        }

        return Inertia::render('certificates/Verify', [
            'certificate' => [
                'certificate_number' => $certificateData->certificateNumber,
                'learner_name' => $certificateData->learnerName,
                'course_title' => $certificateData->courseTitle,
                'issued_at' => $certificateData->issuedAt->toISOString(),
                'formatted_issue_date' => $certificateData->issuedAt->translatedFormat('d F Y'),
                'expires_at' => $certificateData->expiresAt?->toISOString(),
                'formatted_expiry_date' => $certificateData->expiresAt?->translatedFormat('d F Y'),
                'is_valid' => $certificateData->isValid,
                'revocation_reason' => $certificateData->revocationReason,
            ],
        ]);
    }
}
```

---

## 4.5 Admin Certificate Controller

### File: `app/Http/Controllers/Admin/CertificateAdminController.php`

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Certificate\Contracts\CertificateServiceContract;
use App\Domain\Certificate\DTOs\IssueCertificateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCertificateRequest;
use App\Http\Requests\Admin\RevokeCertificateRequest;
use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class CertificateAdminController extends Controller
{
    public function __construct(
        private CertificateServiceContract $certificateService
    ) {}

    /**
     * Display all certificates (admin view).
     */
    public function index(Request $request): InertiaResponse
    {
        $query = Certificate::with(['user:id,name,email', 'course:id,title,slug'])
            ->orderByDesc('issued_at');

        // Filters
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('certificate_number', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"))
                    ->orWhereHas('course', fn ($q) => $q->where('title', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('status')) {
            match ($request->input('status')) {
                'valid' => $query->valid(),
                'revoked' => $query->revoked(),
                'expired' => $query->expired(),
                default => null,
            };
        }

        if ($request->filled('course_id')) {
            $query->where('course_id', $request->input('course_id'));
        }

        $certificates = $query->paginate(20)->withQueryString();

        return Inertia::render('admin/certificates/Index', [
            'certificates' => $certificates,
            'filters' => $request->only(['search', 'status', 'course_id']),
            'courses' => Course::select('id', 'title')->orderBy('title')->get(),
        ]);
    }

    /**
     * Show form to manually issue certificate.
     */
    public function create(): InertiaResponse
    {
        return Inertia::render('admin/certificates/Create', [
            'courses' => Course::published()->select('id', 'title')->orderBy('title')->get(),
            'learners' => User::where('role', 'learner')
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->limit(100)
                ->get(),
        ]);
    }

    /**
     * Manually issue a certificate.
     */
    public function store(StoreCertificateRequest $request): RedirectResponse
    {
        $dto = new IssueCertificateDTO(
            userId: $request->input('user_id'),
            courseId: $request->input('course_id'),
            expiresAt: $request->input('expires_at') ? now()->parse($request->input('expires_at')) : null,
            metadata: $request->input('metadata'),
        );

        $certificate = $this->certificateService->issueManual($dto);

        return redirect()
            ->route('admin.certificates.show', $certificate)
            ->with('success', 'Sertifikat berhasil diterbitkan.');
    }

    /**
     * View certificate details (admin).
     */
    public function show(Certificate $certificate): InertiaResponse
    {
        $certificate->load(['user', 'course', 'enrollment', 'revokedByUser']);

        return Inertia::render('admin/certificates/Show', [
            'certificate' => $certificate,
        ]);
    }

    /**
     * Revoke a certificate.
     */
    public function revoke(RevokeCertificateRequest $request, Certificate $certificate): RedirectResponse
    {
        if ($certificate->isRevoked()) {
            return back()->with('error', 'Sertifikat sudah dicabut.');
        }

        $this->certificateService->revoke(
            $certificate,
            $request->user(),
            $request->input('reason')
        );

        return back()->with('success', 'Sertifikat berhasil dicabut.');
    }

    /**
     * Delete (same as revoke for audit trail).
     */
    public function destroy(RevokeCertificateRequest $request, Certificate $certificate): RedirectResponse
    {
        return $this->revoke($request, $certificate);
    }
}
```

---

## 4.6 Form Requests

### StoreCertificateRequest

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isLmsAdmin();
    }

    public function rules(): array
    {
        return [
            'user_id' => [
                'required',
                'exists:users,id',
                Rule::exists('users', 'id')->where('role', 'learner'),
            ],
            'course_id' => [
                'required',
                'exists:courses,id',
            ],
            'expires_at' => [
                'nullable',
                'date',
                'after:today',
            ],
            'metadata' => [
                'nullable',
                'array',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Pilih peserta yang akan menerima sertifikat.',
            'user_id.exists' => 'Peserta tidak ditemukan.',
            'course_id.required' => 'Pilih kursus untuk sertifikat.',
            'course_id.exists' => 'Kursus tidak ditemukan.',
            'expires_at.after' => 'Tanggal kadaluarsa harus setelah hari ini.',
        ];
    }
}
```

### RevokeCertificateRequest

```php
<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RevokeCertificateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->isLmsAdmin();
    }

    public function rules(): array
    {
        return [
            'reason' => [
                'required',
                'string',
                'min:10',
                'max:500',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan pencabutan harus diisi.',
            'reason.min' => 'Alasan minimal 10 karakter.',
            'reason.max' => 'Alasan maksimal 500 karakter.',
        ];
    }
}
```

---

## 4.7 Route Summary

| Method | URI | Name | Controller | Auth |
|--------|-----|------|------------|------|
| GET | `/verify/{num}` | certificates.verify | CertificateVerificationController@show | Public |
| GET | `/certificates` | certificates.index | CertificateController@index | User |
| GET | `/certificates/{id}` | certificates.show | CertificateController@show | Owner |
| GET | `/certificates/{id}/download` | certificates.download | CertificateController@download | Owner |
| POST | `/certificates/{id}/regenerate` | certificates.regenerate | CertificateController@regenerate | Owner |
| GET | `/admin/certificates` | admin.certificates.index | CertificateAdminController@index | Admin |
| GET | `/admin/certificates/create` | admin.certificates.create | CertificateAdminController@create | Admin |
| POST | `/admin/certificates` | admin.certificates.store | CertificateAdminController@store | Admin |
| GET | `/admin/certificates/{id}` | admin.certificates.show | CertificateAdminController@show | Admin |
| POST | `/admin/certificates/{id}/revoke` | admin.certificates.revoke | CertificateAdminController@revoke | Admin |
| DELETE | `/admin/certificates/{id}` | admin.certificates.destroy | CertificateAdminController@destroy | Admin |

---

## Implementation Checklist

- [ ] Create `routes/certificates.php`
- [ ] Register route file in `bootstrap/app.php`
- [ ] Create `CertificatePolicy`
- [ ] Register policy in `AppServiceProvider`
- [ ] Create `CertificateController`
- [ ] Create `CertificateVerificationController`
- [ ] Create `Admin/CertificateAdminController`
- [ ] Create `StoreCertificateRequest`
- [ ] Create `RevokeCertificateRequest`
- [ ] Test all routes with correct auth
- [ ] Test policy authorization

---

## Next Phase

Continue to [Phase 5: UI Components](./05-UI-COMPONENTS.md)
