<?php

namespace App\Domain\Enrollment\Notifications;

use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CourseCompletedMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Course $course,
        public readonly Enrollment $enrollment
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
            ->subject("Selamat! Anda telah menyelesaikan {$this->course->title}")
            ->greeting("Selamat, {$notifiable->name}!")
            ->line("Anda telah berhasil menyelesaikan kursus \"{$this->course->title}\".")
            ->line('Sertifikat Anda akan segera tersedia.')
            ->action('Lihat Kursus', route('courses.show', $this->course))
            ->line('Terima kasih telah belajar bersama kami!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'enrollment.completed',
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
            'enrollment_id' => $this->enrollment->id,
            'message' => "Selamat! Anda menyelesaikan kursus \"{$this->course->title}\"",
        ];
    }
}
