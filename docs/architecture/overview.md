# Architecture Overview

This document follows the [Arc42](https://arc42.org/) template for software architecture documentation.

## 1. Introduction and Goals

### 1.1 Requirements Overview

Enteraksi is a Learning Management System (LMS) designed for:

| Requirement | Description |
|-------------|-------------|
| Indonesian Banking Compliance | Training for OJK regulations, AML, cyber security |
| Multi-content Support | Text, video, audio, documents, live sessions |
| Assessment & Grading | Quizzes with auto and manual grading |
| Progress Tracking | Detailed learner progress and completion |
| Role-based Access | Learner, Content Manager, Trainer, Admin |
| Mobile Responsive | Full functionality on mobile devices |

### 1.2 Quality Goals

| Priority | Goal | Measure |
|----------|------|---------|
| 1 | Usability | Intuitive UI, minimal training needed |
| 2 | Performance | Page load < 2s, video start < 3s |
| 3 | Security | OWASP compliance, data protection |
| 4 | Maintainability | Clear architecture, comprehensive tests |
| 5 | Scalability | Support 10,000+ concurrent users |

### 1.3 Stakeholders

| Role | Expectations |
|------|--------------|
| Learners | Easy access to courses, track progress, mobile support |
| Trainers | Create courses, monitor learner progress |
| Admins | Manage users, publish content, generate reports |
| Developers | Clear architecture, good documentation |
| IT Operations | Easy deployment, monitoring, backups |

---

## 2. Architecture Constraints

### 2.1 Technical Constraints

| Constraint | Background |
|------------|------------|
| PHP 8.4+ | Laravel 12 requirement |
| MySQL 8.0+ / SQLite | Primary data storage |
| Modern browsers | Chrome, Firefox, Safari, Edge (latest 2 versions) |
| HTTPS required | Security compliance |

### 2.2 Organizational Constraints

| Constraint | Background |
|------------|------------|
| Indonesian language | Primary user base |
| OJK compliance | Banking regulation requirements |
| Data residency | Data must remain in Indonesia |

### 2.3 Conventions

| Convention | Description |
|------------|-------------|
| Laravel conventions | PSR-4, service providers, Eloquent |
| Vue 3 Composition API | Modern Vue patterns |
| REST-ful routes | Standard HTTP methods |
| Inertia.js | SPA-like experience without API |

---

## 3. System Scope and Context

### 3.1 Business Context

```
┌─────────────────────────────────────────────────────────────────┐
│                        External Systems                          │
│  ┌─────────┐  ┌─────────┐  ┌─────────┐  ┌─────────────────┐    │
│  │  HRIS   │  │YouTube  │  │  Zoom   │  │  SMTP Server    │    │
│  └────┬────┘  └────┬────┘  └────┬────┘  └────────┬────────┘    │
│       │            │            │                 │              │
└───────┼────────────┼────────────┼─────────────────┼──────────────┘
        │            │            │                 │
        ▼            ▼            ▼                 ▼
┌─────────────────────────────────────────────────────────────────┐
│                                                                  │
│                      Enteraksi LMS                               │
│                                                                  │
│   ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌──────────┐       │
│   │  Course  │  │Assessment│  │  User    │  │ Progress │       │
│   │Management│  │  System  │  │Management│  │ Tracking │       │
│   └──────────┘  └──────────┘  └──────────┘  └──────────┘       │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
        │            │            │                 │
        ▼            ▼            ▼                 ▼
┌───────┴────────────┴────────────┴─────────────────┴──────────────┐
│                           Users                                   │
│  ┌─────────┐  ┌───────────────┐  ┌─────────┐  ┌───────────┐     │
│  │Learners │  │Content Managers│  │Trainers │  │LMS Admins │     │
│  └─────────┘  └───────────────┘  └─────────┘  └───────────┘     │
└──────────────────────────────────────────────────────────────────┘
```

### 3.2 Technical Context

```
┌──────────────────────────────────────────────────────────────────┐
│                         Client Layer                              │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │                    Web Browser                              │  │
│  │  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐     │  │
│  │  │   Vue 3 SPA  │  │ Inertia.js   │  │Tailwind CSS  │     │  │
│  │  │  Components  │  │   Adapter    │  │   Styles     │     │  │
│  │  └──────────────┘  └──────────────┘  └──────────────┘     │  │
│  └────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
                               │
                               │ HTTPS
                               ▼
┌──────────────────────────────────────────────────────────────────┐
│                       Application Layer                           │
│  ┌────────────────────────────────────────────────────────────┐  │
│  │                    Laravel 12                               │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │  │
│  │  │ Controllers │  │  Policies   │  │  Services   │        │  │
│  │  └─────────────┘  └─────────────┘  └─────────────┘        │  │
│  │  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐        │  │
│  │  │   Models    │  │  Requests   │  │  Providers  │        │  │
│  │  └─────────────┘  └─────────────┘  └─────────────┘        │  │
│  └────────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────────┘
                               │
                               ▼
┌──────────────────────────────────────────────────────────────────┐
│                         Data Layer                                │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐           │
│  │    MySQL     │  │ File Storage │  │    Redis     │           │
│  │   Database   │  │   (Media)    │  │   (Cache)    │           │
│  └──────────────┘  └──────────────┘  └──────────────┘           │
└──────────────────────────────────────────────────────────────────┘
```

---

## 4. Solution Strategy

### 4.1 Technology Decisions

| Decision | Rationale |
|----------|-----------|
| Laravel 12 | Mature PHP framework, excellent ORM, built-in auth |
| Vue 3 + Inertia | SPA experience without API complexity |
| Tailwind CSS v4 | Rapid UI development, consistent design |
| MySQL | Reliable, widely supported RDBMS |
| Fortify | Headless auth with 2FA support |

### 4.2 Architecture Decisions

| Decision | Rationale |
|----------|-----------|
| Monolithic architecture | Simpler deployment, sufficient for scale |
| Domain-Driven Design layer | Complex business logic isolation, testability |
| State machine pattern | Clear lifecycle management for Course/Enrollment |
| Strategy pattern | Swappable progress calculators and grading strategies |
| Service layer | Business logic separate from controllers/models |
| Policy-based authorization | Fine-grained access control |
| Polymorphic media | Flexible attachment to any model |
| Soft deletes | Data recovery, audit trail |

See [Architecture Decision Records](../adr/) for detailed rationale.

---

## 5. Building Block View

### 5.1 Level 1: System Context

See diagram in Section 3.1.

### 5.2 Level 2: Container View

| Container | Technology | Purpose |
|-----------|------------|---------|
| Web Application | Laravel + Vue | User interface and business logic |
| Database | MySQL | Persistent data storage |
| File Storage | Local/S3 | Media files (videos, documents) |
| Cache | Redis/Database | Session and cache storage |
| Mail Server | SMTP | Email notifications |

### 5.3 Level 3: Component View

**Backend Components:**
```
app/
├── Domain/               # Domain-Driven Design layer
│   ├── Assessment/       # Grading strategies, DTOs, exceptions
│   ├── Course/           # State machine (draft/published/archived)
│   ├── Enrollment/       # Service, states, events, notifications
│   ├── Progress/         # Tracking service, calculator strategies
│   └── Shared/           # Base contracts, value objects, exceptions
├── Http/Controllers/     # Request handlers (22 controllers)
├── Models/               # Eloquent models (17 models)
├── Policies/             # Authorization rules (7 policies)
├── Services/             # Utility services (MediaSeederHelper, TipTapRenderer)
├── Providers/            # Service container bindings
└── Http/Requests/        # Input validation (19 form requests)
```

**Frontend Components:**
```
resources/js/
├── pages/                # Page components (35 pages)
├── components/           # Reusable components (150+)
├── layouts/              # Page layouts (8 layouts)
├── composables/          # Shared logic (4 composables)
└── types/                # TypeScript definitions
```

---

## 6. Runtime View

### 6.1 Course Enrollment Flow

```
Learner          Browser           Controller        EnrollmentService    Database
   │                │                 │                    │                  │
   │  Click Enroll  │                 │                    │                  │
   │───────────────>│                 │                    │                  │
   │                │  POST /enroll   │                    │                  │
   │                │────────────────>│                    │                  │
   │                │                 │  Check Policy      │                  │
   │                │                 │───────────────────>│                  │
   │                │                 │                    │  Create Enrollment
   │                │                 │                    │─────────────────>│
   │                │                 │                    │  Dispatch Event  │
   │                │                 │                    │  (UserEnrolled)  │
   │                │                 │<───────────────────│                  │
   │                │  Redirect       │                    │                  │
   │                │<────────────────│                    │                  │
   │  Show Course   │                 │                    │                  │
   │<───────────────│                 │                    │                  │
```

### 6.2 Lesson Progress Tracking

```
Learner          Browser           Controller       ProgressService      Database
   │                │                 │                    │                  │
   │  View Lesson   │                 │                    │                  │
   │───────────────>│                 │                    │                  │
   │                │  GET /lesson    │                    │                  │
   │                │────────────────>│                    │                  │
   │                │                 │  getOrCreateProgress│                 │
   │                │                 │───────────────────>│                  │
   │                │                 │                    │─────────────────>│
   │                │  Render Page    │                    │                  │
   │                │<────────────────│                    │                  │
   │  Scroll/Watch  │                 │                    │                  │
   │───────────────>│                 │                    │                  │
   │                │ PATCH /progress │                    │                  │
   │                │────────────────>│ (debounced)        │                  │
   │                │                 │  updatePageProgress │                 │
   │                │                 │───────────────────>│                  │
   │                │                 │                    │  Update DB       │
   │                │                 │                    │─────────────────>│
   │                │                 │                    │  calculateProgress
   │                │                 │                    │  (uses strategy) │
   │                │                 │                    │  Dispatch Events │
```

---

## 7. Deployment View

### 7.1 Development Environment

```
Developer Machine
├── PHP 8.4 + Composer
├── Node.js 20 + npm
├── MySQL/SQLite
└── Local file storage
```

### 7.2 Production Environment

```
┌─────────────────────────────────────────────────────────────┐
│                      Load Balancer                           │
│                        (Nginx)                               │
└─────────────────────────┬───────────────────────────────────┘
                          │
          ┌───────────────┼───────────────┐
          ▼               ▼               ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│   Web Server 1  │ │   Web Server 2  │ │   Web Server N  │
│   PHP-FPM       │ │   PHP-FPM       │ │   PHP-FPM       │
└────────┬────────┘ └────────┬────────┘ └────────┬────────┘
         │                   │                   │
         └───────────────────┼───────────────────┘
                             │
         ┌───────────────────┼───────────────────┐
         ▼                   ▼                   ▼
┌─────────────────┐ ┌─────────────────┐ ┌─────────────────┐
│     MySQL       │ │     Redis       │ │    S3/Storage   │
│   (Primary)     │ │    (Cache)      │ │    (Media)      │
└─────────────────┘ └─────────────────┘ └─────────────────┘
```

---

## 8. Cross-cutting Concepts

### 8.1 Security

- **Authentication**: Laravel Fortify with optional 2FA
- **Authorization**: Policy-based access control
- **Input validation**: Form Request classes
- **XSS prevention**: Automatic escaping in Blade/Vue
- **CSRF protection**: Laravel middleware
- **SQL injection**: Eloquent ORM with parameter binding

### 8.2 Logging & Monitoring

- **Application logs**: `storage/logs/laravel.log`
- **Error tracking**: Laravel exception handler
- **Performance**: Laravel Telescope (development)

### 8.3 Internationalization

- **Primary language**: Bahasa Indonesia
- **Validation messages**: Indonesian
- **Date formatting**: Indonesian locale
- **Number formatting**: Indonesian standards

---

## 9. Architecture Decisions

Key decisions are documented in [Architecture Decision Records](../adr/):

| ADR | Decision |
|-----|----------|
| [ADR-001](../adr/001-inertia-vue.md) | Use Inertia.js with Vue 3 |
| [ADR-002](../adr/002-fortify-auth.md) | Use Laravel Fortify for authentication |
| [ADR-003](../adr/003-progress-tracking.md) | Progress tracking approach |
| [ADR-004](../adr/004-media-storage.md) | Media storage strategy |
| [ADR-005](../adr/005-assessment-types.md) | Assessment question types |

---

## 10. Quality Requirements

### 10.1 Quality Tree

```
Quality
├── Usability
│   ├── Learnability (intuitive interface)
│   └── Accessibility (mobile responsive)
├── Performance
│   ├── Response time (< 2s page load)
│   └── Throughput (1000+ concurrent users)
├── Security
│   ├── Authentication (2FA support)
│   └── Authorization (role-based)
├── Maintainability
│   ├── Modularity (clear separation)
│   └── Testability (80%+ coverage goal)
└── Reliability
    ├── Availability (99.9% uptime goal)
    └── Recoverability (backups, soft deletes)
```

### 10.2 Quality Scenarios

| Scenario | Measure |
|----------|---------|
| Page load time | < 2 seconds for 95th percentile |
| Video start time | < 3 seconds after click |
| Search response | < 500ms for course search |
| Concurrent users | Support 1000 without degradation |

---

## 11. Risks and Technical Debt

### 11.1 Identified Risks

| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Large video uploads fail | Medium | High | Chunked uploads, S3 |
| Database bottleneck | Low | High | Read replicas, caching |
| Third-party dependency | Medium | Medium | Abstraction layers |

### 11.2 Technical Debt

| Item | Description | Priority |
|------|-------------|----------|
| User management UI | Roles changed via database only | Medium |
| Certificate generation | Planned, not implemented | Medium |
| Report/Analytics dashboard | Planned, not built | Low |

### 11.3 Completed Architecture Work

| Item | Phase | Description |
|------|-------|-------------|
| Domain Layer | Phase 1-8 | Full DDD implementation with services, strategies, events |
| State Machines | Phase 5 | Course, Enrollment, AssessmentAttempt lifecycle |
| Progress Strategies | Phase 3 | Lesson-based, Assessment-inclusive, Weighted calculators |
| Grading Strategies | Phase 4 | Auto-grading for MC/TF, manual for essays |
| Domain Events | Phase 6 | Event-driven architecture for notifications |
| Cleanup | Phase 9 | Removed deprecated methods, backward compatibility code |

---

## 12. Glossary

See [Glossary](../glossary.md) for domain terminology.

---

## Related Documents

- [ARCHITECTURE.md](../ARCHITECTURE.md) - Detailed technical architecture
- [DATA-MODEL.md](../DATA-MODEL.md) - Database schema and models
- [FEATURES.md](../FEATURES.md) - Feature flows
- [Security Architecture](./security.md) - Security details
