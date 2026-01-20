# Enteraksi LMS Documentation

Welcome to the Enteraksi Learning Management System documentation. This documentation follows the [Diátaxis framework](https://diataxis.fr/) for technical documentation.

## Documentation Structure

### Tutorials (Learning-oriented)
Step-by-step lessons for newcomers to learn by doing.

- [Getting Started](./getting-started/installation.md) - Set up and run Enteraksi
- [Your First Course](./getting-started/first-course.md) - Create your first course
- [Understanding Roles](./getting-started/roles.md) - Learn about user roles

### How-To Guides (Task-oriented)
Practical guides for accomplishing specific tasks.

- [Course Management](./guides/course-management.md) - Create, edit, publish courses
- [Assessment Creation](./guides/assessments.md) - Build quizzes and tests
- [User Invitations](./guides/invitations.md) - Invite learners to courses
- [Progress Tracking](./guides/progress-tracking.md) - Track learner progress

### Reference (Information-oriented)
Technical reference material for precise information.

- [API Reference](./reference/api.md) - HTTP endpoints and responses
- [Model Reference](./reference/models.md) - Database models and relationships
- [Configuration](./reference/configuration.md) - Environment and config options
- [Data Model (detailed)](./DATA-MODEL.md) - Complete entity documentation

### Explanation (Understanding-oriented)
Discussion and clarification of concepts.

- [Architecture Overview](./architecture/overview.md) - System design and decisions
- [Security Model](./architecture/security.md) - Authentication and authorization
- [Content Delivery](./architecture/content-delivery.md) - How content types work
- [Architecture (detailed)](./ARCHITECTURE.md) - Complete technical architecture

---

## Quick Navigation

| I want to... | Go to... |
|--------------|----------|
| Set up the project | [Installation Guide](./getting-started/installation.md) |
| Understand the codebase | [Architecture Overview](./architecture/overview.md) |
| Create a course | [Course Management Guide](./guides/course-management.md) |
| Look up a model | [Model Reference](./reference/models.md) |
| Understand a decision | [Architecture Decision Records](./adr/) |
| Debug an issue | [Troubleshooting](./troubleshooting.md) |
| Learn the terminology | [Glossary](./glossary.md) |

---

## Document Index

### Core Documentation
| Document | Description | Audience |
|----------|-------------|----------|
| [ARCHITECTURE.md](./ARCHITECTURE.md) | Complete system architecture | Developers |
| [DATA-MODEL.md](./DATA-MODEL.md) | All models, relationships, schema | Developers |
| [FEATURES.md](./FEATURES.md) | Feature flows end-to-end | Developers |

### Getting Started
| Document | Description | Audience |
|----------|-------------|----------|
| [Installation](./getting-started/installation.md) | Setup instructions | New developers |
| [Configuration](./getting-started/configuration.md) | Environment setup | New developers |
| [First Course](./getting-started/first-course.md) | Tutorial: create a course | New developers |
| [Roles](./getting-started/roles.md) | User roles explanation | All |

### Architecture (Arc42)
| Document | Description |
|----------|-------------|
| [Overview](./architecture/overview.md) | Introduction and goals |
| [Constraints](./architecture/constraints.md) | Technical constraints |
| [Context](./architecture/context.md) | System context diagram |
| [Building Blocks](./architecture/building-blocks.md) | Component structure |
| [Runtime View](./architecture/runtime.md) | Key scenarios |
| [Deployment](./architecture/deployment.md) | Deployment architecture |
| [Security](./architecture/security.md) | Security concepts |
| [Content Delivery](./architecture/content-delivery.md) | Content system |

### How-To Guides
| Document | Description |
|----------|-------------|
| [Course Management](./guides/course-management.md) | CRUD for courses |
| [Assessments](./guides/assessments.md) | Create and grade assessments |
| [Invitations](./guides/invitations.md) | Invite and manage learners |
| [Progress Tracking](./guides/progress-tracking.md) | Track completion |
| [Media Upload](./guides/media-upload.md) | Upload videos, documents |
| [Learning Paths](./guides/learning-paths.md) | Create course sequences |

### Reference
| Document | Description |
|----------|-------------|
| [API Reference](./reference/api.md) | HTTP endpoints |
| [Models](./reference/models.md) | Eloquent models |
| [Configuration](./reference/configuration.md) | Config options |
| [Events](./reference/events.md) | System events |
| [Validation Rules](./reference/validation.md) | Form validation |

### Architecture Decision Records
| ADR | Title | Status |
|-----|-------|--------|
| [ADR-001](./adr/001-inertia-vue.md) | Use Inertia.js with Vue 3 | Accepted |
| [ADR-002](./adr/002-fortify-auth.md) | Use Laravel Fortify for auth | Accepted |
| [ADR-003](./adr/003-progress-tracking.md) | Progress tracking approach | Accepted |
| [ADR-004](./adr/004-media-storage.md) | Media storage strategy | Accepted |
| [ADR-005](./adr/005-assessment-types.md) | Assessment question types | Accepted |

### Other
| Document | Description |
|----------|-------------|
| [Glossary](./glossary.md) | Domain terminology |
| [Troubleshooting](./troubleshooting.md) | Common issues |
| [Contributing](./contributing.md) | Contribution guidelines |

---

## Documentation Conventions

### Code Examples
- PHP code blocks use `php` syntax highlighting
- Vue/TypeScript code blocks use `vue` or `typescript`
- Bash commands use `bash`

### Terminology
- **Course** - A learning unit containing sections and lessons
- **Lesson** - A single learning item (text, video, document, etc.)
- **Enrollment** - A user's registration in a course
- See [Glossary](./glossary.md) for complete terminology

### File Paths
- Paths starting with `/` are from project root
- `app/` refers to Laravel application directory
- `resources/js/` refers to Vue frontend

---

## Keeping Documentation Updated

When making code changes:
1. Update relevant reference docs if APIs change
2. Add ADR if making architectural decisions
3. Update guides if workflows change
4. Add troubleshooting entries for new error scenarios

---

*Last updated: January 2026*
