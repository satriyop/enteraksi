<?php

namespace App\Domain\LearningPath\Notifications;

use App\Models\LearningPath;
use App\Models\LearningPathEnrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PathCompletedMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly LearningPath $path,
        public readonly LearningPathEnrollment $enrollment,
        public readonly int $completedCourses
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
        return (new MailMessage)
            ->subject("Selamat! Anda Telah Menyelesaikan Learning Path: {$this->path->title}")
            ->greeting("Selamat, {$notifiable->name}!")
            ->line("Anda telah berhasil menyelesaikan learning path \"{$this->path->title}\".")
            ->line("Anda telah menyelesaikan {$this->completedCourses} kursus dalam learning path ini.")
            ->line('Pencapaian luar biasa! Sertifikat Anda akan segera tersedia.')
            ->action('Lihat Pencapaian', route('learning-paths.show', $this->path))
            ->line('Terima kasih telah belajar bersama kami!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'learning_path.completed',
            'learning_path_id' => $this->path->id,
            'learning_path_title' => $this->path->title,
            'enrollment_id' => $this->enrollment->id,
            'completed_courses' => $this->completedCourses,
            'message' => "Selamat! Anda menyelesaikan learning path \"{$this->path->title}\"",
        ];
    }
}
