<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BankingCourseSeeder extends Seeder
{
    /**
     * Banking-specific courses aligned with Learning Path themes:
     * - Kepatuhan Perbankan (Compliance)
     * - APU-PPT (Anti-Money Laundering)
     * - Transformasi Digital
     * - Manajemen Risiko
     * - Dasar Perbankan (Onboarding)
     */
    public function run(): void
    {
        $contentManager = User::where('role', 'content_manager')->first();
        $lmsAdmin = User::where('role', 'lms_admin')->first();

        if (! $contentManager || ! $lmsAdmin) {
            $this->command->warn('Content manager or LMS admin not found. Skipping banking course seeding.');

            return;
        }

        // Ensure we have a banking category
        $bankingCategory = Category::firstOrCreate(
            ['slug' => 'perbankan-keuangan'],
            [
                'name' => 'Perbankan & Keuangan',
                'description' => 'Kursus terkait industri perbankan, regulasi keuangan, dan manajemen risiko.',
            ]
        );

        $courses = $this->getCourseData();
        $tags = Tag::all();

        foreach ($courses as $index => $courseData) {
            // Skip if course already exists
            if (Course::where('title', $courseData['title'])->exists()) {
                $this->command->info("Skipping (exists): {$courseData['title']}");

                continue;
            }

            $this->command->info("Creating course: {$courseData['title']}");

            $course = Course::create([
                'user_id' => $contentManager->id,
                'title' => $courseData['title'],
                'slug' => Str::slug($courseData['title']).'-'.Str::random(6),
                'short_description' => $courseData['short_description'],
                'long_description' => $courseData['long_description'],
                'objectives' => $courseData['objectives'],
                'prerequisites' => $courseData['prerequisites'],
                'category_id' => $bankingCategory->id,
                'thumbnail_path' => null,
                'status' => $courseData['status'],
                'visibility' => 'public',
                'difficulty_level' => $courseData['difficulty_level'],
                'estimated_duration_minutes' => $courseData['estimated_duration_minutes'],
                'published_at' => $courseData['status'] === 'published' ? now() : null,
                'published_by' => $courseData['status'] === 'published' ? $lmsAdmin->id : null,
            ]);

            // Attach random tags
            if ($tags->isNotEmpty()) {
                $courseTags = $tags->random(min(3, $tags->count()));
                $course->tags()->attach($courseTags->pluck('id'));
            }

            // Create sections and lessons
            $this->createSectionsAndLessons($course, $courseData['sections']);
        }

        $this->command->info("\nBanking course seeding completed!");
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
                Lesson::create([
                    'course_section_id' => $section->id,
                    'title' => $lessonData['title'],
                    'description' => $lessonData['description'] ?? null,
                    'order' => $lessonOrder + 1,
                    'content_type' => $lessonData['content_type'],
                    'rich_content' => $lessonData['rich_content'] ?? $this->generateRichContent($lessonData['title']),
                    'youtube_url' => $lessonData['youtube_url'] ?? null,
                    'estimated_duration_minutes' => $lessonData['duration'],
                    'is_free_preview' => $lessonData['is_free_preview'] ?? false,
                ]);
            }
        }
    }

    private function generateRichContent(string $title): array
    {
        return [
            'type' => 'doc',
            'content' => [
                [
                    'type' => 'heading',
                    'attrs' => ['level' => 1],
                    'content' => [['type' => 'text', 'text' => $title]],
                ],
                [
                    'type' => 'paragraph',
                    'content' => [['type' => 'text', 'text' => 'Konten pembelajaran akan segera tersedia.']],
                ],
            ],
        ];
    }

    private function getCourseData(): array
    {
        return [
            // =====================================================
            // KEPATUHAN PERBANKAN (Compliance) - 3 courses
            // =====================================================
            [
                'title' => 'Dasar-Dasar Regulasi OJK',
                'learning_path' => 'compliance',
                'short_description' => 'Memahami kerangka regulasi Otoritas Jasa Keuangan (OJK) untuk industri perbankan Indonesia.',
                'long_description' => 'Kursus ini memberikan pemahaman komprehensif tentang regulasi OJK yang mengatur industri perbankan di Indonesia. Peserta akan mempelajari struktur regulasi, kewenangan OJK, dan kewajiban kepatuhan bagi lembaga perbankan.',
                'objectives' => [
                    'Memahami struktur dan kewenangan OJK',
                    'Mengenal regulasi utama perbankan',
                    'Memahami kewajiban pelaporan ke OJK',
                    'Mengetahui sanksi pelanggaran regulasi',
                ],
                'prerequisites' => [
                    'Pemahaman dasar tentang industri perbankan',
                ],
                'status' => 'published',
                'difficulty_level' => 'beginner',
                'estimated_duration_minutes' => 180,
                'sections' => [
                    [
                        'title' => 'Pengenalan OJK',
                        'description' => 'Sejarah, struktur, dan kewenangan OJK',
                        'lessons' => [
                            ['title' => 'Sejarah Pembentukan OJK', 'content_type' => 'text', 'duration' => 15, 'is_free_preview' => true],
                            ['title' => 'Struktur Organisasi OJK', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Kewenangan dan Tugas OJK', 'content_type' => 'text', 'duration' => 25],
                        ],
                    ],
                    [
                        'title' => 'Regulasi Perbankan',
                        'description' => 'Regulasi utama yang mengatur perbankan',
                        'lessons' => [
                            ['title' => 'POJK tentang Tata Kelola Bank', 'content_type' => 'text', 'duration' => 30],
                            ['title' => 'POJK tentang Manajemen Risiko', 'content_type' => 'text', 'duration' => 30],
                            ['title' => 'Kewajiban Pelaporan Bank', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Sanksi dan Penegakan Hukum', 'content_type' => 'text', 'duration' => 20],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Tata Kelola Perusahaan (GCG) Perbankan',
                'learning_path' => 'compliance',
                'short_description' => 'Menguasai prinsip dan implementasi Good Corporate Governance di industri perbankan.',
                'long_description' => 'Kursus ini membahas secara mendalam prinsip-prinsip Tata Kelola Perusahaan yang Baik (GCG) khusus untuk industri perbankan sesuai regulasi OJK. Peserta akan mempelajari peran Dewan Komisaris, Direksi, dan fungsi kontrol.',
                'objectives' => [
                    'Memahami 5 prinsip dasar GCG',
                    'Mengenal peran organ perusahaan dalam GCG',
                    'Mampu mengidentifikasi conflict of interest',
                    'Dapat menerapkan prinsip GCG dalam operasional',
                ],
                'prerequisites' => [
                    'Telah mengikuti kursus Dasar-Dasar Regulasi OJK',
                ],
                'status' => 'published',
                'difficulty_level' => 'intermediate',
                'estimated_duration_minutes' => 240,
                'sections' => [
                    [
                        'title' => 'Prinsip Dasar GCG',
                        'description' => 'Transparansi, Akuntabilitas, Responsibilitas, Independensi, Kewajaran',
                        'lessons' => [
                            ['title' => 'Lima Prinsip GCG (TARIF)', 'content_type' => 'text', 'duration' => 25, 'is_free_preview' => true],
                            ['title' => 'Transparansi dalam Perbankan', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Akuntabilitas dan Responsibilitas', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Independensi dan Kewajaran', 'content_type' => 'text', 'duration' => 20],
                        ],
                    ],
                    [
                        'title' => 'Organ Perusahaan',
                        'description' => 'Peran Komisaris, Direksi, dan Komite',
                        'lessons' => [
                            ['title' => 'Tugas dan Tanggung Jawab Dewan Komisaris', 'content_type' => 'text', 'duration' => 30],
                            ['title' => 'Tugas dan Tanggung Jawab Direksi', 'content_type' => 'text', 'duration' => 30],
                            ['title' => 'Komite-Komite di Bawah Dewan Komisaris', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Self Assessment GCG', 'content_type' => 'text', 'duration' => 30],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Sistem Kontrol Internal Perbankan',
                'learning_path' => 'compliance',
                'short_description' => 'Membangun dan mengevaluasi sistem kontrol internal yang efektif di lembaga perbankan.',
                'long_description' => 'Kursus ini membahas framework kontrol internal perbankan berdasarkan COSO dan regulasi OJK. Peserta akan mempelajari cara merancang, mengimplementasikan, dan mengevaluasi sistem kontrol internal.',
                'objectives' => [
                    'Memahami framework COSO untuk kontrol internal',
                    'Mampu mengidentifikasi risiko dan kontrol',
                    'Dapat merancang prosedur kontrol yang efektif',
                    'Menguasai teknik evaluasi efektivitas kontrol',
                ],
                'prerequisites' => [
                    'Telah mengikuti kursus GCG Perbankan',
                ],
                'status' => 'published',
                'difficulty_level' => 'intermediate',
                'estimated_duration_minutes' => 210,
                'sections' => [
                    [
                        'title' => 'Framework Kontrol Internal',
                        'description' => 'Pengenalan COSO dan komponen kontrol internal',
                        'lessons' => [
                            ['title' => 'Pengenalan Framework COSO', 'content_type' => 'text', 'duration' => 25, 'is_free_preview' => true],
                            ['title' => 'Lima Komponen Kontrol Internal', 'content_type' => 'text', 'duration' => 30],
                            ['title' => 'Lingkungan Pengendalian', 'content_type' => 'text', 'duration' => 25],
                        ],
                    ],
                    [
                        'title' => 'Implementasi dan Evaluasi',
                        'description' => 'Penerapan dan pengujian kontrol internal',
                        'lessons' => [
                            ['title' => 'Identifikasi Risiko dan Aktivitas Kontrol', 'content_type' => 'text', 'duration' => 30],
                            ['title' => 'Informasi dan Komunikasi', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Monitoring dan Evaluasi Kontrol', 'content_type' => 'text', 'duration' => 30],
                            ['title' => 'Pelaporan Kelemahan Kontrol', 'content_type' => 'text', 'duration' => 25],
                        ],
                    ],
                ],
            ],

            // =====================================================
            // APU-PPT (Anti-Money Laundering) - 2 courses
            // =====================================================
            [
                'title' => 'Pengenalan Anti Pencucian Uang',
                'learning_path' => 'apu-ppt',
                'short_description' => 'Memahami konsep dasar pencucian uang dan kerangka regulasi APU-PPT di Indonesia.',
                'long_description' => 'Kursus ini memberikan pemahaman dasar tentang pencucian uang, pendanaan terorisme, dan kerangka regulasi APU-PPT di Indonesia. Peserta akan mempelajari metode pencucian uang dan peran lembaga pelapor.',
                'objectives' => [
                    'Memahami definisi dan tahapan pencucian uang',
                    'Mengenal modus operandi pencucian uang',
                    'Memahami kerangka regulasi APU-PPT Indonesia',
                    'Mengetahui peran PPATK dan lembaga pelapor',
                ],
                'prerequisites' => [],
                'status' => 'published',
                'difficulty_level' => 'beginner',
                'estimated_duration_minutes' => 150,
                'sections' => [
                    [
                        'title' => 'Konsep Dasar Pencucian Uang',
                        'description' => 'Definisi, tahapan, dan modus operandi',
                        'lessons' => [
                            ['title' => 'Apa itu Pencucian Uang?', 'content_type' => 'text', 'duration' => 20, 'is_free_preview' => true],
                            ['title' => 'Tiga Tahapan Pencucian Uang', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Modus Operandi Umum', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Pendanaan Terorisme', 'content_type' => 'text', 'duration' => 20],
                        ],
                    ],
                    [
                        'title' => 'Kerangka Regulasi',
                        'description' => 'Regulasi APU-PPT di Indonesia',
                        'lessons' => [
                            ['title' => 'UU TPPU dan PP APU-PPT', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Peran dan Fungsi PPATK', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Kewajiban Pihak Pelapor', 'content_type' => 'text', 'duration' => 20],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Customer Due Diligence (CDD)',
                'learning_path' => 'apu-ppt',
                'short_description' => 'Menguasai prinsip dan prosedur Customer Due Diligence sesuai regulasi APU-PPT.',
                'long_description' => 'Kursus lanjutan yang membahas secara detail prosedur Customer Due Diligence (CDD) dan Enhanced Due Diligence (EDD). Peserta akan mempelajari identifikasi nasabah, verifikasi, dan monitoring transaksi.',
                'objectives' => [
                    'Memahami prinsip Know Your Customer (KYC)',
                    'Mampu melakukan identifikasi dan verifikasi nasabah',
                    'Menguasai prosedur Enhanced Due Diligence',
                    'Dapat mengidentifikasi transaksi mencurigakan',
                ],
                'prerequisites' => [
                    'Telah mengikuti kursus Pengenalan Anti Pencucian Uang',
                ],
                'status' => 'published',
                'difficulty_level' => 'intermediate',
                'estimated_duration_minutes' => 180,
                'sections' => [
                    [
                        'title' => 'Prinsip KYC dan CDD',
                        'description' => 'Dasar-dasar identifikasi nasabah',
                        'lessons' => [
                            ['title' => 'Prinsip Know Your Customer', 'content_type' => 'text', 'duration' => 20, 'is_free_preview' => true],
                            ['title' => 'Identifikasi Nasabah Perorangan', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Identifikasi Nasabah Korporasi', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Beneficial Owner', 'content_type' => 'text', 'duration' => 20],
                        ],
                    ],
                    [
                        'title' => 'Enhanced Due Diligence',
                        'description' => 'CDD diperluas untuk nasabah berisiko tinggi',
                        'lessons' => [
                            ['title' => 'Kategori Nasabah Berisiko Tinggi', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Prosedur EDD', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Politically Exposed Person (PEP)', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Monitoring Transaksi Berkelanjutan', 'content_type' => 'text', 'duration' => 25],
                        ],
                    ],
                ],
            ],

            // =====================================================
            // TRANSFORMASI DIGITAL - 4 courses
            // =====================================================
            [
                'title' => 'Pengantar Digital Banking',
                'learning_path' => 'digital',
                'short_description' => 'Memahami tren dan teknologi transformasi digital di industri perbankan.',
                'long_description' => 'Kursus ini memberikan gambaran komprehensif tentang transformasi digital perbankan, termasuk mobile banking, internet banking, dan layanan keuangan digital lainnya.',
                'objectives' => [
                    'Memahami evolusi digital banking',
                    'Mengenal teknologi dasar digital banking',
                    'Memahami regulasi digital banking OJK',
                    'Mengetahui tren dan inovasi terkini',
                ],
                'prerequisites' => [],
                'status' => 'published',
                'difficulty_level' => 'beginner',
                'estimated_duration_minutes' => 120,
                'sections' => [
                    [
                        'title' => 'Evolusi Perbankan Digital',
                        'description' => 'Sejarah dan perkembangan digital banking',
                        'lessons' => [
                            ['title' => 'Dari ATM ke Mobile Banking', 'content_type' => 'text', 'duration' => 20, 'is_free_preview' => true],
                            ['title' => 'Generasi Layanan Digital Banking', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Digital-Only Bank di Indonesia', 'content_type' => 'text', 'duration' => 20],
                        ],
                    ],
                    [
                        'title' => 'Teknologi dan Regulasi',
                        'description' => 'Infrastruktur dan kerangka regulasi',
                        'lessons' => [
                            ['title' => 'Infrastruktur Teknologi Digital Banking', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'POJK tentang Bank Digital', 'content_type' => 'text', 'duration' => 20],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Open Banking dan API Economy',
                'learning_path' => 'digital',
                'short_description' => 'Menguasai konsep open banking dan implementasi API dalam ekosistem perbankan.',
                'long_description' => 'Kursus ini membahas konsep open banking, standar API perbankan, dan peluang kolaborasi dengan fintech melalui API economy.',
                'objectives' => [
                    'Memahami konsep dan manfaat open banking',
                    'Mengenal standar API perbankan (SNAP)',
                    'Memahami integrasi dengan fintech',
                    'Mengetahui aspek keamanan API',
                ],
                'prerequisites' => [
                    'Telah mengikuti kursus Pengantar Digital Banking',
                ],
                'status' => 'published',
                'difficulty_level' => 'intermediate',
                'estimated_duration_minutes' => 150,
                'sections' => [
                    [
                        'title' => 'Konsep Open Banking',
                        'description' => 'Prinsip dan implementasi open banking',
                        'lessons' => [
                            ['title' => 'Apa itu Open Banking?', 'content_type' => 'text', 'duration' => 20, 'is_free_preview' => true],
                            ['title' => 'Manfaat Open Banking', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Open Banking di Berbagai Negara', 'content_type' => 'text', 'duration' => 25],
                        ],
                    ],
                    [
                        'title' => 'Standar API Perbankan',
                        'description' => 'Standar dan keamanan API',
                        'lessons' => [
                            ['title' => 'Standar Nasional API Perbankan (SNAP)', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Jenis-Jenis API Perbankan', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Keamanan dan Autentikasi API', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Kolaborasi Bank-Fintech', 'content_type' => 'text', 'duration' => 20],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Keamanan Siber Perbankan',
                'learning_path' => 'digital',
                'short_description' => 'Memahami ancaman siber dan implementasi keamanan di layanan digital banking.',
                'long_description' => 'Kursus ini membahas berbagai ancaman keamanan siber di perbankan digital dan strategi mitigasinya sesuai regulasi OJK tentang keamanan informasi.',
                'objectives' => [
                    'Mengidentifikasi ancaman siber di perbankan',
                    'Memahami framework keamanan informasi',
                    'Mengetahui regulasi keamanan siber OJK',
                    'Dapat menerapkan prinsip keamanan dasar',
                ],
                'prerequisites' => [
                    'Telah mengikuti kursus Pengantar Digital Banking',
                ],
                'status' => 'published',
                'difficulty_level' => 'intermediate',
                'estimated_duration_minutes' => 180,
                'sections' => [
                    [
                        'title' => 'Ancaman Keamanan Siber',
                        'description' => 'Jenis-jenis ancaman di perbankan digital',
                        'lessons' => [
                            ['title' => 'Landscape Ancaman Siber Perbankan', 'content_type' => 'text', 'duration' => 25, 'is_free_preview' => true],
                            ['title' => 'Phishing dan Social Engineering', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Malware dan Ransomware', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Fraud Digital Banking', 'content_type' => 'text', 'duration' => 25],
                        ],
                    ],
                    [
                        'title' => 'Keamanan dan Regulasi',
                        'description' => 'Framework dan regulasi keamanan',
                        'lessons' => [
                            ['title' => 'Framework Keamanan Informasi', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'POJK Keamanan Siber', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Incident Response dan Recovery', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Security Awareness untuk Pegawai', 'content_type' => 'text', 'duration' => 20],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Inovasi Layanan Keuangan Digital',
                'learning_path' => 'digital',
                'short_description' => 'Eksplorasi inovasi fintech dan peluang bisnis di era digital banking.',
                'long_description' => 'Kursus ini mengeksplorasi berbagai inovasi di industri keuangan digital termasuk payment gateway, lending platform, dan wealth management digital.',
                'objectives' => [
                    'Mengenal berbagai inovasi fintech',
                    'Memahami model bisnis digital banking',
                    'Mengidentifikasi peluang inovasi',
                    'Memahami customer experience digital',
                ],
                'prerequisites' => [
                    'Telah mengikuti kursus Open Banking dan API',
                ],
                'status' => 'published',
                'difficulty_level' => 'advanced',
                'estimated_duration_minutes' => 150,
                'sections' => [
                    [
                        'title' => 'Inovasi Fintech',
                        'description' => 'Berbagai inovasi di industri keuangan',
                        'lessons' => [
                            ['title' => 'Ekosistem Fintech Indonesia', 'content_type' => 'text', 'duration' => 25, 'is_free_preview' => true],
                            ['title' => 'Payment dan E-Wallet', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Peer-to-Peer Lending', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Wealthtech dan Robo-Advisor', 'content_type' => 'text', 'duration' => 20],
                        ],
                    ],
                    [
                        'title' => 'Customer Experience',
                        'description' => 'Pengalaman nasabah di era digital',
                        'lessons' => [
                            ['title' => 'Personalisasi Layanan Digital', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Omnichannel Banking', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Data Analytics untuk Customer Insight', 'content_type' => 'text', 'duration' => 25],
                        ],
                    ],
                ],
            ],

            // =====================================================
            // MANAJEMEN RISIKO - 3 courses
            // =====================================================
            [
                'title' => 'Kerangka Kerja Manajemen Risiko Basel III',
                'learning_path' => 'risk',
                'short_description' => 'Memahami framework Basel III dan implementasinya di perbankan Indonesia.',
                'long_description' => 'Kursus ini membahas kerangka kerja Basel III secara komprehensif, termasuk capital adequacy, liquidity coverage, dan leverage ratio sesuai adopsi OJK.',
                'objectives' => [
                    'Memahami evolusi Basel I, II, dan III',
                    'Menguasai perhitungan CAR dan modal minimum',
                    'Memahami liquidity coverage ratio (LCR)',
                    'Mengenal net stable funding ratio (NSFR)',
                ],
                'prerequisites' => [
                    'Pemahaman dasar akuntansi perbankan',
                ],
                'status' => 'published',
                'difficulty_level' => 'advanced',
                'estimated_duration_minutes' => 240,
                'sections' => [
                    [
                        'title' => 'Evolusi Kerangka Basel',
                        'description' => 'Sejarah dan perkembangan Basel Accord',
                        'lessons' => [
                            ['title' => 'Dari Basel I ke Basel III', 'content_type' => 'text', 'duration' => 30, 'is_free_preview' => true],
                            ['title' => 'Tiga Pilar Basel III', 'content_type' => 'text', 'duration' => 30],
                            ['title' => 'Adopsi Basel III di Indonesia', 'content_type' => 'text', 'duration' => 25],
                        ],
                    ],
                    [
                        'title' => 'Perhitungan Rasio Basel',
                        'description' => 'CAR, LCR, dan NSFR',
                        'lessons' => [
                            ['title' => 'Capital Adequacy Ratio (CAR)', 'content_type' => 'text', 'duration' => 35],
                            ['title' => 'Liquidity Coverage Ratio (LCR)', 'content_type' => 'text', 'duration' => 30],
                            ['title' => 'Net Stable Funding Ratio (NSFR)', 'content_type' => 'text', 'duration' => 30],
                            ['title' => 'Leverage Ratio', 'content_type' => 'text', 'duration' => 25],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Manajemen Risiko Kredit',
                'learning_path' => 'risk',
                'short_description' => 'Menguasai teknik identifikasi, pengukuran, dan mitigasi risiko kredit.',
                'long_description' => 'Kursus ini membahas siklus manajemen risiko kredit dari origination hingga collection, termasuk credit scoring, provisioning, dan recovery.',
                'objectives' => [
                    'Memahami siklus risiko kredit',
                    'Menguasai teknik credit scoring',
                    'Mampu menghitung expected loss',
                    'Mengetahui strategi mitigasi risiko kredit',
                ],
                'prerequisites' => [
                    'Telah mengikuti kursus Basel III',
                ],
                'status' => 'published',
                'difficulty_level' => 'advanced',
                'estimated_duration_minutes' => 210,
                'sections' => [
                    [
                        'title' => 'Dasar Risiko Kredit',
                        'description' => 'Konsep dan siklus risiko kredit',
                        'lessons' => [
                            ['title' => 'Definisi dan Jenis Risiko Kredit', 'content_type' => 'text', 'duration' => 25, 'is_free_preview' => true],
                            ['title' => 'Siklus Kredit dan Risiko', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Credit Scoring Model', 'content_type' => 'text', 'duration' => 30],
                        ],
                    ],
                    [
                        'title' => 'Pengukuran dan Mitigasi',
                        'description' => 'Kuantifikasi dan strategi mitigasi',
                        'lessons' => [
                            ['title' => 'Probability of Default (PD)', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Loss Given Default (LGD)', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Expected Loss dan Provisioning', 'content_type' => 'text', 'duration' => 30],
                            ['title' => 'Strategi Mitigasi Risiko Kredit', 'content_type' => 'text', 'duration' => 25],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Risiko Operasional dan Likuiditas',
                'learning_path' => 'risk',
                'short_description' => 'Memahami dan mengelola risiko operasional dan likuiditas di perbankan.',
                'long_description' => 'Kursus ini membahas manajemen risiko operasional (termasuk IT risk dan fraud) dan risiko likuiditas sesuai regulasi OJK.',
                'objectives' => [
                    'Mengidentifikasi sumber risiko operasional',
                    'Memahami pengelolaan risiko IT dan fraud',
                    'Menguasai manajemen risiko likuiditas',
                    'Dapat menyusun contingency plan',
                ],
                'prerequisites' => [
                    'Telah mengikuti kursus Basel III',
                ],
                'status' => 'published',
                'difficulty_level' => 'advanced',
                'estimated_duration_minutes' => 210,
                'sections' => [
                    [
                        'title' => 'Risiko Operasional',
                        'description' => 'Identifikasi dan pengelolaan risiko operasional',
                        'lessons' => [
                            ['title' => 'Jenis-Jenis Risiko Operasional', 'content_type' => 'text', 'duration' => 25, 'is_free_preview' => true],
                            ['title' => 'IT Risk dan Cybersecurity Risk', 'content_type' => 'text', 'duration' => 30],
                            ['title' => 'Fraud Risk Management', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Business Continuity Management', 'content_type' => 'text', 'duration' => 25],
                        ],
                    ],
                    [
                        'title' => 'Risiko Likuiditas',
                        'description' => 'Pengelolaan likuiditas bank',
                        'lessons' => [
                            ['title' => 'Sumber Risiko Likuiditas', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Asset-Liability Management', 'content_type' => 'text', 'duration' => 30],
                            ['title' => 'Stress Testing Likuiditas', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Liquidity Contingency Plan', 'content_type' => 'text', 'duration' => 25],
                        ],
                    ],
                ],
            ],

            // =====================================================
            // DASAR PERBANKAN (Onboarding) - 2 courses
            // =====================================================
            [
                'title' => 'Pengenalan Industri Perbankan Indonesia',
                'learning_path' => 'onboarding',
                'short_description' => 'Memahami struktur, sejarah, dan peran industri perbankan di Indonesia.',
                'long_description' => 'Kursus onboarding untuk pegawai baru yang membahas sejarah perbankan Indonesia, struktur industri, dan peran bank dalam perekonomian.',
                'objectives' => [
                    'Memahami sejarah perbankan Indonesia',
                    'Mengenal struktur industri perbankan',
                    'Memahami peran Bank Indonesia dan OJK',
                    'Mengetahui jenis-jenis bank di Indonesia',
                ],
                'prerequisites' => [],
                'status' => 'published',
                'difficulty_level' => 'beginner',
                'estimated_duration_minutes' => 120,
                'sections' => [
                    [
                        'title' => 'Sejarah Perbankan Indonesia',
                        'description' => 'Perkembangan industri perbankan nasional',
                        'lessons' => [
                            ['title' => 'Sejarah Singkat Perbankan Indonesia', 'content_type' => 'text', 'duration' => 20, 'is_free_preview' => true],
                            ['title' => 'Krisis Perbankan 1998', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Reformasi Perbankan Pasca Krisis', 'content_type' => 'text', 'duration' => 20],
                        ],
                    ],
                    [
                        'title' => 'Struktur Industri',
                        'description' => 'Pelaku dan regulator perbankan',
                        'lessons' => [
                            ['title' => 'Jenis-Jenis Bank di Indonesia', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Peran Bank Indonesia', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Peran OJK dalam Pengawasan', 'content_type' => 'text', 'duration' => 20],
                        ],
                    ],
                ],
            ],
            [
                'title' => 'Produk dan Layanan Perbankan',
                'learning_path' => 'onboarding',
                'short_description' => 'Mengenal berbagai produk dan layanan yang ditawarkan bank di Indonesia.',
                'long_description' => 'Kursus ini membahas berbagai produk perbankan mulai dari simpanan, kredit, hingga layanan treasury dan wealth management.',
                'objectives' => [
                    'Mengenal produk dana (funding)',
                    'Memahami produk kredit (lending)',
                    'Mengetahui layanan jasa perbankan',
                    'Memahami produk treasury dan investasi',
                ],
                'prerequisites' => [
                    'Telah mengikuti kursus Pengenalan Industri Perbankan',
                ],
                'status' => 'published',
                'difficulty_level' => 'beginner',
                'estimated_duration_minutes' => 150,
                'sections' => [
                    [
                        'title' => 'Produk Dana dan Kredit',
                        'description' => 'Produk simpanan dan pinjaman',
                        'lessons' => [
                            ['title' => 'Produk Simpanan (Giro, Tabungan, Deposito)', 'content_type' => 'text', 'duration' => 25, 'is_free_preview' => true],
                            ['title' => 'Produk Kredit Konsumer', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Produk Kredit Komersial', 'content_type' => 'text', 'duration' => 25],
                        ],
                    ],
                    [
                        'title' => 'Layanan Perbankan',
                        'description' => 'Jasa dan layanan bank',
                        'lessons' => [
                            ['title' => 'Layanan Transfer dan Remittance', 'content_type' => 'text', 'duration' => 20],
                            ['title' => 'Trade Finance dan LC', 'content_type' => 'text', 'duration' => 25],
                            ['title' => 'Produk Treasury dan Investasi', 'content_type' => 'text', 'duration' => 25],
                        ],
                    ],
                ],
            ],
        ];
    }
}
