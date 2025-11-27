<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Media;
use App\Models\Tag;
use App\Models\User;
use App\Services\MediaSeederHelper;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CourseSeeder extends Seeder
{
    private MediaSeederHelper $mediaHelper;

    public function __construct()
    {
        $this->mediaHelper = new MediaSeederHelper;
    }

    public function run(): void
    {
        $contentManager = User::where('role', 'content_manager')->first();
        $lmsAdmin = User::where('role', 'lms_admin')->first();

        if (! $contentManager || ! $lmsAdmin) {
            $this->command->warn('Content manager or LMS admin not found. Skipping course seeding.');

            return;
        }

        $this->mediaHelper->cleanupStorage();

        $courses = $this->getCourseData();
        $categories = Category::all();
        $tags = Tag::all();

        foreach ($courses as $index => $courseData) {
            $this->command->info("Creating course: {$courseData['title']}");

            $category = $categories->where('name', $courseData['category'])->first();

            $targetFilename = 'course-'.($index + 1).'-'.Str::slug($courseData['title']).'.jpg';

            // Try fixture first, then fallback to download
            $thumbnailPath = null;
            if (isset($courseData['thumbnail_fixture'])) {
                $thumbnailPath = $this->mediaHelper->copyThumbnail($courseData['thumbnail_fixture'], $targetFilename);
                if ($thumbnailPath) {
                    $this->command->info('  [OK] Thumbnail copied from fixture');
                }
            }

            if (! $thumbnailPath) {
                $thumbnailPath = $this->mediaHelper->downloadThumbnail(
                    $courseData['thumbnail_keywords'],
                    $targetFilename
                );
                if ($thumbnailPath) {
                    $this->command->info('  [OK] Thumbnail downloaded');
                }
            }

            $course = Course::create([
                'user_id' => $contentManager->id,
                'title' => $courseData['title'],
                'slug' => Str::slug($courseData['title']).'-'.Str::random(6),
                'short_description' => $courseData['short_description'],
                'long_description' => $courseData['long_description'],
                'objectives' => $courseData['objectives'],
                'prerequisites' => $courseData['prerequisites'],
                'category_id' => $category?->id,
                'thumbnail_path' => $thumbnailPath,
                'status' => $courseData['status'],
                'visibility' => 'public',
                'difficulty_level' => $courseData['difficulty_level'],
                'estimated_duration_minutes' => $courseData['estimated_duration_minutes'],
                'published_at' => $courseData['status'] === 'published' ? now() : null,
                'published_by' => $courseData['status'] === 'published' ? $lmsAdmin->id : null,
            ]);

            $courseTags = $tags->random(min(5, $tags->count()));
            $course->tags()->attach($courseTags->pluck('id'));

            $this->createSectionsAndLessons($course, $courseData['sections']);
        }

        $this->command->info("\nCourse seeding completed!");
    }

    private function createSectionsAndLessons(Course $course, array $sectionsData): void
    {
        foreach ($sectionsData as $sectionOrder => $sectionData) {
            $section = CourseSection::create([
                'course_id' => $course->id,
                'title' => $sectionData['title'],
                'description' => $sectionData['description'],
                'order' => $sectionOrder + 1,
            ]);

            foreach ($sectionData['lessons'] as $lessonOrder => $lessonData) {
                // Load rich content from fixture if specified, otherwise use inline content
                $richContent = null;
                if (isset($lessonData['rich_content_fixture'])) {
                    $richContent = $this->mediaHelper->loadRichContent($lessonData['rich_content_fixture']);
                }
                if (! $richContent && isset($lessonData['rich_content'])) {
                    $richContent = $lessonData['rich_content'];
                }

                $lesson = Lesson::create([
                    'course_section_id' => $section->id,
                    'title' => $lessonData['title'],
                    'description' => $lessonData['description'] ?? null,
                    'order' => $lessonOrder + 1,
                    'content_type' => $lessonData['content_type'],
                    'rich_content' => $richContent,
                    'youtube_url' => $lessonData['youtube_url'] ?? null,
                    'conference_url' => $lessonData['conference_url'] ?? null,
                    'conference_type' => $lessonData['conference_type'] ?? null,
                    'estimated_duration_minutes' => $lessonData['duration'],
                    'is_free_preview' => $lessonData['is_free_preview'] ?? false,
                ]);

                $this->createLessonMedia($lesson, $lessonData);
            }
        }
    }

    private function createLessonMedia(Lesson $lesson, array $lessonData): void
    {
        $mediaPath = null;

        // Video content: try fixture first, then download
        if ($lessonData['content_type'] === 'video') {
            $mediaPath = $this->mediaHelper->copyVideo($lesson->id);
            if ($mediaPath) {
                $this->command->info("    [OK] Video copied from fixture for: {$lesson->title}");
            } elseif (isset($lessonData['video_url'])) {
                $mediaPath = $this->mediaHelper->downloadVideo($lessonData['video_url'], $lesson->id);
                if ($mediaPath) {
                    $this->command->info("    [OK] Video downloaded for: {$lesson->title}");
                }
            }
        }

        // Audio content: try fixture first, then download
        if ($lessonData['content_type'] === 'audio') {
            $mediaPath = $this->mediaHelper->copyAudio($lesson->id);
            if ($mediaPath) {
                $this->command->info("    [OK] Audio copied from fixture for: {$lesson->title}");
            } elseif (isset($lessonData['audio_url'])) {
                $mediaPath = $this->mediaHelper->downloadAudio($lessonData['audio_url'], $lesson->id);
                if ($mediaPath) {
                    $this->command->info("    [OK] Audio downloaded for: {$lesson->title}");
                }
            }
        }

        // Document content: try fixture first, then generate
        if ($lessonData['content_type'] === 'document') {
            if (isset($lessonData['pdf_fixture'])) {
                $mediaPath = $this->mediaHelper->copyPdf($lessonData['pdf_fixture'], $lesson->id);
                if ($mediaPath) {
                    $this->command->info("    [OK] PDF copied from fixture for: {$lesson->title}");
                }
            }
            if (! $mediaPath && isset($lessonData['pdf_html'])) {
                $mediaPath = $this->mediaHelper->generatePdf(
                    $lessonData['pdf_title'] ?? $lesson->title,
                    $lessonData['pdf_html'],
                    $lesson->id
                );
                if ($mediaPath) {
                    $this->command->info("    [OK] PDF generated for: {$lesson->title}");
                }
            }
        }

        if ($mediaPath) {
            // Use content type as collection name (video, audio, document)
            $collectionName = $lessonData['content_type'];

            Media::create([
                'mediable_type' => Lesson::class,
                'mediable_id' => $lesson->id,
                'collection_name' => $collectionName,
                'name' => pathinfo($mediaPath, PATHINFO_FILENAME),
                'file_name' => basename($mediaPath),
                'mime_type' => $this->mediaHelper->getMimeType($mediaPath),
                'disk' => 'public',
                'path' => $mediaPath,
                'size' => $this->mediaHelper->getFileSize($mediaPath),
                'duration_seconds' => $lessonData['duration'] * 60,
            ]);
        }
    }

    private function getCourseData(): array
    {
        return [
            $this->getPythonCourseData(),
            $this->getLaravelCourseData(),
            $this->getProjectManagementCourseData(),
            $this->getBusinessEnglishCourseData(),
            $this->getUiUxCourseData(),
        ];
    }

    private function getPythonCourseData(): array
    {
        return [
            'title' => 'Pengantar Pemrograman Python untuk Pemula',
            'category' => 'Teknologi Informasi',
            'thumbnail_fixture' => 'python',
            'thumbnail_keywords' => 'python,programming,code,laptop',
            'short_description' => 'Pelajari dasar-dasar pemrograman Python dari nol. Cocok untuk pemula yang ingin memulai karir di bidang teknologi.',
            'long_description' => 'Python adalah bahasa pemrograman yang populer dan mudah dipelajari. Dalam kursus ini, Anda akan mempelajari konsep dasar pemrograman seperti variabel, tipe data, percabangan, perulangan, fungsi, dan struktur data dasar.',
            'objectives' => [
                'Memahami konsep dasar pemrograman',
                'Mampu menulis program Python sederhana',
                'Menguasai struktur data dasar Python',
                'Dapat membuat program interaktif',
            ],
            'prerequisites' => [
                'Tidak diperlukan pengalaman pemrograman sebelumnya',
                'Komputer dengan sistem operasi Windows, Mac, atau Linux',
            ],
            'status' => 'published',
            'difficulty_level' => 'beginner',
            'estimated_duration_minutes' => 480,
            'sections' => [
                [
                    'title' => 'Pendahuluan',
                    'description' => 'Pengenalan Python dan persiapan lingkungan pengembangan',
                    'lessons' => [
                        [
                            'title' => 'Apa itu Python?',
                            'content_type' => 'text',
                            'duration' => 10,
                            'is_free_preview' => true,
                            'rich_content_fixture' => 'python',
                        ],
                        [
                            'title' => 'Instalasi Python dan VS Code',
                            'content_type' => 'youtube',
                            'duration' => 15,
                            'youtube_url' => 'https://www.youtube.com/watch?v=YYXdXT2l-Gg',
                        ],
                        [
                            'title' => 'Cheat Sheet: Sintaks Python Dasar',
                            'content_type' => 'document',
                            'duration' => 10,
                            'pdf_fixture' => 'python-cheatsheet',
                            'pdf_title' => 'Python Cheat Sheet',
                        ],
                    ],
                ],
                [
                    'title' => 'Dasar-Dasar Python',
                    'description' => 'Mempelajari sintaks dasar dan konsep fundamental Python',
                    'lessons' => [
                        [
                            'title' => 'Video Tutorial: Variabel dan Tipe Data',
                            'content_type' => 'video',
                            'duration' => 20,
                            'video_url' => 'https://sample-videos.com/video321/mp4/720/big_buck_bunny_720p_1mb.mp4',
                        ],
                        [
                            'title' => 'Operator Aritmatika',
                            'content_type' => 'text',
                            'duration' => 15,
                            'rich_content_fixture' => 'python',
                        ],
                        [
                            'title' => 'Podcast: Tips Belajar Python',
                            'content_type' => 'audio',
                            'duration' => 15,
                            'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3',
                        ],
                        [
                            'title' => 'Sesi Live: Tanya Jawab Python',
                            'content_type' => 'conference',
                            'duration' => 60,
                            'conference_url' => 'https://zoom.us/j/python-qa-session',
                            'conference_type' => 'zoom',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getLaravelCourseData(): array
    {
        return [
            'title' => 'Pengembangan Web dengan Laravel',
            'category' => 'Teknologi Informasi',
            'thumbnail_fixture' => 'laravel',
            'thumbnail_keywords' => 'web,development,coding,php',
            'short_description' => 'Kuasai framework Laravel dan bangun aplikasi web profesional dengan PHP.',
            'long_description' => 'Laravel adalah framework PHP paling populer untuk pengembangan aplikasi web modern. Kursus ini mengajarkan Anda cara membangun aplikasi web dari awal hingga deployment.',
            'objectives' => [
                'Memahami arsitektur MVC Laravel',
                'Mampu membangun REST API dengan Laravel',
                'Menguasai Eloquent ORM untuk database',
                'Dapat mengimplementasikan authentication',
            ],
            'prerequisites' => [
                'Pemahaman dasar PHP',
                'Familiar dengan HTML dan CSS',
                'Pengetahuan dasar database SQL',
            ],
            'status' => 'published',
            'difficulty_level' => 'intermediate',
            'estimated_duration_minutes' => 720,
            'sections' => [
                [
                    'title' => 'Pengenalan Laravel',
                    'description' => 'Memahami framework Laravel dan ekosistemnya',
                    'lessons' => [
                        [
                            'title' => 'Apa itu Laravel?',
                            'content_type' => 'text',
                            'duration' => 15,
                            'is_free_preview' => true,
                            'rich_content_fixture' => 'laravel',
                        ],
                        [
                            'title' => 'Instalasi Laravel dengan Composer',
                            'content_type' => 'youtube',
                            'duration' => 20,
                            'youtube_url' => 'https://www.youtube.com/watch?v=MFh0Fd7BsjE',
                        ],
                        [
                            'title' => 'Dokumentasi: Eloquent ORM Reference',
                            'content_type' => 'document',
                            'duration' => 15,
                            'pdf_fixture' => 'eloquent-reference',
                            'pdf_title' => 'Eloquent ORM Quick Reference',
                        ],
                    ],
                ],
                [
                    'title' => 'Routing dan Controller',
                    'description' => 'Memahami sistem routing dan controller di Laravel',
                    'lessons' => [
                        [
                            'title' => 'Video: Dasar Routing Laravel',
                            'content_type' => 'video',
                            'duration' => 20,
                            'video_url' => 'https://sample-videos.com/video321/mp4/720/big_buck_bunny_720p_1mb.mp4',
                        ],
                        [
                            'title' => 'Podcast: Best Practices Laravel',
                            'content_type' => 'audio',
                            'duration' => 20,
                            'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3',
                        ],
                        [
                            'title' => 'Workshop Live: Building REST API',
                            'content_type' => 'conference',
                            'duration' => 90,
                            'conference_url' => 'https://meet.google.com/laravel-api-workshop',
                            'conference_type' => 'google_meet',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getProjectManagementCourseData(): array
    {
        return [
            'title' => 'Manajemen Proyek untuk Profesional',
            'category' => 'Bisnis & Manajemen',
            'thumbnail_fixture' => 'project-management',
            'thumbnail_keywords' => 'business,meeting,team,office',
            'short_description' => 'Pelajari teknik manajemen proyek yang efektif untuk menyelesaikan proyek tepat waktu dan sesuai anggaran.',
            'long_description' => 'Kursus ini dirancang untuk para profesional yang ingin meningkatkan kemampuan manajemen proyek. Anda akan mempelajari metodologi Agile dan Scrum, teknik perencanaan, dan manajemen risiko.',
            'objectives' => [
                'Memahami metodologi Agile dan Scrum',
                'Mampu membuat project plan yang efektif',
                'Menguasai teknik manajemen risiko',
                'Dapat memimpin tim proyek dengan percaya diri',
            ],
            'prerequisites' => [
                'Pengalaman kerja minimal 1 tahun',
                'Familiar dengan lingkungan kerja tim',
            ],
            'status' => 'published',
            'difficulty_level' => 'intermediate',
            'estimated_duration_minutes' => 360,
            'sections' => [
                [
                    'title' => 'Dasar Manajemen Proyek',
                    'description' => 'Konsep fundamental dalam manajemen proyek',
                    'lessons' => [
                        [
                            'title' => 'Apa itu Manajemen Proyek?',
                            'content_type' => 'text',
                            'duration' => 15,
                            'is_free_preview' => true,
                            'rich_content_fixture' => 'project-management',
                        ],
                        [
                            'title' => 'Project Life Cycle',
                            'content_type' => 'youtube',
                            'duration' => 25,
                            'youtube_url' => 'https://www.youtube.com/watch?v=thsFsPnUHRA',
                        ],
                        [
                            'title' => 'Template: Project Charter',
                            'content_type' => 'document',
                            'duration' => 20,
                            'pdf_fixture' => 'project-charter',
                            'pdf_title' => 'Project Charter Template',
                        ],
                    ],
                ],
                [
                    'title' => 'Metodologi Agile',
                    'description' => 'Memahami dan menerapkan Agile dalam proyek',
                    'lessons' => [
                        [
                            'title' => 'Video: Framework Scrum',
                            'content_type' => 'video',
                            'duration' => 30,
                            'video_url' => 'https://sample-videos.com/video321/mp4/720/big_buck_bunny_720p_1mb.mp4',
                        ],
                        [
                            'title' => 'Podcast: Agile dalam Praktik',
                            'content_type' => 'audio',
                            'duration' => 25,
                            'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-3.mp3',
                        ],
                        [
                            'title' => 'Live Session: Sprint Planning Demo',
                            'content_type' => 'conference',
                            'duration' => 60,
                            'conference_url' => 'https://zoom.us/j/sprint-planning-demo',
                            'conference_type' => 'zoom',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getBusinessEnglishCourseData(): array
    {
        return [
            'title' => 'Bahasa Inggris Bisnis',
            'category' => 'Bahasa',
            'thumbnail_fixture' => 'business-english',
            'thumbnail_keywords' => 'business,communication,office,professional',
            'short_description' => 'Tingkatkan kemampuan bahasa Inggris Anda untuk keperluan profesional dan bisnis.',
            'long_description' => 'Kursus ini fokus pada pengembangan kemampuan bahasa Inggris dalam konteks bisnis. Anda akan belajar cara berkomunikasi dalam meeting, menulis email profesional, dan presentasi.',
            'objectives' => [
                'Mampu berkomunikasi dalam meeting berbahasa Inggris',
                'Menguasai penulisan email bisnis profesional',
                'Dapat melakukan presentasi dalam bahasa Inggris',
                'Memahami terminologi bisnis dalam bahasa Inggris',
            ],
            'prerequisites' => [
                'Pemahaman dasar bahasa Inggris (minimal level A2)',
            ],
            'status' => 'draft',
            'difficulty_level' => 'intermediate',
            'estimated_duration_minutes' => 300,
            'sections' => [
                [
                    'title' => 'Email Bisnis',
                    'description' => 'Menulis email profesional dalam bahasa Inggris',
                    'lessons' => [
                        [
                            'title' => 'Struktur Email Formal',
                            'content_type' => 'text',
                            'duration' => 20,
                            'is_free_preview' => true,
                            'rich_content_fixture' => 'business-english',
                        ],
                        [
                            'title' => 'Frasa Penting dalam Email',
                            'content_type' => 'youtube',
                            'duration' => 25,
                            'youtube_url' => 'https://www.youtube.com/watch?v=voXc5HQ_Ghs',
                        ],
                        [
                            'title' => 'Vocabulary List: Business Terms',
                            'content_type' => 'document',
                            'duration' => 15,
                            'pdf_fixture' => 'business-vocab',
                            'pdf_title' => 'Business English Vocabulary',
                        ],
                    ],
                ],
                [
                    'title' => 'Meeting Skills',
                    'description' => 'Komunikasi efektif dalam meeting',
                    'lessons' => [
                        [
                            'title' => 'Listening Practice: Business Meeting',
                            'content_type' => 'audio',
                            'duration' => 20,
                            'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-4.mp3',
                        ],
                        [
                            'title' => 'Live Practice: English Conversation',
                            'content_type' => 'conference',
                            'duration' => 45,
                            'conference_url' => 'https://meet.google.com/english-practice-session',
                            'conference_type' => 'google_meet',
                        ],
                    ],
                ],
            ],
        ];
    }

    private function getUiUxCourseData(): array
    {
        return [
            'title' => 'Desain UI/UX dengan Figma',
            'category' => 'Desain & Multimedia',
            'thumbnail_fixture' => 'ui-ux',
            'thumbnail_keywords' => 'design,ux,interface,creative',
            'short_description' => 'Pelajari prinsip desain UI/UX dan kuasai tool Figma untuk membuat desain yang menarik.',
            'long_description' => 'Kursus komprehensif tentang desain antarmuka pengguna (UI) dan pengalaman pengguna (UX). Anda akan mempelajari prinsip-prinsip desain, wireframing, dan prototyping dengan Figma.',
            'objectives' => [
                'Memahami prinsip desain UI/UX',
                'Menguasai tool Figma untuk desain',
                'Mampu membuat wireframe dan prototype',
                'Dapat melakukan user testing dasar',
            ],
            'prerequisites' => [
                'Tidak diperlukan pengalaman desain sebelumnya',
                'Akun Figma (gratis)',
            ],
            'status' => 'published',
            'difficulty_level' => 'beginner',
            'estimated_duration_minutes' => 420,
            'sections' => [
                [
                    'title' => 'Pengenalan UI/UX',
                    'description' => 'Memahami dasar-dasar UI/UX Design',
                    'lessons' => [
                        [
                            'title' => 'Apa itu UI dan UX?',
                            'content_type' => 'text',
                            'duration' => 15,
                            'is_free_preview' => true,
                            'rich_content_fixture' => 'ui-ux',
                        ],
                        [
                            'title' => 'Perbedaan UI dan UX',
                            'content_type' => 'youtube',
                            'duration' => 20,
                            'youtube_url' => 'https://www.youtube.com/watch?v=FTFaQWZBqQ8',
                        ],
                        [
                            'title' => 'Checklist: UI Design Review',
                            'content_type' => 'document',
                            'duration' => 15,
                            'pdf_fixture' => 'ui-checklist',
                            'pdf_title' => 'UI Design Review Checklist',
                        ],
                    ],
                ],
                [
                    'title' => 'Figma Basics',
                    'description' => 'Menguasai dasar-dasar Figma',
                    'lessons' => [
                        [
                            'title' => 'Video: Interface Figma',
                            'content_type' => 'video',
                            'duration' => 20,
                            'video_url' => 'https://sample-videos.com/video321/mp4/720/big_buck_bunny_720p_1mb.mp4',
                        ],
                        [
                            'title' => 'Podcast: Prinsip Desain UI/UX',
                            'content_type' => 'audio',
                            'duration' => 25,
                            'audio_url' => 'https://www.soundhelix.com/examples/mp3/SoundHelix-Song-5.mp3',
                        ],
                        [
                            'title' => 'Live Review: Portfolio Design',
                            'content_type' => 'conference',
                            'duration' => 60,
                            'conference_url' => 'https://zoom.us/j/portfolio-review-session',
                            'conference_type' => 'zoom',
                        ],
                    ],
                ],
            ],
        ];
    }
}
