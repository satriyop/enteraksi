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

            $thumbnailPath = $this->mediaHelper->downloadThumbnail(
                $courseData['thumbnail_keywords'],
                'course-'.($index + 1).'-'.Str::slug($courseData['title']).'.jpg'
            );

            if ($thumbnailPath) {
                $this->command->info("  [OK] Thumbnail downloaded");
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
                $lesson = Lesson::create([
                    'course_section_id' => $section->id,
                    'title' => $lessonData['title'],
                    'description' => $lessonData['description'] ?? null,
                    'order' => $lessonOrder + 1,
                    'content_type' => $lessonData['content_type'],
                    'rich_content' => $lessonData['rich_content'] ?? null,
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

        if ($lessonData['content_type'] === 'video' && isset($lessonData['video_url'])) {
            $mediaPath = $this->mediaHelper->downloadVideo($lessonData['video_url'], $lesson->id);
            if ($mediaPath) {
                $this->command->info("    [OK] Video downloaded for: {$lesson->title}");
            }
        }

        if ($lessonData['content_type'] === 'audio' && isset($lessonData['audio_url'])) {
            $mediaPath = $this->mediaHelper->downloadAudio($lessonData['audio_url'], $lesson->id);
            if ($mediaPath) {
                $this->command->info("    [OK] Audio downloaded for: {$lesson->title}");
            }
        }

        if ($lessonData['content_type'] === 'document' && isset($lessonData['pdf_content'])) {
            $mediaPath = $this->mediaHelper->generatePdf(
                $lessonData['pdf_title'] ?? $lesson->title,
                $lessonData['pdf_content'],
                $lesson->id
            );
            if ($mediaPath) {
                $this->command->info("    [OK] PDF generated for: {$lesson->title}");
            }
        }

        if ($mediaPath) {
            Media::create([
                'mediable_type' => Lesson::class,
                'mediable_id' => $lesson->id,
                'collection_name' => 'default',
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
                            'rich_content' => $this->getSampleRichContent('Python adalah bahasa pemrograman tingkat tinggi yang mudah dipelajari. Dibuat oleh Guido van Rossum pada tahun 1991.'),
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
                            'pdf_title' => 'Python Cheat Sheet',
                            'pdf_content' => "VARIABEL DAN TIPE DATA\n\nVariabel:\nname = \"Budi\"\nage = 25\nprice = 99.99\nis_active = True\n\nTipe Data:\n- str (string): \"Hello World\"\n- int (integer): 42\n- float (decimal): 3.14\n- bool (boolean): True/False\n- list: [1, 2, 3]\n- dict: {\"key\": \"value\"}\n\nOPERATOR\n+ Penjumlahan\n- Pengurangan\n* Perkalian\n/ Pembagian\n// Pembagian bulat\n% Modulus\n** Pangkat\n\nKONDISI\nif condition:\n    # code\nelif other_condition:\n    # code\nelse:\n    # code\n\nPERULANGAN\nfor i in range(10):\n    print(i)\n\nwhile condition:\n    # code",
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
                            'rich_content' => $this->getSampleRichContent('Python mendukung berbagai operator aritmatika: +, -, *, /, //, %, **'),
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
                            'rich_content' => $this->getSampleRichContent('Laravel adalah framework PHP yang elegan dan ekspresif. Menyediakan struktur dan tools untuk membangun aplikasi web modern.'),
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
                            'pdf_title' => 'Eloquent ORM Quick Reference',
                            'pdf_content' => "ELOQUENT ORM REFERENCE\n\nMODEL DASAR\nphp artisan make:model Post\nphp artisan make:model Post -m (dengan migration)\n\nQUERY DASAR\nPost::all()\nPost::find(1)\nPost::where('status', 'published')->get()\nPost::first()\nPost::latest()->take(10)->get()\n\nCREATE\nPost::create(['title' => 'Hello', 'body' => 'World'])\n\nUPDATE\npost->update(['title' => 'New Title'])\n\nDELETE\npost->delete()\nPost::destroy(1)\n\nRELATIONSHIPS\nhasOne, hasMany, belongsTo, belongsToMany\n\nEAGER LOADING\nPost::with('comments')->get()\nPost::with(['comments', 'author'])->get()",
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
                            'rich_content' => $this->getSampleRichContent('Manajemen proyek adalah penerapan pengetahuan, keterampilan, tools, dan teknik untuk aktivitas proyek guna memenuhi persyaratan proyek.'),
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
                            'pdf_title' => 'Project Charter Template',
                            'pdf_content' => "PROJECT CHARTER TEMPLATE\n\nPROJECT INFORMATION\nProject Name: _________________\nProject Manager: _________________\nSponsor: _________________\nStart Date: _________________\nEnd Date: _________________\n\nPROJECT OVERVIEW\nBusiness Need:\n_________________________________\n\nProject Objectives:\n1. _________________________________\n2. _________________________________\n3. _________________________________\n\nScope:\nIn Scope:\n- _________________________________\n- _________________________________\n\nOut of Scope:\n- _________________________________\n\nSTAKEHOLDERS\nName | Role | Responsibility\n_____|______|_______________\n\nMILESTONES\nMilestone | Target Date\n__________|____________\n\nRISKS\nRisk | Impact | Mitigation\n_____|________|___________\n\nBUDGET\nTotal Budget: Rp _________________\n\nAPPROVALS\nSignature: _________________\nDate: _________________",
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
                            'rich_content' => $this->getSampleRichContent('Email formal memiliki struktur: Subject Line, Greeting, Body, Closing, dan Signature.'),
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
                            'pdf_title' => 'Business English Vocabulary',
                            'pdf_content' => "BUSINESS ENGLISH VOCABULARY\n\nMEETING PHRASES\n- Let's get started\n- I'd like to begin by...\n- Moving on to the next point\n- To summarize...\n- Any questions?\n\nEMAIL PHRASES\nOpening:\n- I hope this email finds you well\n- Thank you for your email\n- Following up on our conversation\n\nClosing:\n- Please let me know if you have any questions\n- I look forward to hearing from you\n- Best regards / Kind regards\n\nCOMMON BUSINESS TERMS\n- ROI (Return on Investment)\n- KPI (Key Performance Indicator)\n- Q1, Q2, Q3, Q4 (Quarters)\n- YoY (Year over Year)\n- B2B (Business to Business)\n- B2C (Business to Consumer)\n- Stakeholder\n- Deliverable\n- Deadline\n- Milestone",
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
                            'rich_content' => $this->getSampleRichContent('UI (User Interface) adalah tampilan visual, sedangkan UX (User Experience) adalah keseluruhan pengalaman pengguna saat berinteraksi dengan produk.'),
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
                            'pdf_title' => 'UI Design Review Checklist',
                            'pdf_content' => "UI DESIGN REVIEW CHECKLIST\n\nTYPOGRAPHY\n[ ] Font hierarchy is clear (H1, H2, H3, Body)\n[ ] Line height is readable (1.4-1.6)\n[ ] Font size is accessible (min 16px body)\n[ ] Maximum 2-3 font families\n\nCOLOR\n[ ] Color palette is consistent\n[ ] Sufficient contrast (WCAG AA)\n[ ] Color is not the only indicator\n[ ] Dark mode compatibility\n\nLAYOUT\n[ ] Grid system is consistent\n[ ] White space is balanced\n[ ] Visual hierarchy is clear\n[ ] Mobile responsive\n\nCOMPONENTS\n[ ] Buttons are consistent\n[ ] Form inputs are clear\n[ ] Icons are consistent style\n[ ] Loading states defined\n\nACCESSIBILITY\n[ ] Alt text for images\n[ ] Keyboard navigation\n[ ] Focus states visible\n[ ] Touch targets min 44px\n\nINTERACTION\n[ ] Hover states defined\n[ ] Click feedback\n[ ] Error states clear\n[ ] Success states clear",
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

    private function getSampleRichContent(string $mainContent): array
    {
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 2],
                    'content' => [
                        ['type' => 'text', 'text' => 'Pendahuluan'],
                    ],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [
                        ['type' => 'text', 'text' => $mainContent],
                    ],
                ],
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 3],
                    'content' => [
                        ['type' => 'text', 'text' => 'Tujuan Pembelajaran'],
                    ],
                ],
                [
                    'type' => 'bulletList',
                    'content' => [
                        [
                            'type' => 'listItem',
                            'content' => [
                                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Memahami konsep dasar yang dibahas']]],
                            ],
                        ],
                        [
                            'type' => 'listItem',
                            'content' => [
                                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Dapat mengaplikasikan pengetahuan dalam praktik']]],
                            ],
                        ],
                        [
                            'type' => 'listItem',
                            'content' => [
                                ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Siap untuk melanjutkan ke materi berikutnya']]],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
