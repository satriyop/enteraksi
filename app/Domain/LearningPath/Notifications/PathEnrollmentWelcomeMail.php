<?php

namespace App\Domain\LearningPath\Notifications;

use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PathEnrollmentWelcomeMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly LearningPath $path,
        public readonly LearningPathEnrollment $enrollment
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $totalCourses = $this->path->courses()->count();

        return (new MailMessage)
            ->subject("Selamat Bergabung di Learning Path: {$this->path->title}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Anda telah terdaftar di learning path \"{$this->path->title}\".")
            ->line("Learning path ini terdiri dari {$totalCourses} kursus yang akan membantu Anda mencapai tujuan pembelajaran.")
            ->action('Mulai Belajar', route('learning-paths.show', $this->path))
            ->line('Selamat belajar dan semoga sukses!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'learning_path.enrollment.created',
            'learning_path_id' => $this->path->id,
            'learning_path_title' => $this->path->title,
            'enrollment_id' => $this->enrollment->id,
            'message' => "Anda terdaftar di learning path \"{$this->path->title}\"",
        ];
    }
}
