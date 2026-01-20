# Assessment Creation Guide

How to create quizzes, tests, and assessments in Enteraksi LMS.

## Prerequisites

- A published or draft course
- User role: Content Manager, Trainer, or LMS Admin

---

## Assessment Overview

Assessments are used to evaluate learner knowledge. They consist of:
- Questions (various types)
- Settings (time limit, passing score, attempts)
- Grading (automatic or manual)

---

## Creating an Assessment

### Step 1: Navigate to Assessments

1. Open your course
2. Click the **"Assessments"** tab
3. Click **"Create Assessment"**

### Step 2: Basic Information

| Field | Required | Description |
|-------|----------|-------------|
| Title | Yes | Assessment name |
| Description | No | What the assessment covers |
| Instructions | No | Directions for learners |

### Step 3: Settings

| Setting | Description | Default |
|---------|-------------|---------|
| Time Limit | Minutes allowed (empty = unlimited) | None |
| Passing Score | Minimum percentage to pass (0-100) | 70 |
| Max Attempts | How many tries allowed (1-10) | 3 |
| Shuffle Questions | Randomize order each attempt | Yes |
| Show Correct Answers | Display answers after submission | Yes |
| Allow Review | Let learners review their attempt | Yes |

### Step 4: Save

Click **"Create Assessment"** to save as draft.

---

## Adding Questions

### Navigate to Questions

1. Open the assessment
2. Click **"Manage Questions"**

### Question Types

| Type | Description | Auto-Graded |
|------|-------------|-------------|
| Multiple Choice | Select one correct answer | Yes |
| True/False | Two options | Yes |
| Short Answer | Brief text response | No |
| Essay | Long text response | No |
| Matching | Match pairs | No |
| File Upload | Submit a file | No |

---

## Creating Multiple Choice Questions

1. Click **"Add Question"**
2. Select **"Multiple Choice"**
3. Fill in:
   - **Question Text**: The question
   - **Points**: How many points (default: 1)
   - **Feedback**: Optional explanation shown after
4. Add options:
   - Click **"Add Option"**
   - Enter option text
   - Check **"Correct"** for the right answer
   - Optionally add feedback per option
5. Click **"Save"**

### Example

**Question:** What is the capital of Indonesia?
- [ ] Surabaya
- [x] Jakarta *(correct)*
- [ ] Bandung
- [ ] Bali

---

## Creating True/False Questions

1. Click **"Add Question"**
2. Select **"True/False"**
3. Fill in:
   - **Question Text**: A statement
   - **Correct Answer**: True or False
   - **Points**: How many points
4. Click **"Save"**

### Example

**Statement:** Python is a compiled language.
**Answer:** False

---

## Creating Short Answer Questions

1. Click **"Add Question"**
2. Select **"Short Answer"**
3. Fill in:
   - **Question Text**: The question
   - **Points**: How many points
   - **Expected Answer** (optional): For grader reference
4. Click **"Save"**

**Note:** Short answer questions require manual grading.

---

## Creating Essay Questions

1. Click **"Add Question"**
2. Select **"Essay"**
3. Fill in:
   - **Question Text**: The prompt
   - **Points**: How many points
   - **Rubric** (optional): Grading criteria
4. Click **"Save"**

**Note:** Essay questions require manual grading.

---

## Creating File Upload Questions

1. Click **"Add Question"**
2. Select **"File Upload"**
3. Fill in:
   - **Question Text**: What to submit
   - **Points**: How many points
   - **Accepted Formats** (optional): e.g., "PDF, DOCX"
4. Click **"Save"**

**Note:** File uploads require manual grading.

---

## Reordering Questions

Drag and drop questions to change their order.

---

## Editing Questions

1. Click on the question
2. Modify fields
3. Click **"Save"**

**Note:** Cannot edit questions in published assessments.

---

## Publishing an Assessment

Only **LMS Admin** can publish assessments.

### Before Publishing

- [ ] At least one question
- [ ] Passing score is set
- [ ] Time limit is appropriate (if set)

### How to Publish

1. Open the assessment
2. Click **"Publish"**
3. Confirm

### What Happens

- Learners can now take the assessment
- Questions become read-only
- Attempts are recorded

---

## Grading Assessments

### Automatic Grading

Multiple choice and true/false questions are graded instantly:
- Correct answer = full points
- Wrong answer = 0 points

### Manual Grading

For short answer, essay, matching, and file upload:

1. Go to **"Assessments"** → **"View Attempts"**
2. Find attempts with status **"Needs Grading"**
3. Click **"Grade"**
4. For each question:
   - View the learner's answer
   - Assign points (0 to max)
   - Add feedback (optional)
5. Click **"Submit Grades"**

### Grading Tips

- Be consistent across learners
- Provide constructive feedback
- Use rubrics for essays
- Partial credit is allowed

---

## Viewing Attempt Results

### For Instructors

1. Open the assessment
2. Click **"Attempts"**
3. View list of all attempts with:
   - Learner name
   - Score / Max Score
   - Percentage
   - Pass/Fail status
   - Submission time

### For Learners

After submission (if `show_correct_answers` enabled):
- View their answers
- See correct answers
- Read feedback

---

## Assessment Settings Explained

### Time Limit

- **Recommended:** 1-2 minutes per question
- **Example:** 20 questions → 30-40 minutes
- When time expires, attempt auto-submits

### Passing Score

- **Standard:** 70-80%
- **Compliance training:** Often 80-100%
- Learners see pass/fail result

### Max Attempts

- **Quizzes:** 2-3 attempts
- **Final exams:** 1 attempt
- Each attempt is tracked separately

### Shuffle Questions

- Prevents cheating
- Questions appear in random order
- Options within questions also shuffle (for MC)

---

## Best Practices

### Question Design

- One concept per question
- Clear, unambiguous wording
- Avoid "trick" questions
- Balance difficulty levels

### Assessment Structure

- Start with easier questions
- Mix question types
- 10-20 questions is typical
- Include feedback for learning

### Anti-Cheating

- Enable question shuffling
- Set appropriate time limits
- Use question banks (future feature)
- Mix question types

---

## Common Issues

### "Cannot start assessment"

**Causes:**
- Assessment not published
- Max attempts reached
- Not enrolled in course

### "Timer ran out"

**What happens:**
- Attempt auto-submits with current answers
- Unanswered questions get 0 points

### "Waiting for grading"

**Cause:** Has questions requiring manual grading
**Solution:** Instructor must grade the attempt

---

## Related Guides

- [Course Management](./course-management.md) - Create courses first
- [Progress Tracking](./progress-tracking.md) - Track completion
