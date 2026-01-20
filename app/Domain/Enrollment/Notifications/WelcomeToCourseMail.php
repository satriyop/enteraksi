<?php

namespace App\Domain\Enrollment\Notifications;

use App\Models\Course;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeToCourseMail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Course $course
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
            ->subject("Selamat bergabung di {$this->course->title}")
            ->greeting("Halo, {$notifiable->name}!")
            ->line("Anda telah berhasil mendaftar di kursus \"{$this->course->title}\".")
            ->line('Mulai perjalanan belajar Anda sekarang!')
            ->action('Mulai Belajar', route('courses.show', $this->course))
            ->line('Semoga sukses dalam pembelajaran Anda!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'enrollment.welcome',
            'course_id' => $this->course->id,
            'course_title' => $this->course->title,
            'message' => "Anda berhasil mendaftar di kursus \"{$this->course->title}\"",
        ];
    }
}
