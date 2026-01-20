# LMS Backend Architecture Refactoring Plan

## Executive Summary

This comprehensive plan transforms the Enteraksi LMS backend from a well-intentioned but architecturally constrained codebase into a **clean, maintainable, enterprise-grade application** following SOLID principles, design patterns, and Laravel best practices.

### Current State Assessment

| Metric | Score | Status |
|--------|-------|--------|
| Code Organization | 6/10 | Fair |
| Separation of Concerns | 4/10 | Poor |
| Testability | 5/10 | Fair |
| Maintainability | 5/10 | Fair |
| Scalability | 4/10 | Poor |
| SOLID Compliance | 4/10 | Poor |
| **Overall** | **4.7/10** | **Needs Refactoring** |

### Target State Goals

- **Technical Debt**: Minimize by 70%
- **Stability**: 99.5% reliability for core flows
- **Testability**: 90%+ code coverage capability
- **Extensibility**: Plugin-ready architecture
- **Flexibility**: Strategy-swappable components
- **Observability**: Full event tracking, debugging ease

---

## Architecture Vision

### Before (Current)
```
HTTP Request → Controller → Model → Database
              (bloated)   (god model)
```

### After (Target)
```
HTTP Request → Controller → Service → Repository → Database
                  ↓           ↓            ↓
              Validator   Domain     State Machine
                  ↓       Events         ↓
              Strategy              Event Listeners
              Pattern                    ↓
                                   Notifications
                                   Audit Logs
                                   Integrations
```

---

## Phased Implementation

### Phase 1: Foundation & Infrastructure
**File**: [01-FOUNDATION.md](./01-FOUNDATION.md)
**Duration**: Week 1-2
**Focus**: Base patterns, contracts, directory structure

Key deliverables:
- Directory restructure following Domain-Driven Design (lite)
- Base contracts/interfaces for core patterns
- Exception hierarchy
- DTO and Value Object foundations

### Phase 2: Service Layer Extraction
**File**: [02-SERVICE-LAYER.md](./02-SERVICE-LAYER.md)
**Duration**: Week 3-5
**Focus**: Extract business logic from models and controllers

Key deliverables:
- EnrollmentService
- ProgressTrackingService
- AssessmentGradingService
- CoursePublishingService
- ContentDeliveryService

### Phase 3: State Machine Implementation
**File**: [03-STATE-MACHINES.md](./03-STATE-MACHINES.md)
**Duration**: Week 6-7
**Focus**: Formal state management

Key deliverables:
- Course state machine (Draft → Published → Archived)
- Enrollment state machine (Active → Completed/Dropped)
- AssessmentAttempt state machine (InProgress → Submitted → Graded → Completed)
- State transition guards and validators

### Phase 4: Event-Driven Architecture
**File**: [04-EVENT-DRIVEN.md](./04-EVENT-DRIVEN.md)
**Duration**: Week 8-9
**Focus**: Decoupling through events

Key deliverables:
- Domain events for all state transitions
- Event listeners for side effects
- Notification system foundation
- Audit logging through events

### Phase 5: Dependency Injection & Strategy Patterns
**File**: [05-DI-STRATEGY.md](./05-DI-STRATEGY.md)
**Duration**: Week 10-11
**Focus**: Flexibility and testability through DI and strategies

Key deliverables:
- GradingStrategy interface and implementations
- NotificationStrategy interface
- ProgressCalculator strategies
- Service provider bindings
- Container-based dependency resolution

### Phase 6: Observability & Debugging
**File**: [06-OBSERVABILITY.md](./06-OBSERVABILITY.md)
**Duration**: Week 12
**Focus**: Monitoring, logging, debugging

Key deliverables:
- Structured logging
- Event timeline tracking
- Health checks and metrics
- Debug mode enhancements
- Error context enrichment

### Phase 7: Testing Strategy
**File**: [07-TESTING-STRATEGY.md](./07-TESTING-STRATEGY.md)
**Duration**: Ongoing (parallel)
**Focus**: Comprehensive test coverage

Key deliverables:
- Unit tests for services
- Integration tests for state machines
- Feature tests for complete flows
- Contract tests for strategies
- Test doubles and factories

### Phase 8: Migration & Rollout Guide
**File**: [08-MIGRATION-GUIDE.md](./08-MIGRATION-GUIDE.md)
**Duration**: Reference document
**Focus**: Safe rollout strategy

Key deliverables:
- Feature flag strategy
- Database migration safety
- Rollback procedures
- Performance monitoring during rollout

### Phase 9: Cleanup and Simplification
**File**: [09-CLEANUP-AND-SIMPLIFICATION.md](./09-CLEANUP-AND-SIMPLIFICATION.md)
**Duration**: Week 13 (Development Phase)
**Focus**: Remove technical debt and simplify codebase

Key deliverables:
- Remove all deprecated methods from models
- Add missing `is_required` column to assessments
- Remove backward compatibility code
- Simplify feature flag infrastructure
- Safety net tests for each cleanup phase

---

## Key Design Decisions

### 1. Domain-Driven Design (Lite)
We adopt a pragmatic DDD approach without going full aggregate-root complexity. The focus is on:
- Clear domain boundaries
- Rich domain models
- Services that represent domain operations

### 2. Spatie State Machine vs Custom
**Decision**: Use `spatie/laravel-model-states` for:
- Battle-tested implementation
- Laravel integration
- Transition hooks
- Easy state querying

### 3. Repository Pattern: Optional
**Decision**: Skip full repository pattern, use:
- Query classes for complex reads
- Services for write operations
- Models retain Eloquent convenience

### 4. Event Sourcing: Not Now
**Decision**: Skip event sourcing, use:
- Domain events for decoupling
- Traditional database persistence
- Event listeners for side effects

---

## Critical Code Smells Addressed

| Smell | Location | Fix |
|-------|----------|-----|
| God Model | `Enrollment`, `LessonProgress` | Extract to services |
| Fat Controller | `AssessmentController:263-334` | Extract to `GradingService` |
| Feature Envy | `LessonProgress::markCompleted()` | Move logic to service |
| Primitive Obsession | State strings everywhere | State objects/enums |
| Missing Abstraction | No grading strategies | Strategy pattern |
| Tight Coupling | Models calling models | Event-based decoupling |
| No Events | Zero events/listeners | Full event coverage |

---

## Risk Mitigation

### Technical Risks

| Risk | Mitigation |
|------|------------|
| Regression during refactor | Comprehensive test suite before refactoring |
| Performance degradation | Benchmark critical paths before/after |
| Migration data corruption | Database backup and transaction usage |
| Team learning curve | Documentation and pair programming |

### Business Risks

| Risk | Mitigation |
|------|------------|
| Extended downtime | Feature flags, gradual rollout |
| Lost functionality | Parallel running old/new code |
| User confusion | No UI changes during backend refactor |

---

## Success Metrics

### Code Quality
- [ ] 0 Psalm/PHPStan level 5 errors
- [ ] All services have interfaces
- [ ] No controller method > 20 lines
- [ ] No model with business logic

### Testability
- [ ] 90% unit test coverage for services
- [ ] All state transitions tested
- [ ] All strategies have contract tests

### Observability
- [ ] All state changes emit events
- [ ] Structured logging for all services
- [ ] Health endpoints for core services

### Performance
- [ ] No regression in response times
- [ ] Query count unchanged or improved
- [ ] Memory usage unchanged or improved

---

## Quick Reference: Files to Touch

### High Impact Refactoring Targets

```
app/
├── Http/Controllers/
│   ├── AssessmentController.php    ★★★ HIGH - Extract grading logic
│   ├── CourseController.php        ★★ MEDIUM - Slim down
│   ├── CoursePublishController.php ★★★ HIGH - State machine
│   └── LessonProgressController.php ★★ MEDIUM - Service extraction
├── Models/
│   ├── Enrollment.php              ★★★ HIGH - God model
│   ├── LessonProgress.php          ★★★ HIGH - God model
│   ├── Course.php                  ★★ MEDIUM - State machine
│   ├── AssessmentAttempt.php       ★★★ HIGH - State machine
│   └── Assessment.php              ★★ MEDIUM - Clean up
└── Services/
    └── (empty - needs creation)     ★★★ CRITICAL
```

---

## Timeline Overview

```
Week 1-2:   ████████ Phase 1: Foundation
Week 3-5:   ████████████ Phase 2: Services
Week 6-7:   ████████ Phase 3: State Machines
Week 8-9:   ████████ Phase 4: Events
Week 10-11: ████████ Phase 5: DI & Strategy
Week 12:    ████ Phase 6: Observability
Ongoing:    ░░░░░░░░░░░░░░░░ Phase 7: Testing (parallel)
Reference:  ░░░░ Phase 8: Migration Guide
Week 13:    ████ Phase 9: Cleanup & Simplification
```

---

## Next Steps

1. **Read Phase 1** - [01-FOUNDATION.md](./01-FOUNDATION.md)
2. **Set up base structure**
3. **Begin incremental refactoring**

> "The best refactoring is the one that doesn't break anything while making everything better." - Every Senior Developer Ever
