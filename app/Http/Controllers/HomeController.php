<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class HomeController extends Controller
{
    public function index(): Response
    {
        $featuredCourses = Course::query()
            ->published()
            ->visible()
            ->with(['user:id,name', 'category:id,name,slug'])
            ->withCount(['sections', 'lessons', 'enrollments'])
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get()
            ->map(fn (Course $course) => $this->formatCourseForFrontend($course));

        $popularCourses = Course::query()
            ->published()
            ->visible()
            ->with(['user:id,name', 'category:id,name,slug'])
            ->withCount(['sections', 'lessons', 'enrollments'])
            ->orderByDesc('enrollments_count')
            ->limit(8)
            ->get()
            ->map(fn (Course $course) => $this->formatCourseForFrontend($course));

        $categories = Category::query()
            ->whereNull('parent_id')
            ->withCount(['courses' => fn ($query) => $query->published()->visible()])
            ->orderBy('order')
            ->limit(12)
            ->get()
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'courses_count' => $category->courses_count,
                'icon' => $this->getCategoryIcon($category->slug),
            ]);

        $stats = [
            [
                'label' => 'Kursus Tersedia',
                'value' => Course::published()->visible()->count() ?: '50+',
                'icon' => 'courses',
            ],
            [
                'label' => 'Siswa Terdaftar',
                'value' => '1,000+',
                'icon' => 'students',
            ],
            [
                'label' => 'Instruktur Ahli',
                'value' => '25+',
                'icon' => 'instructors',
            ],
            [
                'label' => 'Jam Konten',
                'value' => '500+',
                'icon' => 'hours',
            ],
        ];

        return Inertia::render('Welcome', [
            'canRegister' => Features::enabled(Features::registration()),
            'featuredCourses' => $featuredCourses,
            'popularCourses' => $popularCourses,
            'categories' => $categories,
            'stats' => $stats,
        ]);
    }

    private function formatCourseForFrontend(Course $course): array
    {
        return [
            'id' => $course->id,
            'title' => $course->title,
            'slug' => $course->slug,
            'short_description' => $course->short_description,
            'thumbnail_url' => $course->thumbnail_path
                ? Storage::url($course->thumbnail_path)
                : null,
            'instructor' => $course->user ? [
                'id' => $course->user->id,
                'name' => $course->user->name,
            ] : null,
            'category' => $course->category ? [
                'id' => $course->category->id,
                'name' => $course->category->name,
                'slug' => $course->category->slug,
            ] : null,
            'rating' => 4.5,
            'reviews_count' => rand(10, 500),
            'students_count' => $course->enrollments_count ?? 0,
            'estimated_duration_minutes' => $course->duration,
            'lessons_count' => $course->lessons_count ?? 0,
            'difficulty_level' => $course->difficulty_level,
            'is_bestseller' => ($course->enrollments_count ?? 0) > 100,
            'is_new' => $course->created_at?->isAfter(now()->subDays(30)),
        ];
    }

    private function getCategoryIcon(string $slug): string
    {
        $iconMap = [
            'programming' => 'code',
            'web-development' => 'code',
            'mobile-development' => 'code',
            'design' => 'palette',
            'ui-ux' => 'palette',
            'graphic-design' => 'palette',
            'business' => 'briefcase',
            'marketing' => 'trending',
            'photography' => 'camera',
            'video' => 'camera',
            'music' => 'music',
            'health' => 'heart',
            'fitness' => 'heart',
            'language' => 'globe',
        ];

        foreach ($iconMap as $key => $icon) {
            if (str_contains($slug, $key)) {
                return $icon;
            }
        }

        return 'briefcase';
    }
}
