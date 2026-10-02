<?php

namespace App\Mail;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "You have a new appointment" notice to the appointment's doctor -- sent
 * by AppointmentObserver whenever an appointment is created, whichever
 * code path created it (appointment screen, online booking, AI plan, care
 * plan).
 */
class NewAppointmentForDoctorMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Appointment $appointment) {}

    public function envelope(): Envelope
    {
        $appointment = $this->appointment;

        return new Envelope(
            subject: 'New appointment: '.($appointment->client?->name ?? 'patient').' — '
                .$appointment->date?->format('Y-m-d').' '.$this->time($appointment->start_time),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.message-shell',
            with: [
                'companyName' => $this->appointment->company?->name ?? '',
                'body' => $this->body(),
                'isRtl' => false,
                'lang' => 'en',
            ],
        );
    }

    public function body(): string
    {
        $appointment = $this->appointment;
        $client = $appointment->client;

        $lines = [
            'Hello '.($appointment->doctor?->name ?? 'Doctor').',',
            '',
            'You have a new appointment.',
            '',
            'Date: '.$appointment->date?->format('l, Y-m-d'),
            'Time: '.$this->time($appointment->start_time).($appointment->end_time ? ' – '.$this->time($appointment->end_time) : ''),
        ];

        if ($appointment->duration_minutes) {
            $lines[] = 'Duration: '.$appointment->duration_minutes.' min';
        }
        if ($appointment->booked_online) {
            $lines[] = 'Booked online by the patient';
        }

        $lines[] = '';
        $lines[] = 'Patient';
        $lines[] = 'Name: '.($client?->name ?? '—');
        if ($client?->client_code) {
            $lines[] = 'Patient code: '.$client->client_code;
        }
        if ($client?->phone) {
            $lines[] = 'Phone: '.$client->phone;
        }
        if ($client?->email) {
            $lines[] = 'Email: '.$client->email;
        }
        if ($client?->gender) {
            $lines[] = 'Gender: '.ucfirst($client->gender->value);
        }
        $age = $client?->date_of_birth ? $client->date_of_birth->age : $client?->age;
        if ($age) {
            $lines[] = 'Age: '.$age;
        }
        if ($client?->date_of_birth) {
            $lines[] = 'Date of birth: '.$client->date_of_birth->format('Y-m-d');
        }
        if ($client?->city) {
            $lines[] = 'City: '.$client->city;
        }
        if ($client?->medical_notes) {
            $lines[] = 'Medical notes: '.$client->medical_notes;
        }

        $notes = trim((string) ($appointment->notes ?: $appointment->planned_summary));
        if ($notes !== '') {
            $lines[] = '';
            $lines[] = 'Appointment notes: '.$notes;
        }

        return implode("\n", $lines);
    }

    protected function time(?string $value): string
    {
        return $value ? substr($value, 0, 5) : '';
    }
}
