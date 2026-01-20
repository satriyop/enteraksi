# Course Management Guide

How to create, edit, and manage courses in Enteraksi LMS.

## Prerequisites

- User role: Content Manager, Trainer, or LMS Admin
- Logged in to the system

---

## Creating a Course

### Step 1: Navigate to Courses

1. Log in with a Content Manager, Trainer, or Admin account
2. Click **"Courses"** in the sidebar
3. Click the **"Create Course"** button

### Step 2: Fill Basic Information

| Field | Required | Description |
|-------|----------|-------------|
| Title | Yes | Course name (max 255 characters) |
| Short Description | No | Brief summary (max 500 characters) |
| Long Description | No | Detailed description with formatting |
| Category | No | Select from available categories |
| Difficulty Level | Yes | Beginner, Intermediate, or Advanced |
| Thumbnail | No | Course cover image (JPEG, PNG, WebP, max 2MB) |
| Tags | No | Keywords for search |

### Step 3: Add Learning Objectives (Optional)

Click **"Add Objective"** to add learning goals:
- "Understand basic Python syntax"
- "Build simple command-line applications"

### Step 4: Add Prerequisites (Optional)

Click **"Add Prerequisite"** to specify requirements:
- "Basic computer literacy"
- "No prior programming experience needed"

### Step 5: Save

Click **"Create Course"** to save as draft.

---

## Course Structure

Courses are organized hierarchically:

```
Course
├── Section 1 (e.g., "Introduction")
│   ├── Lesson 1.1 (e.g., "Welcome Video")
│   ├── Lesson 1.2 (e.g., "Course Overview")
│   └── Lesson 1.3 (e.g., "Setup Instructions")
├── Section 2 (e.g., "Fundamentals")
│   ├── Lesson 2.1
│   └── Lesson 2.2
└── Section 3
    └── ...
```

---

## Adding Sections

1. Open your course (click on it from the list)
2. Click **"Add Section"**
3. Enter:
   - **Title**: Section name (e.g., "Getting Started")
   - **Description**: Optional section summary
4. Click **"Save"**

### Reordering Sections

Drag and drop sections to change their order.

---

## Adding Lessons

### Step 1: Create Lesson

1. In the course view, find the section
2. Click **"Add Lesson"** within that section
3. Select the **Content Type**

### Step 2: Choose Content Type

| Type | Best For | File/Input |
|------|----------|------------|
| Text | Written content, articles | Rich text editor |
| Video | Recorded lectures | Upload MP4, WebM, MOV (max 512MB) |
| YouTube | External videos | YouTube URL |
| Audio | Podcasts, narration | Upload MP3, WAV (max 100MB) |
| Document | PDFs, slides | Upload PDF, DOC, PPT (max 50MB) |
| Conference | Live sessions | Zoom/Google Meet URL |

### Step 3: Fill Lesson Details

| Field | Required | Description |
|-------|----------|-------------|
| Title | Yes | Lesson name |
| Description | No | Brief description |
| Duration | No | Estimated time in minutes |
| Free Preview | No | Allow viewing without enrollment |

### Step 4: Add Content

**For Text:**
- Use the rich text editor
- Format with headings, lists, code blocks
- Supports images and links

**For Video/Audio/Document:**
- Click upload area or drag file
- Wait for upload to complete
- Duration auto-detected for video/audio

**For YouTube:**
- Paste the YouTube URL
- Supports: youtube.com/watch?v=... or youtu.be/...

**For Conference:**
- Enter the meeting URL
- Select type: Zoom or Google Meet

### Step 5: Save

Click **"Save Lesson"**

---

## Editing Courses

### Edit Course Details

1. Open the course
2. Click **"Edit"** button
3. Modify fields
4. Click **"Save"**

**Note:** Published courses can only be edited by LMS Admin.

### Edit Sections

1. Click the **pencil icon** next to the section title
2. Modify title or description
3. Click **"Save"**

### Edit Lessons

1. Click on the lesson
2. Click **"Edit"** button
3. Modify content
4. Click **"Save"**

---

## Publishing a Course

Only **LMS Admin** can publish courses.

### Requirements Before Publishing

- [ ] At least one section
- [ ] At least one lesson
- [ ] Course has a title and difficulty level
- [ ] All lessons have content

### How to Publish

1. Open the course
2. Click **"Publish"** button
3. Confirm the action

### What Happens After Publishing

- Course appears in the catalog (if public visibility)
- Learners can enroll
- Course becomes read-only (except for Admin)
- `published_at` timestamp is recorded

---

## Managing Course Status

| Status | Description | Who Can See |
|--------|-------------|-------------|
| Draft | Work in progress | Only creator |
| Published | Live and available | Everyone (per visibility) |
| Archived | Removed from catalog | Only enrolled learners |

### Unpublish

1. Open published course
2. Click **"Unpublish"**
3. Course returns to draft status

### Archive

1. Open published course
2. Click **"Archive"**
3. Course removed from catalog but enrolled learners keep access

---

## Setting Visibility

| Visibility | Who Can Enroll |
|------------|----------------|
| Public | Anyone can enroll |
| Restricted | Only invited learners |
| Hidden | Not shown in catalog |

### Change Visibility

1. Open the course
2. Click **"Edit"**
3. Change **Visibility** dropdown
4. Click **"Save"**

**Note:** Only LMS Admin can change visibility of published courses.

---

## Deleting Courses

### Delete Draft Course

1. Open the draft course
2. Click **"Delete"**
3. Confirm the action

**Note:** Soft delete - can be recovered from database.

### Delete Published Course

Only LMS Admin can delete published courses.

---

## Duration Management

### Automatic Calculation

Course duration is automatically calculated from lesson durations.

### Manual Override

1. Edit the course
2. Set **Manual Duration** field
3. This overrides the automatic calculation

### Recalculate

1. Open the course
2. Click **"Recalculate Duration"**
3. Sums all lesson durations

---

## Best Practices

### Course Design

- Start with clear learning objectives
- Break content into 5-15 minute lessons
- Mix content types (video, text, quiz)
- Include free preview lessons

### Naming Conventions

- **Course**: "Introduction to Python Programming"
- **Section**: "Module 1: Getting Started"
- **Lesson**: "1.1 - Installing Python"

### Content Quality

- Use high-quality video (1080p minimum)
- Compress large files before upload
- Provide downloadable resources
- Include assessments after each section

---

## Common Issues

### "Cannot edit course"

**Cause:** Course is published
**Solution:** Ask LMS Admin to unpublish or edit for you

### "Upload failed"

**Cause:** File too large or wrong format
**Solution:** Check file size limits, use supported formats

### "Duration not updating"

**Cause:** Manual duration override set
**Solution:** Clear manual duration or click "Recalculate"

---

## Related Guides

- [Assessment Creation](./assessments.md) - Add quizzes
- [Media Upload](./media-upload.md) - Detailed upload guide
- [User Invitations](./invitations.md) - Invite learners
