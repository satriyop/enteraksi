<?php

use App\Http\Controllers\LearningPathController;
use App\Http\Controllers\LearningPathEnrollmentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    // ============================================
    // Learner Routes (prefixed with /learner)
    // ============================================
    Route::prefix('learner')->name('learner.')->group(function () {
        // My Learning Paths
        Route::get('learning-paths', [LearningPathEnrollmentController::class, 'index'])
            ->name('learning-paths.index');

        // Browse Learning Paths
        Route::get('learning-paths/browse', [LearningPathEnrollmentController::class, 'browse'])
            ->name('learning-paths.browse');

        // View Learning Path (with enrollment status)
        Route::get('learning-paths/{learningPath}', [LearningPathEnrollmentController::class, 'show'])
            ->name('learning-paths.show');

        // Enroll in Learning Path
        Route::post('learning-paths/{learningPath}/enroll', [LearningPathEnrollmentController::class, 'enroll'])
            ->name('learning-paths.enroll');

        // View Progress (uses learningPath ID, controller finds enrollment)
        Route::get('learning-paths/{learningPath}/progress', [LearningPathEnrollmentController::class, 'progress'])
            ->name('learning-paths.progress');

        // Drop from Learning Path (uses learningPath ID, controller finds enrollment)
        Route::delete('learning-paths/{learningPath}/drop', [LearningPathEnrollmentController::class, 'drop'])
            ->name('learning-paths.drop');
    });

    // ============================================
    // Admin Routes (existing CRUD)
    // ============================================
    // Learning Path CRUD
    Route::resource('learning-paths', LearningPathController::class)->middleware(['auth', 'verified']);

    // Learning Path Publishing
    Route::put('learning-paths/{learning_path}/publish', [LearningPathController::class, 'publish'])
        ->name('learning-paths.publish')
        ->middleware('can:publish,learning_path');
    Route::put('learning-paths/{learning_path}/unpublish', [LearningPathController::class, 'unpublish'])
        ->name('learning-paths.unpublish')
        ->middleware('can:publish,learning_path');

    // Learning Path Course Reordering
    Route::post('learning-paths/{learning_path}/reorder', [LearningPathController::class, 'reorder'])
        ->name('learning-paths.reorder')
        ->middleware('can:reorder,learning_path');
});
