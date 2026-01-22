declare namespace App.Data.Course {
export type CourseData = {
id: number;
title: string;
slug: string | null;
description: string | null;
status: string;
visibility: string;
difficulty_level: string | null;
estimated_duration_minutes: number | null;
thumbnail: string | null;
user_id: number;
category_id: number | null;
created_at: string | null;
updated_at: string | null;
published_at: string | null;
lessons_count: any | number | null;
sections_count: any | number | null;
enrollments_count: any | number | null;
average_rating: any | number | null;
ratings_count: any | number | null;
};
}
declare namespace App.Data.Enrollment {
export type EnrollmentData = {
id: number;
user_id: number;
course_id: number;
status: string;
progress_percentage: number;
enrolled_at: string | null;
started_at: string | null;
completed_at: string | null;
invited_by: number | null;
last_lesson_id: number | null;
};
}
declare namespace App.Data.LearningPath {
export type CourseProgressData = {
course_id: number;
course_title: string;
status: string;
position: number;
is_required: boolean;
completion_percentage: number;
min_required_percentage: number | null;
prerequisites: Array<number> | null;
lock_reason: string | null;
unlocked_at: string | null;
started_at: string | null;
completed_at: string | null;
enrollment_id: number | null;
};
export type PathEnrollmentData = {
id: number;
user_id: number;
learning_path_id: number;
state: string;
progress_percentage: number;
enrolled_at: string | null;
completed_at: string | null;
dropped_at: string | null;
drop_reason: string | null;
};
}
declare namespace App.Data.Progress {
export type LessonProgressData = {
id: number;
enrollment_id: number;
lesson_id: number;
is_completed: boolean;
progress_percentage: number;
time_spent_seconds: number;
current_page: number | null;
total_pages: number | null;
highest_page_reached: number | null;
media_position_seconds: number | null;
media_duration_seconds: number | null;
media_progress_percentage: number | null;
completed_at: string | null;
pagination_metadata: { [key: string]: any } | null;
};
}
declare namespace App.Data.User {
export type UserData = {
id: number;
name: string;
email: string;
role: string;
avatar: string | null;
bio: string | null;
created_at: string | null;
};
}
