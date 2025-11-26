# General Requirement
- the application is learning management system, use common best practice of building LMS by industry standard : SCORM, xAPI, and LTI.
- mobile friendly (responsive) user interface is mandatory.
- always build test code. All features or user story should has test code.
- build for Indonesian. such as : if you seed any data use indonesian context (names, address, etc).


#  Course User Story
=== CONTENT MANAGER ROLE ===
## user with content manager role
### Course
- **management(CRUD)**
- able to manage their own courses using Course CRUD.
- able to delete course Lesson using Course CRUD.
- able to create course by first create course outline (Section and Lesson), where he can drag and drop section and lesson (accordion).
- able to create/edit a Lesson with interactive in one page only (see course creation for details).
- able to link Assessment to Section or Lesson or Course.
- able to link Competencies to course.

- **creation** 
- able to set course thumbnail by upload image.
- able to create course outline by defining : course title, description (short & long), objectives (what you'll learn), prerequisites, categories,tags.
- able to create course content/Lesson using WYSIWYG editor for rich text content, upload assets, drag-and-drop module order.
- able to upload several types of content : Video, Audio, Document (PDF, PPT, DOC).
- able to put youtube link as part of the lesson, the link will be rendered as video automatically for learner user.
- able to put video conference call such as zoom / google meet link on specific lesson/course.
- able to set course difficulty level (default : beginner/intermediate/advance).
- system able to set estimate section duration and course duration (based on video duration, average reading, video conf duration,etc).
- able to edit section duration and course duration estimated by system.
- able to ask system to re-estimate section duration and course duration.

- **publishing** 
- created course as set as status as : Draft and wait for lms admin to publish it.
- created course default visibility is : Public.
- when course is publish by lms admin/admin, course status become Published.
- not able to edit course when course status is Published.

- **importing (Future Features)** :
- able to import h5p content.
- able to import SCORM content.
- able to do LTI integration.

### Learning paths
- able to manage learning path using Learning Path CRUD.
- able to create learning path by selecting/associating several courses.
- able to arrange the order of learning path in drag and drop fashioned from courses selected.
- system will calculate learning path duration based on course duration.
- manage assessment UI using interactive page (no refresh) when possible.


### Assessments
- able to manage Assessments using Assessment CRUD.
- able to create Multiple Choice Questions Assessment Type where learner select one or more correct answers from a list of options
- able to create True/False Questions Assessment Type.
- able to create Matching Questions Assessment Type  (Students connect items from two different lists).
- able to pre-defines the correct answers and point values. The LMS compares the student's submission to the answer key and instantly calculates a score.
- manage assessment UI using interactive page (no refresh) when possible.



=== TRAINING  ROLE ===
## user with trainer role
### Course
- able to see published courses only.
- able to invite learner role users to enroll courses/learning paths.

### Assessment
- Able to create new Assessment as Content Manager role user.


=== LMS ADMIN ROLE ===
## user with lms admin role
### Inherit
- able to do trainer role and content manager role able to do.

### Program
**manage by CRUD**
- able to manage program/curriculum using Program CRUD.
- set start date and due date on program .
- able to links together a set of required learning paths/courses for particular program.

### Course 
**manage by CRUD**
- able to set course status (Draft/Published/Archived).
- able to set course visibility (Public/Restricted/Hidden).

**see**
- able to see all courses regardless of the status and visibility.
- able to see detailed information learner user progress and activities on course/learning path when visiting learner user profile.

**publish**
- able to publish course/learning path proposed by content manager role users.
- able to grant certificate to learner role users when passing 


### Grading Scale
- able to manage grading scale for competencies and job role. Default grading scale to seed into system are : 
    - Level 0: No Experience/Knowledge - No understanding of the task.
    - Level 1: Basic/Beginner - Observes others; needs close guidance.
    - Level 2: Proficient (with oversight) - Performs with supervision.
    - Level 3: Independent - Executes tasks independently.
    - Level 4: Expert - Coaches others and sets standards.


### Competencies
- able to manage competency matrix for particular job role using Competency Matrix CRUD.
- able to create competency matrix. example : Required Level for a Sales Manager Job Role are Sales Skills with Level 4, Communication with Level 3, Leader with Level 2 along with each Behavioral Indicators (Grading Rubric) using Competency Matrix CRUD.

- able to create Competencies Category (eg : Sales Skills, Communication, Leadership) & Competencies (eg: Negotiation, Active Listening, Conflict Resolution)
- able to link grading scale to competencies.


### Certificates
- able to create prerequisites for a certificate to be granted to user with learner role. eg : based on competencies level.
- able to upload certificate and link to learner user.


### Experience Points & Badges
- able to manage Experience Points Matrix, that will define how many experience points user get when finishing course/program/learning path.
- able to manage Badges, Should learner get certain points of experience level, user get badges automatically.
- able to edit learner user experience point and badges.


## learner user role
**enrollment**
- able to see list of assigned/invited courses.
- able to see list of courses after login (default landing page) for courses with status : Published.
- able to see list of courses with visibility public by default.
- able to see list of courses with visibility restricted, only if invited by lms admin.
- able to see their enrolled courses (via My Learning Menu in Nav Top Right Bar ).

- able to enroll course independently or invited by user with roles : trainer, lms admin, super admin.
- able to see : course details, rating, number of enrolled learners, related topics courses

- not able to enrolled archieved courses.
- not able to see & enroll courses with status : draft.
- not able to see & enroll courses with visibility : restricted, unless invited by lms admin.

**progress**
- able to resume their last lesson on selected enrolled course.
- able to take assignment or quizzes that belong to course user enrolled.
- able to resume their last quiz / assessment taken.
- able to get certificate on particular course, given they pass the minimum prerequisites of the course.
- able to enrolled learning path provided.
- able to collect experience points when finishing course.
- able to get badges based on their experience points.
- able to get certificate of completion upon finishing course automatically.

**rating**
- able to rate course (1-5).

**ui/ux**
- able to scroll down : the order that he will see :
- able to see carousel (slide of 5 courses) as the landing page.
- able to see My Learning Section : list of enrolled courses and their respective progress below the carousel.
- able to see Invited To Learn Section : list of invited courses below in enrolled courses section.
- able to see Course Details: course title, decription, prerequisites, duration of course/lesson, when selecting particular course.
- able to see Collapsable/Expandable Course Content, Section header will show how many lesson and duration.
