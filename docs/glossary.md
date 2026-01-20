# Glossary

Domain terminology and technical terms used in Enteraksi LMS.

---

## Domain Terms

### A

**Assessment**
A test or quiz that evaluates learner knowledge. Contains questions, has a passing score, and can be graded automatically or manually.

**Attempt**
A single instance of a learner taking an assessment. Tracks start time, answers, score, and completion status.

### C

**Category**
A classification for courses. Examples: "Teknologi Informasi", "Bisnis & Manajemen". Categories can be hierarchical (parent-child).

**Completion**
The state when a learner has finished all required content in a course or lesson. Tracked via progress percentage.

**Content Manager**
A user role that can create and edit their own courses but cannot publish them.

**Course**
A collection of learning content organized into sections and lessons. The primary unit of learning.

**Course Invitation**
An invitation sent to a learner to enroll in a restricted course. Can be accepted, declined, or expire.

### D

**Draft**
The initial status of a course before publication. Draft courses are only visible to their creators.

### E

**Enrollment**
The record of a learner's registration in a course. Tracks status, progress, and completion.

### F

**Free Preview**
A lesson that can be viewed without enrollment. Used to showcase course content.

### G

**Grading**
The process of evaluating assessment answers. Can be automatic (multiple choice) or manual (essays).

### L

**Learner**
The default user role. Can enroll in courses, view content, and take assessments.

**Learning Path**
An ordered sequence of courses that form a curriculum. Courses can have prerequisites and completion requirements.

**Lesson**
A single unit of content within a course section. Can be text, video, audio, document, YouTube, or conference.

**LMS Admin**
The highest user role with full system access. Can publish courses, manage all content, and grade any assessment.

### M

**Media**
Uploaded files attached to courses or lessons. Includes videos, audio, documents, and thumbnails.

### O

**OJK**
Otoritas Jasa Keuangan (Financial Services Authority of Indonesia). The regulatory body for banking compliance training.

### P

**Progress**
The percentage of course content a learner has completed. Calculated from completed lessons.

**Published**
The status of a course that is live and visible to learners. Only LMS Admins can publish.

### Q

**Question**
An individual item in an assessment. Types include multiple choice, true/false, short answer, essay, matching, and file upload.

**Question Option**
A possible answer for a multiple choice or true/false question. One or more can be marked as correct.

### R

**Rating**
A 1-5 star evaluation of a course by an enrolled learner. Can include a text review.

**Restricted**
A course visibility setting where only invited learners can enroll.

### S

**Section**
A group of related lessons within a course. Used to organize content into chapters or modules.

**SCORM**
Sharable Content Object Reference Model. An e-learning standard for content packaging. (Planned support)

### T

**Tag**
A keyword label for courses. Used for search and filtering.

**Trainer**
A user role that can create courses and invite learners. Cannot publish courses.

**Two-Factor Authentication (2FA)**
An additional security layer requiring a TOTP code from an authenticator app.

### X

**xAPI**
Experience API (Tin Can API). A modern e-learning standard for tracking learning experiences. (Planned support)

---

## Technical Terms

### A

**Arc42**
A template for software architecture documentation. Used for the architecture docs.

**ADR (Architecture Decision Record)**
A document capturing an important architectural decision, its context, and consequences.

### C

**Composable**
A Vue 3 function that encapsulates reusable stateful logic (similar to React hooks).

### D

**Diátaxis**
A systematic framework for technical documentation. Separates content into tutorials, how-to guides, reference, and explanation.

### E

**Eloquent**
Laravel's Object-Relational Mapper (ORM) for database operations.

### F

**Form Request**
A Laravel class that handles request validation and authorization.

**Fortify**
Laravel's headless authentication backend. Provides registration, login, 2FA, and password reset.

### G

**Gate**
Laravel's authorization mechanism for checking permissions.

### I

**Inertia.js**
A library that connects server-side frameworks (Laravel) with client-side frameworks (Vue) without building an API.

### M

**Migration**
A Laravel database schema version control file. Defines table structure changes.

**MorphMany/MorphTo**
Eloquent polymorphic relationships. Used for media that can belong to courses or lessons.

### P

**Policy**
A Laravel class that organizes authorization logic for a model.

### S

**Seeder**
A Laravel class that populates the database with test data.

**Soft Delete**
A deletion pattern that marks records as deleted without removing them. Allows recovery.

### T

**TipTap**
A rich text editor for Vue. Used for lesson content editing.

**TOTP**
Time-based One-Time Password. The algorithm used for 2FA codes.

### W

**Wayfinder**
Laravel package that generates TypeScript types for routes. Provides type-safe routing in Vue.

---

## Abbreviations

| Abbreviation | Full Form |
|--------------|-----------|
| AML | Anti-Money Laundering |
| API | Application Programming Interface |
| CRUD | Create, Read, Update, Delete |
| CSS | Cascading Style Sheets |
| FK | Foreign Key |
| HTML | HyperText Markup Language |
| HTTP | HyperText Transfer Protocol |
| JSON | JavaScript Object Notation |
| LMS | Learning Management System |
| LTI | Learning Tools Interoperability |
| ORM | Object-Relational Mapping |
| PDF | Portable Document Format |
| PHP | PHP: Hypertext Preprocessor |
| RBAC | Role-Based Access Control |
| REST | Representational State Transfer |
| SPA | Single Page Application |
| SQL | Structured Query Language |
| SSO | Single Sign-On |
| UI/UX | User Interface / User Experience |
| URL | Uniform Resource Locator |
| UUID | Universally Unique Identifier |

---

## Indonesian Terms

| Indonesian | English | Context |
|------------|---------|---------|
| Bahasa Indonesia | Indonesian language | Primary UI language |
| Kursus | Course | Learning unit |
| Pelajaran | Lesson | Content unit |
| Pelatihan | Training | Learning activity |
| Pengguna | User | System user |
| Peserta | Participant/Learner | Course enrollee |
| Sertifikat | Certificate | Completion document |
| Ujian | Exam/Assessment | Evaluation |

---

## Status Values

### Course Status
| Value | Description |
|-------|-------------|
| draft | Not published, only creator can see |
| published | Live, available to learners |
| archived | Removed from catalog, still accessible to enrolled |

### Course Visibility
| Value | Description |
|-------|-------------|
| public | Anyone can enroll |
| restricted | Only invited learners can enroll |
| hidden | Not shown in catalog |

### Enrollment Status
| Value | Description |
|-------|-------------|
| active | Currently enrolled and learning |
| completed | Finished all content |
| inactive | Paused enrollment |
| dropped | Withdrew from course |

### Invitation Status
| Value | Description |
|-------|-------------|
| pending | Awaiting response |
| accepted | Learner accepted |
| declined | Learner declined |
| expired | Past expiration date |

### Assessment Attempt Status
| Value | Description |
|-------|-------------|
| in_progress | Currently taking |
| submitted | Answers submitted |
| graded | All answers graded |
| completed | Final state |
