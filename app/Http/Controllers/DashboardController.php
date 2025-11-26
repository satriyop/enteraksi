<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isLearner()) {
            return redirect()->route('learner.dashboard');
        }

        $stats = [
            'programs' => 0,
            'courses' => $this->getCoursesCount($user),
            'learners' => $this->getLearnersCount($user),
        ];

        return Inertia::render('Dashboard', [
            'stats' => $stats,
        ]);
    }

    private function getCoursesCount(User $user): int
    {
        if ($user->isLmsAdmin()) {
            return Course::count();
        }

        return Course::where('user_id', $user->id)->count();
    }

    private function getLearnersCount(User $user): int
    {
        if ($user->isLmsAdmin()) {
            return User::where('role', 'learner')->count();
        }

        return 0;
    }
}
