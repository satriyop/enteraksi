# Learning Paths Enhancement Plan

> **Feature**: Learning Paths with Prerequisites, Progress Tracking & Auto-Enrollment
> **Status**: Planning
> **Created**: 2026-01-20
> **Estimated Phases**: 6

---

## Executive Summary

This plan enhances the existing Learning Path feature to include learner enrollment, progress tracking, prerequisite enforcement, branching logic, and auto-enrollment capabilities. The existing admin CRUD functionality is solid; this enhancement focuses on the **learner experience** and **business logic**.

---

## Current State Analysis

### What Exists ✅

| Component | Status | Notes |
|-----------|--------|-------|
| Database Tables | ✅ Complete | `learning_paths`, `learning_path_course` pivot |
| LearningPath Model | ✅ Complete | Relationships, scopes, soft deletes |
| Admin CRUD | ✅ Complete | Create, edit, delete, publish/unpublish |
| Course Ordering | ✅ Complete | Drag-drop reordering, position tracking |
| Authorization | ✅ Complete | Policy-based (admin, content_manager, learner) |
| Form Validation | ✅ Complete | StoreLearningPathRequest, UpdateLearningPathRequest |
| Admin UI | ✅ Complete | Index, Create, Show, Edit pages |
| Factory & Tests | ✅ Complete | 47 test cases for CRUD |

### What's Missing ❌

| Component | Priority | Description |
|-----------|----------|-------------|
| Path Enrollment | 🔴 High | Learners cannot enroll in paths |
| Path Progress | 🔴 High | No aggregated progress across courses |
| Prerequisites Logic | 🔴 High | `prerequisites` column exists but not enforced |
| Unlock Conditions | 🟡 Medium | Courses don't unlock sequentially |
| Learner UI | 🔴 High | No "My Learning Paths" page |
| Domain Layer | 🟡 Medium | Business logic in controller |
| Auto-Enrollment | 🟢 Low | No role/department-based auto-enroll |
| Path Completion | 🟡 Medium | No certificate integration |
| Branching | 🟢 Low | No assessment-based path divergence |

---

## User Stories

### Learner Stories

| ID | Story | Priority |
|----|-------|----------|
| US-01 | As a learner, I want to enroll in a learning path so that I can follow a structured curriculum | 🔴 High |
| US-02 | As a learner, I want to see my progress across all courses in a path so that I know how much is left | 🔴 High |
| US-03 | As a learner, I want to see which courses are locked and why so that I understand the prerequisites | 🔴 High |
| US-04 | As a learner, I want courses to unlock automatically when I meet prerequisites so that I can continue learning | 🔴 High |
| US-05 | As a learner, I want to see all my enrolled paths in one place ("My Learning Paths") | 🔴 High |
| US-06 | As a learner, I want to receive a certificate when I complete a learning path | 🟡 Medium |
| US-07 | As a learner, I want to be auto-enrolled in mandatory paths based on my role/department | 🟢 Low |

### Admin Stories

| ID | Story | Priority |
|----|-------|----------|
| US-08 | As an admin, I want to define prerequisites between courses in a path (e.g., "must complete Course A before Course B") | 🔴 High |
| US-09 | As an admin, I want to set minimum completion percentage required to unlock next course | 🔴 High |
| US-10 | As an admin, I want to see how many learners are enrolled in each path and their progress | 🟡 Medium |
| US-11 | As an admin, I want to create auto-enrollment rules (e.g., "all Tellers must take Compliance Path") | 🟢 Low |
| US-12 | As an admin, I want to define branching logic (e.g., "if score < 70% on Assessment A, take remedial course") | 🟢 Low |

---

## Architecture Overview

```
┌─────────────────────────────────────────────────────────────────┐
│                    LEARNING PATH SYSTEM                          │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────┐    ┌───────────────────┐    ┌──────────────┐ │
│  │   Learner    │───▶│  Path Enrollment  │───▶│    Course    │ │
│  │              │    │    Service        │    │  Enrollment  │ │
│  └──────────────┘    └───────────────────┘    └──────────────┘ │
│                              │                                   │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                  Path Progress Service                     │  │
│  │  ┌────────────┐  ┌────────────┐  ┌────────────────────┐  │  │
│  │  │ Calculate  │  │  Check     │  │  Unlock Next      │  │  │
│  │  │ Progress   │  │ Prereqs    │  │  Course           │  │  │
│  │  └────────────┘  └────────────┘  └────────────────────┘  │  │
│  └──────────────────────────────────────────────────────────┘  │
│                              │                                   │
│                              ▼                                   │
│  ┌──────────────────────────────────────────────────────────┐  │
│  │                     Events & Listeners                     │  │
│  │  • PathEnrollmentCreated → Auto-enroll in first course    │  │
│  │  • CourseCompleted → Check prerequisites, unlock next     │  │
│  │  • PathCompleted → Issue certificate (optional)           │  │
│  └──────────────────────────────────────────────────────────┘  │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Database Schema (New Tables)

### `learning_path_enrollments`

```
┌─────────────────────────────────────────────────────────────┐
│                 learning_path_enrollments                    │
├─────────────────────────────────────────────────────────────┤
│ id                  BIGINT PRIMARY KEY                       │
│ learning_path_id    BIGINT FK → learning_paths.id           │
│ user_id             BIGINT FK → users.id                    │
│ status              ENUM('active','completed','dropped')    │
│ enrolled_at         TIMESTAMP                                │
│ completed_at        TIMESTAMP NULLABLE                       │
│ dropped_at          TIMESTAMP NULLABLE                       │
│ progress_percentage INT DEFAULT 0                            │
│ metadata            JSON NULLABLE                            │
│ created_at          TIMESTAMP                                │
│ updated_at          TIMESTAMP                                │
├─────────────────────────────────────────────────────────────┤
│ UNIQUE(learning_path_id, user_id)                           │
│ INDEX(user_id, status)                                       │
│ INDEX(learning_path_id, status)                              │
└─────────────────────────────────────────────────────────────┘
```

### `learning_path_course_progress`

```
┌─────────────────────────────────────────────────────────────┐
│              learning_path_course_progress                   │
├─────────────────────────────────────────────────────────────┤
│ id                        BIGINT PRIMARY KEY                 │
│ learning_path_enrollment_id BIGINT FK                        │
│ course_id                 BIGINT FK → courses.id            │
│ enrollment_id             BIGINT FK → enrollments.id NULL   │
│ status                    ENUM('locked','available',        │
│                                'in_progress','completed')   │
│ unlocked_at               TIMESTAMP NULLABLE                 │
│ started_at                TIMESTAMP NULLABLE                 │
│ completed_at              TIMESTAMP NULLABLE                 │
│ completion_percentage     INT DEFAULT 0                      │
│ created_at                TIMESTAMP                          │
│ updated_at                TIMESTAMP                          │
├─────────────────────────────────────────────────────────────┤
│ UNIQUE(learning_path_enrollment_id, course_id)              │
│ INDEX(status)                                                │
└─────────────────────────────────────────────────────────────┘
```

---

## Implementation Phases

| Phase | Title | Description | Depends On |
|-------|-------|-------------|------------|
| 1 | [Domain Layer](./01-DOMAIN-LAYER.md) | Contracts, services, DTOs, events | - |
| 2 | [Database Enhancement](./02-DATABASE-ENHANCEMENT.md) | New migrations, models, factories | Phase 1 |
| 3 | [Prerequisites & Unlocking](./03-PREREQUISITES-AND-BRANCHING.md) | Prerequisite logic, unlock conditions | Phase 2 |
| 4 | [Learner Experience](./04-LEARNER-EXPERIENCE.md) | Learner UI, "My Paths", progress display | Phase 3 |
| 5 | [Auto-Enrollment](./05-AUTO-ENROLLMENT.md) | Rules engine, role-based enrollment | Phase 4 |
| 6 | [Test Plan](./06-TEST-PLAN.md) | Comprehensive test coverage | All |

---

## API Endpoints (New)

### Learner Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/my-learning-paths` | List enrolled paths with progress |
| POST | `/learning-paths/{path}/enroll` | Enroll in a learning path |
| DELETE | `/learning-paths/{path}/drop` | Drop from a learning path |
| GET | `/learning-paths/{path}/progress` | Get detailed progress for a path |

### Admin Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/admin/learning-paths/{path}/enrollments` | List all enrollments |
| POST | `/admin/learning-paths/{path}/enroll-user` | Manually enroll a user |
| GET | `/admin/learning-paths/{path}/analytics` | Path analytics |

---

## Success Criteria

### Functional Requirements

- [ ] Learners can enroll in published learning paths
- [ ] Progress is calculated and displayed (aggregated across courses)
- [ ] Courses unlock based on prerequisites (previous course completion)
- [ ] "My Learning Paths" page shows all enrolled paths with progress
- [ ] Locked courses show clear messaging about what's needed to unlock
- [ ] Path completion triggers optional certificate issuance

### Non-Functional Requirements

- [ ] Path enrollment operation < 500ms
- [ ] Progress calculation uses efficient queries (no N+1)
- [ ] All new code covered by tests (>90%)
- [ ] Mobile-responsive learner UI

---

## Risks & Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Complex prerequisite logic | High | Start simple (linear unlock), add branching later |
| Performance with many courses | Medium | Cache progress calculations |
| Existing enrollments not linked to paths | Low | Migration script to link existing enrollments |
| Certificate integration complexity | Low | Make certificate optional, integrate later |

---

## File Index

| File | Purpose |
|------|---------|
| [00-INDEX.md](./00-INDEX.md) | This file - overview and anchor |
| [01-DOMAIN-LAYER.md](./01-DOMAIN-LAYER.md) | Domain contracts, services, DTOs, events |
| [02-DATABASE-ENHANCEMENT.md](./02-DATABASE-ENHANCEMENT.md) | Migrations, models, relationships |
| [03-PREREQUISITES-AND-BRANCHING.md](./03-PREREQUISITES-AND-BRANCHING.md) | Prerequisite logic and unlock conditions |
| [04-LEARNER-EXPERIENCE.md](./04-LEARNER-EXPERIENCE.md) | Learner UI pages and components |
| [05-AUTO-ENROLLMENT.md](./05-AUTO-ENROLLMENT.md) | Auto-enrollment rules engine |
| [06-TEST-PLAN.md](./06-TEST-PLAN.md) | Comprehensive test plan |

---

## Next Steps

1. Review and approve this plan
2. Start with Phase 1 (Domain Layer) implementation
3. Proceed phase by phase, running tests after each phase
