  Top 3 Bad Things

  ---
  🔴 #1: DTOs Holding Eloquent Models (Data Leakage Risk)

  File: app/Domain/Progress/DTOs/ProgressResult.php:12-18

  public function __construct(
      public LessonProgress $progress,  // ❌ ELOQUENT MODEL IN DTO!
      public Percentage $coursePercentage,
      ...
  )

  Problem:
  - DTOs should contain primitives, not Eloquent models
  - toResponse() calls $this->progress->toArray() — leaks whatever attributes the model has
  - If LessonProgress ever gets sensitive fields, they auto-leak to API
  - Defeats the entire purpose of having a DTO layer

  Also in: EnrollmentResult.php (holds Enrollment model)

  ---
  🔴 #2: AssessmentAttemptPolicy Authorization Holes

  File: app/Policies/AssessmentAttemptPolicy.php:15-18, 42-45

  public function viewAny(User $user): bool
  {
      return true;  // ❌ ANYONE can list ALL attempts
  }

  public function create(User $user): bool
  {
      return true;  // ❌ ANYONE can create attempts
  }

  Problem:
  - Any authenticated user can see ALL assessment attempts system-wide
  - Any user can create attempts for courses they're not enrolled in
  - view() method has proper checks, but create() doesn't — inconsistent security
  - Privacy breach waiting to happen

  ---
  🔴 #3: Service Contract Signature Mismatch

  Files:
  - EnrollmentService.php:64-68
  - PathEnrollmentService.php:116-119

  // EnrollmentService
  reactivateEnrollment(Enrollment $enrollment, CreateEnrollmentDTO $dto, bool $preserveProgress)

  // PathEnrollmentService
  reactivateEnrollment(LearningPathEnrollment $enrollment, bool $preserveProgress)
  // ❌ Missing $dto parameter!

  Problem:
  - Two services with same method name, incompatible signatures
  - If code tries to use these polymorphically, it breaks
  - Violates Liskov Substitution Principle
  - No contract/interface enforcing consistent signatures

  ---
  Bonus Issues Found:
  ┌─────────────────────────────┬────────────────────────────────────────┬──────────┐
  │            Issue            │                Location                │ Severity │
  ├─────────────────────────────┼────────────────────────────────────────┼──────────┤
  │ N+1 accessor trap           │ LearningPathEnrollment.php:128-131     │ Medium   │
  ├─────────────────────────────┼────────────────────────────────────────┼──────────┤
  │ Missing error handling      │ UpdatePathProgressOnCourseDrop.php:105 │ Medium   │
  ├─────────────────────────────┼────────────────────────────────────────┼──────────┤
  │ Mixed state string vs class │ PathProgressService.php:96             │ Low      │
  └─────────────────────────────┴────────────────────────────────────────┴──────────┘
  ---
  Verdict: The architecture is good (DDD, services, contracts), but the codebase violates its own patterns in critical security and data areas. These aren't style issues — they're
   bugs.