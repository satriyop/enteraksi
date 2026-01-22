Round 6: Brutal Code Review Results

  #1: DUPLICATE GRADING LOGIC WITH INCONSISTENT SCOPING (HIGH)

  Files:
  - app/Http/Controllers/AssessmentController.php:386-432
  - app/Http/Controllers/QuestionController.php:250-300

  Problem: Two submitGrade methods exist doing the same thing with different patterns:

  // AssessmentController.php:400 - UNSCOPED (weaker pattern)
  $answer = AttemptAnswer::find($gradeData['answer_id']);
  if ($answer && $answer->attempt_id === $attempt->id) { ... }

  // QuestionController.php:267 - SCOPED (better pattern)
  $answer = $attempt->answers()->find($answerData['id']);

  Why it's bad:
  1. DRY violation - Same business logic duplicated in two places
  2. Inconsistent security patterns - One uses scoped query, other doesn't
  3. Different field names - One validates answer_id, other validates id
  4. Maintenance nightmare - Bug fix in one won't fix the other

  Severity: HIGH - Code quality + potential security inconsistency

  ---
  #2: HARDCODED MYSQL ERROR CODE - DATABASE LOCK-IN (MEDIUM)

  Files:
  - app/Http/Controllers/EnrollmentController.php:70-76, 203-209
  - app/Http/Controllers/LearningPathEnrollmentController.php:150-158

  Problem:
  } catch (QueryException $e) {
      if ($e->errorInfo[1] === 1062) {  // ← MySQL-specific!
          return back()->with('error', 'Already enrolled');
      }
      throw $e;
  }

  Why it's bad:
  1. MySQL lock-in - Code 1062 = MySQL duplicate key. PostgreSQL uses 23505
  2. Implementation-dependent - errorInfo[1] structure not guaranteed
  3. Hides real bugs - If locking works, this catch should never trigger. If it does trigger, it means the lock FAILED

  Better approach:
  // Database-agnostic check
  if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry')) {
      // Handle duplicate
  }

  Severity: MEDIUM - Portability issue, not immediate breakage

  ---
  #3: INCONSISTENT STATE CONSTANT USAGE (LOW - CODE SMELL)

  File: app/Domain/LearningPath/Services/PathEnrollmentService.php:272

  Problem:
  // Line 272 - hardcoded string
  $completedCourses = $enrollment->courseProgress()
      ->where('state', 'completed')  // ← Raw string
      ->count();

  // vs. everywhere else uses the constant
  ->where('state', CompletedCourseState::$name)  // ← Proper

  Why it's bad:
  1. Inconsistency - Same file uses CompletedCourseState::$name on other lines (262, 270)
  2. Refactoring risk - If state name changes, grep won't find this
  3. IDE support lost - Can't find usages, can't refactor safely

  Severity: LOW - Works today, technical debt for tomorrow

  ---
  Summary
  ┌─────┬────────────────────────────────────────────────────────┬────────────────┬──────────┬───────────────────────────────────┐
  │  #  │                         Issue                          │      Type      │ Severity │            Fix Effort             │
  ├─────┼────────────────────────────────────────────────────────┼────────────────┼──────────┼───────────────────────────────────┤
  │ 1   │ Duplicate grading endpoints with inconsistent patterns │ DRY + Security │ HIGH     │ Medium (consolidate into service) │
  ├─────┼────────────────────────────────────────────────────────┼────────────────┼──────────┼───────────────────────────────────┤
  │ 2   │ Hardcoded MySQL error code 1062                        │ Portability    │ MEDIUM   │ Low (use database-agnostic check) │
  ├─────┼────────────────────────────────────────────────────────┼────────────────┼──────────┼───────────────────────────────────┤
  │ 3   │ Hardcoded state string instead of constant             │ Code Quality   │ LOW      │ Trivial (use constant)            │
  └─────┴────────────────────────────────────────────────────────┴────────────────┴──────────┴───────────────────────────────────┘