<?php

namespace App\Mail;

use App\Models\AttendanceSession;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class RollCallReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public AttendanceSession $session,
        public string $pdfPath,
    ) {
        $this->session->loadMissing([
            'classRoom.school',
            'subject',
            'teacher',
            'records',
        ]);
    }

    public function envelope(): Envelope
    {
        $className = $this->session->classRoom?->name ?? 'Class';
        $date = $this->session->session_date?->format('M j, Y') ?? now()->format('M j, Y');

        return new Envelope(
            subject: "Roll Call Report  {$className}  {$date}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.roll-call-report',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorage($this->pdfPath)
                ->as(basename($this->pdfPath))
                ->withMime('application/pdf'),
        ];
    }
}
