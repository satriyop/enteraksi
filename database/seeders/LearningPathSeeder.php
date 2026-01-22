<?php

namespace Database\Seeders;

use App\Domain\LearningPath\Contracts\PathEnrollmentServiceContract;
use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Domain\LearningPath\States\CompletedCourseState;
use App\Models\Course;
use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class LearningPathSeeder extends Seeder
{
    /**
     * Course title to learning path mapping.
     * These must match titles in BankingCourseSeeder.
     */
    private array $courseMapping = [
        'compliance' => [
            'Dasar-Dasar Regulasi OJK',
            'Tata Kelola Perusahaan (GCG) Perbankan',
            'Sistem Kontrol Internal Perbankan',
        ],
        'apu-ppt' => [
            'Pengenalan Anti Pencucian Uang',
            'Customer Due Diligence (CDD)',
        ],
        'digital' => [
            'Pengantar Digital Banking',
            'Open Banking dan API Economy',
            'Keamanan Siber Perbankan',
            'Inovasi Layanan Keuangan Digital',
        ],
        'risk' => [
            'Kerangka Kerja Manajemen Risiko Basel III',
            'Manajemen Risiko Kredit',
            'Risiko Operasional dan Likuiditas',
        ],
        'onboarding' => [
            'Pengenalan Industri Perbankan Indonesia',
            'Produk dan Layanan Perbankan',
        ],
    ];

    public function run(): void
    {
        $contentManager = User::where('role', 'content_manager')->first();
        $lmsAdmin = User::where('role', 'lms_admin')->first();

        if (! $contentManager || ! $lmsAdmin) {
            $this->command->warn('Content manager or LMS admin not found. Skipping learning path seeding.');

            return;
        }

        $learningPathsData = $this->getLearningPathData();

        foreach ($learningPathsData as $pathData) {
            // Skip if learning path already exists
            if (LearningPath::where('title', $pathData['title'])->exists()) {
                $this->command->info("Skipping (exists): {$pathData['title']}");

                continue;
            }

            $this->command->info("Creating learning path: {$pathData['title']}");

            $learningPath = LearningPath::create([
                'title' => $pathData['title'],
                'slug' => Str::slug($pathData['title']).'-'.Str::random(6),
                'description' => $pathData['description'],
                'objectives' => $pathData['objectives'],
                'created_by' => $contentManager->id,
                'updated_by' => $contentManager->id,
                'is_published' => $pathData['is_published'],
                'published_at' => $pathData['is_published'] ? now() : null,
                'estimated_duration' => $pathData['estimated_duration'],
                'difficulty_level' => $pathData['difficulty_level'],
                'thumbnail_url' => $pathData['thumbnail_url'] ?? null,
                'prerequisite_mode' => $pathData['prerequisite_mode'] ?? 'sequential',
            ]);

            // Attach specific courses to the learning path
            $courseTitles = $this->courseMapping[$pathData['course_key']] ?? [];
            $courses = Course::whereIn('title', $courseTitles)
                ->where('status', 'published')
                ->get();

            if ($courses->isEmpty()) {
                $this->command->warn("  No courses found for: {$pathData['course_key']}. Run BankingCourseSeeder first.");

                continue;
            }

            // Order courses as defined in mapping
            $orderedCourses = collect($courseTitles)
                ->map(fn ($title) => $courses->firstWhere('title', $title))
                ->filter();

            foreach ($orderedCourses->values() as $position => $course) {
                $learningPath->courses()->attach($course->id, [
                    'position' => $position + 1,
                    'is_required' => $position < ($pathData['required_count'] ?? $orderedCourses->count()),
                    'min_completion_percentage' => 80,
                    'prerequisites' => $position > 0 ? [$orderedCourses[$position - 1]->id] : null,
                ]);
            }

            $this->command->info("  Attached {$orderedCourses->count()} courses");
        }

        // Create sample enrollments for test user
        $this->createSampleEnrollments();

        $this->command->info("\nLearning path seeding completed!");
    }

    private function createSampleEnrollments(): void
    {
        $testUser = User::where('email', 'test@example.com')->first();

        if (! $testUser) {
            return;
        }

        $publishedPaths = LearningPath::where('is_published', true)->get();

        if ($publishedPaths->isEmpty()) {
            return;
        }

        // Check if enrollment already exists
        $firstPath = $publishedPaths->first();
        if (LearningPathEnrollment::where('user_id', $testUser->id)
            ->where('learning_path_id', $firstPath->id)
            ->exists()) {
            return;
        }

        // Use service to properly create enrollment with course enrollments
        $enrollmentService = app(PathEnrollmentServiceContract::class);
        $progressService = app(PathProgressServiceContract::class);

        $result = $enrollmentService->enroll($testUser, $firstPath);
        $enrollment = $result->enrollment;

        // Backdate the enrollment
        $enrollment->update(['enrolled_at' => now()->subDays(7)]);

        // Simulate progress: first course in_progress (started), others as initialized
        $courseProgress = $enrollment->courseProgress()->orderBy('position')->get();

        if ($courseProgress->isNotEmpty()) {
            // Mark first course as in_progress (user has started it)
            $firstProgress = $courseProgress->first();
            $progressService->startCourse($enrollment, $firstProgress->course);

            // If path has 3+ courses, complete the first one and unlock second
            if ($courseProgress->count() >= 3) {
                // Complete first course
                $firstCourseEnrollment = $firstProgress->courseEnrollment;
                if ($firstCourseEnrollment) {
                    $firstCourseEnrollment->update([
                        'status' => 'completed',
                        'completed_at' => now()->subDays(3),
                    ]);
                }
                $firstProgress->update([
                    'state' => CompletedCourseState::$name,
                    'completed_at' => now()->subDays(3),
                ]);

                // Unlock next courses
                $progressService->unlockNextCourses($enrollment->fresh());

                // Start second course
                $secondProgress = $courseProgress->get(1);
                if ($secondProgress) {
                    $progressService->startCourse($enrollment->fresh(), $secondProgress->course);
                }

                // Recalculate progress
                $newPercentage = $progressService->calculateProgressPercentage($enrollment->fresh());
                $enrollment->update(['progress_percentage' => $newPercentage]);
            }
        }

        $this->command->info('Created sample enrollment for test user with proper course enrollments');
    }

    private function getLearningPathData(): array
    {
        return [
            [
                'title' => 'Jalur Sertifikasi Kepatuhan Perbankan',
                'course_key' => 'compliance',
                'description' => 'Program pembelajaran komprehensif untuk memahami regulasi OJK dan kepatuhan perbankan di Indonesia. Jalur ini mencakup dasar-dasar regulasi, manajemen risiko, dan praktik terbaik dalam industri perbankan.',
                'objectives' => [
                    'Memahami kerangka regulasi OJK untuk perbankan',
                    'Menguasai prinsip-prinsip tata kelola perusahaan yang baik (GCG)',
                    'Mampu mengidentifikasi dan memitigasi risiko kepatuhan',
                    'Dapat mengimplementasikan kontrol internal yang efektif',
                ],
                'is_published' => true,
                'estimated_duration' => 630, // Sum of 3 courses
                'difficulty_level' => 'intermediate',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?w=640',
                'required_count' => 3,
            ],
            [
                'title' => 'Program Anti Pencucian Uang (APU-PPT)',
                'course_key' => 'apu-ppt',
                'description' => 'Pelatihan wajib untuk memahami dan menerapkan program Anti Pencucian Uang dan Pencegahan Pendanaan Terorisme sesuai regulasi PPATK dan OJK.',
                'objectives' => [
                    'Memahami konsep dasar pencucian uang dan pendanaan terorisme',
                    'Menguasai prinsip Customer Due Diligence (CDD)',
                    'Mampu mengidentifikasi transaksi mencurigakan',
                    'Dapat melakukan pelaporan sesuai ketentuan PPATK',
                ],
                'is_published' => true,
                'estimated_duration' => 330, // Sum of 2 courses
                'difficulty_level' => 'intermediate',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1450101499163-c8848c66ca85?w=640',
                'required_count' => 2,
            ],
            [
                'title' => 'Transformasi Digital Perbankan',
                'course_key' => 'digital',
                'description' => 'Jalur pembelajaran untuk memahami transformasi digital di sektor perbankan, termasuk fintech, open banking, dan cybersecurity.',
                'objectives' => [
                    'Memahami tren transformasi digital di perbankan',
                    'Menguasai konsep open banking dan API economy',
                    'Mampu mengidentifikasi peluang inovasi digital',
                    'Memahami prinsip keamanan siber untuk perbankan digital',
                ],
                'is_published' => true,
                'estimated_duration' => 600, // Sum of 4 courses
                'difficulty_level' => 'advanced',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=640',
                'required_count' => 3,
            ],
            [
                'title' => 'Manajemen Risiko Perbankan',
                'course_key' => 'risk',
                'description' => 'Program pembelajaran menyeluruh tentang manajemen risiko di industri perbankan, mencakup risiko kredit, pasar, operasional, dan likuiditas.',
                'objectives' => [
                    'Memahami kerangka kerja manajemen risiko Basel III',
                    'Menguasai teknik pengukuran dan mitigasi risiko',
                    'Mampu menyusun profil risiko institusi',
                    'Dapat mengimplementasikan kontrol risiko yang efektif',
                ],
                'is_published' => true,
                'estimated_duration' => 660, // Sum of 3 courses
                'difficulty_level' => 'expert',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=640',
                'required_count' => 3,
            ],
            [
                'title' => 'Dasar-Dasar Perbankan untuk Pegawai Baru',
                'course_key' => 'onboarding',
                'description' => 'Jalur onboarding untuk pegawai baru di industri perbankan. Mencakup pengenalan industri, produk perbankan, dan etika profesi.',
                'objectives' => [
                    'Memahami struktur industri perbankan di Indonesia',
                    'Mengenal produk dan layanan perbankan',
                    'Memahami etika dan kode etik perbankan',
                    'Menguasai dasar-dasar pelayanan nasabah',
                ],
                'is_published' => false, // Draft for demo
                'estimated_duration' => 270, // Sum of 2 courses
                'difficulty_level' => 'beginner',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1521791136064-7986c2920216?w=640',
                'required_count' => 2,
            ],
        ];
    }
}
