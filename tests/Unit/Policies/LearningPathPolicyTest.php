<?php

namespace Tests\Unit\Policies;

use App\Models\LearningPath;
use App\Models\User;
use App\Policies\LearningPathPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for LearningPathPolicy.
 *
 * These tests verify authorization logic for learning path operations.
 *
 * Test Matrix:
 * - User Roles: lms_admin, content_manager, learner
 * - Published Status: published, unpublished
 * - Ownership: creator vs non-creator
 */
class LearningPathPolicyTest extends TestCase
{
    use RefreshDatabase;

    private LearningPathPolicy $policy;

    private User $lmsAdmin;

    private User $contentManager;

    private User $otherContentManager;

    private User $learner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new LearningPathPolicy;

        $this->lmsAdmin = User::factory()->create(['role' => 'lms_admin']);
        $this->contentManager = User::factory()->create(['role' => 'content_manager']);
        $this->otherContentManager = User::factory()->create(['role' => 'content_manager']);
        $this->learner = User::factory()->create(['role' => 'learner']);
    }

    // ========== viewAny ==========

    public function test_any_authenticated_user_can_view_any_learning_paths(): void
    {
        $this->assertTrue($this->policy->viewAny($this->lmsAdmin));
        $this->assertTrue($this->policy->viewAny($this->contentManager));
        $this->assertTrue($this->policy->viewAny($this->learner));
    }

    // ========== view ==========

    public function test_lms_admin_can_view_any_learning_path(): void
    {
        $unpublished = LearningPath::factory()->unpublished()->create();
        $published = LearningPath::factory()->published()->create();

        $this->assertTrue($this->policy->view($this->lmsAdmin, $unpublished));
        $this->assertTrue($this->policy->view($this->lmsAdmin, $published));
    }

    public function test_content_manager_can_view_own_learning_path(): void
    {
        $learningPath = LearningPath::factory()->unpublished()->create([
            'created_by' => $this->contentManager->id,
        ]);

        $this->assertTrue($this->policy->view($this->contentManager, $learningPath));
    }

    public function test_content_manager_cannot_view_other_unpublished_learning_path(): void
    {
        $learningPath = LearningPath::factory()->unpublished()->create([
            'created_by' => $this->otherContentManager->id,
        ]);

        $this->assertFalse($this->policy->view($this->contentManager, $learningPath));
    }

    public function test_learner_can_view_published_learning_path(): void
    {
        $learningPath = LearningPath::factory()->published()->create();

        $this->assertTrue($this->policy->view($this->learner, $learningPath));
    }

    public function test_learner_cannot_view_unpublished_learning_path(): void
    {
        $learningPath = LearningPath::factory()->unpublished()->create();

        $this->assertFalse($this->policy->view($this->learner, $learningPath));
    }

    // ========== create ==========

    public function test_lms_admin_can_create_learning_path(): void
    {
        $this->assertTrue($this->policy->create($this->lmsAdmin));
    }

    public function test_content_manager_can_create_learning_path(): void
    {
        $this->assertTrue($this->policy->create($this->contentManager));
    }

    public function test_learner_cannot_create_learning_path(): void
    {
        $this->assertFalse($this->policy->create($this->learner));
    }

    // ========== update ==========

    public function test_lms_admin_can_update_any_learning_path(): void
    {
        $unpublished = LearningPath::factory()->unpublished()->create();
        $published = LearningPath::factory()->published()->create();

        $this->assertTrue($this->policy->update($this->lmsAdmin, $unpublished));
        $this->assertTrue($this->policy->update($this->lmsAdmin, $published));
    }

    public function test_content_manager_can_update_own_learning_path(): void
    {
        $learningPath = LearningPath::factory()->unpublished()->create([
            'created_by' => $this->contentManager->id,
        ]);

        $this->assertTrue($this->policy->update($this->contentManager, $learningPath));
    }

    public function test_content_manager_cannot_update_other_learning_path(): void
    {
        $learningPath = LearningPath::factory()->unpublished()->create([
            'created_by' => $this->otherContentManager->id,
        ]);

        $this->assertFalse($this->policy->update($this->contentManager, $learningPath));
    }

    public function test_learner_cannot_update_learning_path(): void
    {
        $learningPath = LearningPath::factory()->unpublished()->create();

        $this->assertFalse($this->policy->update($this->learner, $learningPath));
    }

    // ========== delete ==========

    public function test_lms_admin_can_delete_any_learning_path(): void
    {
        $unpublished = LearningPath::factory()->unpublished()->create();
        $published = LearningPath::factory()->published()->create();

        $this->assertTrue($this->policy->delete($this->lmsAdmin, $unpublished));
        $this->assertTrue($this->policy->delete($this->lmsAdmin, $published));
    }

    public function test_content_manager_can_delete_own_learning_path(): void
    {
        $learningPath = LearningPath::factory()->unpublished()->create([
            'created_by' => $this->contentManager->id,
        ]);

        $this->assertTrue($this->policy->delete($this->contentManager, $learningPath));
    }

    public function test_content_manager_cannot_delete_other_learning_path(): void
    {
        $learningPath = LearningPath::factory()->unpublished()->create([
            'created_by' => $this->otherContentManager->id,
        ]);

        $this->assertFalse($this->policy->delete($this->contentManager, $learningPath));
    }

    public function test_learner_cannot_delete_learning_path(): void
    {
        $learningPath = LearningPath::factory()->unpublished()->create();

        $this->assertFalse($this->policy->delete($this->learner, $learningPath));
    }

    // ========== publish ==========

    public function test_lms_admin_can_publish_any_learning_path(): void
    {
        $learningPath = LearningPath::factory()->unpublished()->create();

        $this->assertTrue($this->policy->publish($this->lmsAdmin, $learningPath));
    }

    public function test_content_manager_can_publish_own_learning_path(): void
    {
        $learningPath = LearningPath::factory()->unpublished()->create([
            'created_by' => $this->contentManager->id,
        ]);

        $this->assertTrue($this->policy->publish($this->contentManager, $learningPath));
    }

    public function test_content_manager_cannot_publish_other_learning_path(): void
    {
        $learningPath = LearningPath::factory()->unpublished()->create([
            'created_by' => $this->otherContentManager->id,
        ]);

        $this->assertFalse($this->policy->publish($this->contentManager, $learningPath));
    }

    public function test_learner_cannot_publish_learning_path(): void
    {
        $learningPath = LearningPath::factory()->unpublished()->create();

        $this->assertFalse($this->policy->publish($this->learner, $learningPath));
    }

    // ========== reorder ==========

    public function test_reorder_follows_update_policy(): void
    {
        $ownPath = LearningPath::factory()->unpublished()->create([
            'created_by' => $this->contentManager->id,
        ]);
        $otherPath = LearningPath::factory()->unpublished()->create([
            'created_by' => $this->otherContentManager->id,
        ]);

        // Same as update - owner can reorder own path
        $this->assertTrue($this->policy->reorder($this->contentManager, $ownPath));
        $this->assertFalse($this->policy->reorder($this->contentManager, $otherPath));

        // Admin can reorder any
        $this->assertTrue($this->policy->reorder($this->lmsAdmin, $otherPath));

        // Learner cannot reorder
        $this->assertFalse($this->policy->reorder($this->learner, $ownPath));
    }

    // ========== canManageLearningPaths Helper ==========

    public function test_can_manage_learning_paths_helper(): void
    {
        $this->assertTrue($this->lmsAdmin->canManageLearningPaths());
        $this->assertTrue($this->contentManager->canManageLearningPaths());
        $this->assertFalse($this->learner->canManageLearningPaths());

        // Trainer cannot manage learning paths (unlike courses)
        $trainer = User::factory()->create(['role' => 'trainer']);
        $this->assertFalse($trainer->canManageLearningPaths());
    }
}
