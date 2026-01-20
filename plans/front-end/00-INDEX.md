# Enteraksi Frontend Refactoring Master Plan

## Executive Summary

This document outlines a comprehensive refactoring strategy for the Enteraksi LMS frontend built with Vue 3, TypeScript, Inertia.js v2, and Tailwind CSS v4. The goal is to transform the current codebase into a maintainable, scalable, and testable architecture while preserving existing functionality.

---

## Current State Assessment

### Tech Stack
| Technology | Version | Status |
|------------|---------|--------|
| Vue.js | 3.5 | ✅ Current |
| TypeScript | 5.7 | ✅ Current |
| Inertia.js | 2.1 | ✅ Current |
| Tailwind CSS | 4.1 | ✅ Current |
| shadcn-vue | Latest | ✅ Current |
| Wayfinder | 0.x | ✅ Current |

### Codebase Metrics
| Metric | Value | Assessment |
|--------|-------|------------|
| Total Vue Components | 211 | Large codebase |
| Page Components | 39 | Moderate |
| UI Components (shadcn) | 168 | Well-equipped |
| Custom Composables | 4 | **Critical Gap** |
| Lines of Vue Code | 41,352 | Significant |
| Centralized Types | 40 lines | **Critical Gap** |
| Utility Functions | 3 | **Critical Gap** |

### Technical Debt Score: **B-** (6.2/10)

### Critical Issues Identified

| Issue | Severity | Impact | Occurrences |
|-------|----------|--------|-------------|
| Type Duplication | 🔴 High | Maintenance nightmare | 40+ interfaces |
| Utility Duplication | 🔴 High | Bug propagation risk | 10+ files |
| Monolithic Components | 🟠 Medium | Hard to test/maintain | 6 components >500 lines |
| Missing Composables | 🟠 Medium | Logic duplication | Only 4 exist |
| No Component Tests | 🔴 High | Regression risk | 0% coverage |

---

## Refactoring Phases

### Phase 1: [Type System Centralization](./01-TYPE-SYSTEM.md)
**Duration:** 1-2 weeks
**Risk:** Low
**Impact:** High

- Centralize all TypeScript interfaces
- Create domain model types
- Implement type guards and utilities
- Generate types from Laravel models

### Phase 2: [Utility Extraction](./02-UTILITIES.md)
**Duration:** 1 week
**Risk:** Low
**Impact:** Medium

- Extract duplicated utility functions
- Create formatting utilities
- Implement validation helpers
- Build date/time utilities

### Phase 3: [Component Architecture](./03-COMPONENT-ARCHITECTURE.md)
**Duration:** 3-4 weeks
**Risk:** Medium
**Impact:** High

- Decompose monolithic components
- Implement compound component pattern
- Create feature-based organization
- Establish component guidelines

### Phase 4: [Composables Strategy](./04-COMPOSABLES.md)
**Duration:** 2-3 weeks
**Risk:** Medium
**Impact:** High

- Extract business logic to composables
- Create data fetching composables
- Implement form handling patterns
- Build reusable UI logic

### Phase 5: [State Management](./05-STATE-MANAGEMENT.md)
**Duration:** 1-2 weeks
**Risk:** Low
**Impact:** Medium

- Define state boundaries
- Implement provide/inject patterns
- Create stores for shared state
- Handle optimistic updates

### Phase 6: [Testing Strategy](./06-TESTING.md)
**Duration:** 2-3 weeks
**Risk:** Low
**Impact:** High

- Set up Vitest + Vue Test Utils
- Create component testing patterns
- Implement composable tests
- Build integration tests

### Phase 7: [Performance Optimization](./07-PERFORMANCE.md)
**Duration:** 1-2 weeks
**Risk:** Low
**Impact:** Medium

- Implement lazy loading
- Optimize bundle splitting
- Add virtual scrolling
- Memory leak prevention

### Phase 8: [Migration Guide](./08-MIGRATION-GUIDE.md)
**Duration:** Ongoing
**Risk:** N/A
**Impact:** N/A

- Step-by-step rollout strategy
- Feature flag integration
- Rollback procedures
- Team onboarding

---

## Implementation Timeline

```
Week 1-2:   [████████████████████] Phase 1: Type System
Week 2-3:   [██████████] Phase 2: Utilities
Week 3-7:   [████████████████████████████████████████] Phase 3: Components
Week 7-10:  [██████████████████████████████] Phase 4: Composables
Week 10-12: [████████████████████] Phase 5: State Management
Week 12-15: [██████████████████████████████] Phase 6: Testing
Week 15-17: [████████████████████] Phase 7: Performance
Ongoing:    [░░░░░░░░░░] Phase 8: Migration
```

**Total Estimated Duration:** 15-17 weeks

---

## Success Metrics

### Code Quality Metrics
| Metric | Current | Target |
|--------|---------|--------|
| Type Coverage | ~30% | 95% |
| Duplicated Code | High | <5% |
| Avg Component Size | 320 lines | <200 lines |
| Composables Count | 4 | 25+ |
| Test Coverage | 0% | 70% |

### Developer Experience Metrics
| Metric | Current | Target |
|--------|---------|--------|
| New Feature Time | Days | Hours |
| Bug Fix Time | Hours | Minutes |
| Onboarding Time | Weeks | Days |
| IDE Autocomplete | Partial | Full |

### Performance Metrics
| Metric | Current | Target |
|--------|---------|--------|
| Initial Bundle | TBD | <200KB |
| Time to Interactive | TBD | <3s |
| Lighthouse Score | TBD | >90 |

---

## Risk Mitigation

### Technical Risks
| Risk | Probability | Impact | Mitigation |
|------|-------------|--------|------------|
| Breaking existing features | Medium | High | Comprehensive testing before changes |
| Type migration errors | Low | Medium | Gradual adoption with `any` fallback |
| Bundle size increase | Low | Low | Code splitting and tree shaking |
| Team resistance | Low | Medium | Clear documentation and training |

### Rollback Strategy
Each phase includes rollback procedures. See [Migration Guide](./08-MIGRATION-GUIDE.md) for details.

---

## Architecture Principles

### 1. Single Source of Truth
- Types defined once in `/types`
- Utilities centralized in `/lib`
- Shared state in composables

### 2. Composition over Inheritance
- Prefer composables over mixins
- Use compound components
- Leverage Vue 3 Composition API

### 3. Colocation
- Keep related code together
- Feature-based organization
- Tests alongside components

### 4. Progressive Enhancement
- Core functionality first
- Enhance with interactivity
- Graceful degradation

### 5. Type Safety
- Strict TypeScript mode
- Generated types from backend
- Runtime validation where needed

---

## Directory Structure (Target)

```
resources/js/
├── components/
│   ├── ui/                    # shadcn-vue components
│   └── features/              # Feature-specific components
│       ├── course/
│       │   ├── CourseCard.vue
│       │   ├── CourseForm/
│       │   │   ├── index.vue
│       │   │   ├── BasicInfoStep.vue
│       │   │   ├── ContentStep.vue
│       │   │   └── SettingsStep.vue
│       │   └── CourseProgress.vue
│       ├── lesson/
│       ├── assessment/
│       └── enrollment/
├── composables/
│   ├── data/                  # Data fetching
│   │   ├── useCourse.ts
│   │   ├── useLesson.ts
│   │   └── useAssessment.ts
│   ├── forms/                 # Form handling
│   │   ├── useCourseForm.ts
│   │   └── useAssessmentForm.ts
│   ├── ui/                    # UI logic
│   │   ├── useModal.ts
│   │   └── useToast.ts
│   └── features/              # Feature logic
│       ├── useProgressTracking.ts
│       └── useGrading.ts
├── lib/
│   ├── utils.ts               # General utilities
│   ├── formatters.ts          # Formatting functions
│   ├── validators.ts          # Validation helpers
│   └── constants.ts           # App constants
├── types/
│   ├── index.d.ts             # Main export
│   ├── models/                # Domain models
│   │   ├── course.ts
│   │   ├── lesson.ts
│   │   ├── user.ts
│   │   └── assessment.ts
│   ├── api/                   # API response types
│   └── components/            # Component prop types
├── pages/                     # Inertia pages
└── layouts/                   # Layout components
```

---

## Quick Reference

| Document | Purpose |
|----------|---------|
| [01-TYPE-SYSTEM.md](./01-TYPE-SYSTEM.md) | TypeScript type centralization |
| [02-UTILITIES.md](./02-UTILITIES.md) | Utility function extraction |
| [03-COMPONENT-ARCHITECTURE.md](./03-COMPONENT-ARCHITECTURE.md) | Component decomposition |
| [04-COMPOSABLES.md](./04-COMPOSABLES.md) | Composables strategy |
| [05-STATE-MANAGEMENT.md](./05-STATE-MANAGEMENT.md) | State handling patterns |
| [06-TESTING.md](./06-TESTING.md) | Testing strategy |
| [07-PERFORMANCE.md](./07-PERFORMANCE.md) | Performance optimization |
| [08-MIGRATION-GUIDE.md](./08-MIGRATION-GUIDE.md) | Rollout and migration |

---

## Getting Started

1. **Read this document** to understand the overall strategy
2. **Start with Phase 1** (Type System) - lowest risk, highest immediate impact
3. **Follow phase order** - each phase builds on the previous
4. **Use feature flags** for gradual rollout
5. **Test continuously** - don't wait for Phase 6
