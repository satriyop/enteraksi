# Certificate Module - Implementation Plan

> **Feature**: Certificate Management for Course Completion
> **Status**: Planning
> **Created**: 2026-01-20

---

## Overview

The Certificate Module enables automatic generation of completion certificates when learners finish courses. Certificates can be downloaded as PDFs and verified publicly.

## Business Goals

1. **Recognition** - Provide learners with proof of course completion
2. **Compliance** - Support OJK training requirements with verifiable certificates
3. **Verification** - Enable third parties (employers, regulators) to verify authenticity
4. **Retention** - Motivate learners with tangible achievement rewards

## User Stories

### Learner Stories
| ID | Story | Priority |
|----|-------|----------|
| US-01 | As a learner, I want to automatically receive a certificate when I complete a course | Must |
| US-02 | As a learner, I want to download my certificate as a PDF | Must |
| US-03 | As a learner, I want to view all my certificates in one place | Should |
| US-04 | As a learner, I want to share my certificate via a public link | Should |

### Admin Stories
| ID | Story | Priority |
|----|-------|----------|
| US-05 | As an admin, I want to view all issued certificates | Must |
| US-06 | As an admin, I want to revoke a certificate if needed | Must |
| US-07 | As an admin, I want to manually issue a certificate | Should |
| US-08 | As an admin, I want to customize certificate templates per course | Could |

### Public Stories
| ID | Story | Priority |
|----|-------|----------|
| US-09 | As a verifier, I want to verify a certificate's authenticity via URL | Must |
| US-10 | As a verifier, I want to see certificate details (name, course, date) | Must |

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                         Trigger Layer                            │
│  ┌─────────────────────┐    ┌─────────────────────────────────┐ │
│  │ EnrollmentCompleted │───>│ IssueCertificateOnCompletion    │ │
│  │      (Event)        │    │        (Listener)               │ │
│  └─────────────────────┘    └───────────────┬─────────────────┘ │
└─────────────────────────────────────────────┼───────────────────┘
                                              │
┌─────────────────────────────────────────────▼───────────────────┐
│                        Domain Layer                              │
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                   CertificateService                        ││
│  │  • issue(enrollment)     • revoke(certificate, reason)      ││
│  │  • regenerate(cert)      • verify(certificateNumber)        ││
│  └─────────────────────────────────────────────────────────────┘│
│  ┌─────────────────────────────────────────────────────────────┐│
│  │                 CertificateGenerator                        ││
│  │  • generatePdf(certificate) → PDF binary                    ││
│  │  • getTemplate(course)      → Blade template                ││
│  └─────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────┘
                                              │
┌─────────────────────────────────────────────▼───────────────────┐
│                      Persistence Layer                           │
│  ┌──────────────┐  ┌──────────────┐  ┌────────────────────────┐ │
│  │ Certificate  │  │    User      │  │      Enrollment        │ │
│  │   (Model)    │  │   (Model)    │  │       (Model)          │ │
│  └──────────────┘  └──────────────┘  └────────────────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

## Implementation Phases

| Phase | Document | Description | Effort |
|-------|----------|-------------|--------|
| 1 | [01-DOMAIN-LAYER.md](./01-DOMAIN-LAYER.md) | Contracts, services, DTOs, events | Medium |
| 2 | [02-DATABASE-AND-MODEL.md](./02-DATABASE-AND-MODEL.md) | Migration, model, factory, seeder | Low |
| 3 | [03-PDF-GENERATION.md](./03-PDF-GENERATION.md) | PDF generator, templates, styling | Medium |
| 4 | [04-CONTROLLERS-AND-ROUTES.md](./04-CONTROLLERS-AND-ROUTES.md) | Routes, controllers, policies | Medium |
| 5 | [05-UI-COMPONENTS.md](./05-UI-COMPONENTS.md) | Vue components, pages | Medium |
| 6 | [06-TEST-PLAN.md](./06-TEST-PLAN.md) | Unit, feature, edge case tests | High |

## Dependencies

### Existing Code We Leverage
- `EnrollmentCompleted` event (already dispatched on course completion)
- `ProgressTrackingService` (determines completion)
- Domain layer patterns (services, DTOs, events)

### New Dependencies
- `barryvdh/laravel-dompdf` - PDF generation from HTML/Blade

## Database Schema (Preview)

```sql
CREATE TABLE certificates (
    id BIGINT PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    course_id BIGINT REFERENCES courses(id),
    enrollment_id BIGINT REFERENCES enrollments(id),
    certificate_number VARCHAR(50) UNIQUE,  -- CERT-2026-XXXXXX
    issued_at TIMESTAMP,
    expires_at TIMESTAMP NULL,              -- For compliance courses
    revoked_at TIMESTAMP NULL,
    revoked_by BIGINT NULL REFERENCES users(id),
    revocation_reason TEXT NULL,
    metadata JSON,                          -- {score, completion_time, etc}
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

CREATE INDEX idx_certificates_user ON certificates(user_id);
CREATE INDEX idx_certificates_number ON certificates(certificate_number);
CREATE INDEX idx_certificates_course ON certificates(course_id);
```

## API Endpoints (Preview)

| Method | Endpoint | Description | Auth |
|--------|----------|-------------|------|
| GET | `/certificates` | List user's certificates | Learner |
| GET | `/certificates/{id}` | View certificate details | Owner/Admin |
| GET | `/certificates/{id}/download` | Download PDF | Owner/Admin |
| POST | `/certificates/{id}/regenerate` | Regenerate PDF | Owner/Admin |
| POST | `/admin/certificates` | Manually issue certificate | Admin |
| DELETE | `/admin/certificates/{id}` | Revoke certificate | Admin |
| GET | `/verify/{certificateNumber}` | Public verification | Public |

## Success Criteria

1. [ ] Certificates auto-generated when course completed
2. [ ] PDF downloads work with correct formatting
3. [ ] Verification URL works without authentication
4. [ ] Certificates show in learner dashboard
5. [ ] Admin can view/revoke certificates
6. [ ] All tests pass (unit + feature + edge cases)
7. [ ] Indonesian language support in certificates

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| PDF generation slow | Medium | Generate async via queue, cache PDFs |
| Certificate number collision | High | Use UUID + timestamp prefix |
| Large PDF file size | Low | Optimize images, compress output |
| Template rendering issues | Medium | Comprehensive template tests |

## Related Documents

- [ARCHITECTURE.md](../../../docs/ARCHITECTURE.md) - Domain layer patterns
- [DATA-MODEL.md](../../../docs/DATA-MODEL.md) - Existing models
- [FEATURES.md](../../../docs/FEATURES.md) - Progress tracking flow

---

## Quick Start

After planning approval:

```bash
# Phase 1: Domain Layer
php artisan make:class Domain/Certificate/Services/CertificateService

# Phase 2: Database
php artisan make:model Certificate -mf

# Phase 3: PDF
composer require barryvdh/laravel-dompdf

# Phase 4: Controllers
php artisan make:controller CertificateController

# Phase 5: Run tests
php artisan test --filter=Certificate
```
