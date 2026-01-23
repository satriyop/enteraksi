<?php

namespace App\Providers;

// Contracts
use App\Domain\Assessment\Contracts\GradingStrategyResolverContract;
use App\Domain\Assessment\Services\GradingStrategyResolver;
use App\Domain\Assessment\Strategies\ManualGradingStrategy;
use App\Domain\Assessment\Strategies\MultipleChoiceGradingStrategy;
use App\Domain\Assessment\Strategies\ShortAnswerGradingStrategy;
use App\Domain\Assessment\Strategies\TrueFalseGradingStrategy;
use App\Domain\Course\Contracts\CourseInvitationServiceContract;
use App\Domain\Course\Services\CourseInvitationService;
use App\Domain\Enrollment\Contracts\EnrollmentServiceContract;
use App\Domain\Enrollment\Services\EnrollmentService;
use App\Domain\LearningPath\Contracts\PathEnrollmentServiceContract;
use App\Domain\LearningPath\Contracts\PathProgressServiceContract;
use App\Domain\LearningPath\Services\PathEnrollmentService;
use App\Domain\LearningPath\Services\PathProgressService;
use App\Domain\LearningPath\Services\PrerequisiteEvaluatorFactory;
use App\Domain\LearningPath\Strategies\ImmediatePreviousPrerequisiteEvaluator;
use App\Domain\LearningPath\Strategies\NoPrerequisiteEvaluator;
use App\Domain\LearningPath\Strategies\SequentialPrerequisiteEvaluator;
use App\Domain\Progress\Contracts\ProgressCalculatorContract;
use App\Domain\Progress\Contracts\ProgressTrackingServiceContract;
use App\Domain\Progress\Services\ProgressCalculatorFactory;
use App\Domain\Progress\Services\ProgressTrackingService;
use App\Domain\Progress\Strategies\AssessmentInclusiveProgressCalculator;
use App\Domain\Progress\Strategies\LessonBasedProgressCalculator;
use App\Domain\Progress\Strategies\WeightedProgressCalculator;
// Observability Services
use App\Domain\Shared\Services\DomainLogger;
use App\Domain\Shared\Services\EventTimelineService;
use App\Domain\Shared\Services\HealthCheckService;
use App\Domain\Shared\Services\LogContext;
use App\Domain\Shared\Services\MetricsService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class DomainServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     */
    public array $bindings = [
        // Enrollment
        EnrollmentServiceContract::class => EnrollmentService::class,

        // Course Invitation
        CourseInvitationServiceContract::class => CourseInvitationService::class,

        // Progress
        ProgressTrackingServiceContract::class => ProgressTrackingService::class,

        // Learning Path
        PathEnrollmentServiceContract::class => PathEnrollmentService::class,
        PathProgressServiceContract::class => PathProgressService::class,
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerGradingStrategies();
        $this->registerProgressCalculators();
        $this->registerPrerequisiteEvaluators();
        $this->registerObservabilityServices();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers if needed
    }

    /**
     * Register grading strategies and resolver.
     */
    protected function registerGradingStrategies(): void
    {
        // Tag all grading strategies
        $this->app->tag([
            MultipleChoiceGradingStrategy::class,
            TrueFalseGradingStrategy::class,
            ShortAnswerGradingStrategy::class,
            ManualGradingStrategy::class,
        ], 'grading.strategies');

        // Register the strategy resolver
        $this->app->singleton(GradingStrategyResolverContract::class, function ($app) {
            return new GradingStrategyResolver(
                $app->tagged('grading.strategies')
            );
        });
    }

    /**
     * Register progress calculator strategies.
     */
    protected function registerProgressCalculators(): void
    {
        // Tag all calculators
        $this->app->tag([
            LessonBasedProgressCalculator::class,
            WeightedProgressCalculator::class,
            AssessmentInclusiveProgressCalculator::class,
        ], 'progress.calculators');

        // Default calculator binding based on configuration
        $this->app->bind(ProgressCalculatorContract::class, function ($app) {
            $calculatorType = config('lms.progress_calculator', 'lesson_based');

            return match ($calculatorType) {
                'weighted' => $app->make(WeightedProgressCalculator::class),
                'assessment_inclusive' => $app->make(AssessmentInclusiveProgressCalculator::class),
                default => $app->make(LessonBasedProgressCalculator::class),
            };
        });

        // Register the factory as singleton
        $this->app->singleton(ProgressCalculatorFactory::class);
    }

    /**
     * Register prerequisite evaluator strategies for Learning Paths.
     */
    protected function registerPrerequisiteEvaluators(): void
    {
        // Tag all evaluators
        $this->app->tag([
            SequentialPrerequisiteEvaluator::class,
            ImmediatePreviousPrerequisiteEvaluator::class,
            NoPrerequisiteEvaluator::class,
        ], 'learning_path.prerequisite_evaluators');

        // Register the factory as singleton
        $this->app->singleton(PrerequisiteEvaluatorFactory::class);
    }

    /**
     * Register observability services (logging, metrics, health checks).
     */
    protected function registerObservabilityServices(): void
    {
        // LogContext as singleton (persists through request)
        $this->app->singleton(LogContext::class);

        // DomainLogger with domain-specific channel
        $this->app->singleton(DomainLogger::class, function ($app) {
            return new DomainLogger(
                $app->make(LogContext::class),
                Log::channel('domain')
            );
        });

        // MetricsService as singleton
        $this->app->singleton(MetricsService::class);

        // HealthCheckService as singleton
        $this->app->singleton(HealthCheckService::class);

        // EventTimelineService as singleton
        $this->app->singleton(EventTimelineService::class);
    }
}
