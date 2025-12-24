<?php

use App\Http\Controllers\LearningPathController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
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