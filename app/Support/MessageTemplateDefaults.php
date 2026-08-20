<?php

namespace App\Support;

/**
 * The built-in text for every automated message the app sends, keyed by
 * [event key][channel][language]. {placeholder} tokens are substituted by
 * MessageTemplateService::render() at send time. A company's own
 * MessageTemplate row (if any) overrides the matching slot here -- this
 * array is the fallback, not a parallel copy of the "real" wording, so
 * there is exactly one place that defines what these messages say.
 */
class MessageTemplateDefaults
{
    /**
     * @return array<string, array<string, array<string, array{subject?: string, body: string}>>>
     */
    public static function all(): array
    {
        return [
            'appointment_reminder' => [
                'sms' => [
                    'en' => ['body' => 'Reminder: You have an appointment tomorrow with Dr. {doctor_name} at {company_name}, on {date} at {time}.'],
                    'ar' => ['body' => 'تذكير: لديك موعد غدًا مع د. {doctor_name} في عيادة {company_name}، بتاريخ {date} الساعة {time}.'],
                    'tr' => ['body' => "Hatırlatma: Yarın {date} tarihinde saat {time}'de {company_name} kliniğinde Dr. {doctor_name} ile randevunuz bulunmaktadır."],
                ],
                'email' => [
                    'en' => [
                        'subject' => 'Reminder: Your appointment tomorrow at {company_name}',
                        'body' => "Hello {client_name},\nThis is a reminder that you have an appointment tomorrow with Dr. {doctor_name} at {company_name}, on {date} at {time}.\nWe look forward to seeing you.",
                    ],
                    'ar' => [
                        'subject' => 'تذكير بموعدك غدًا في {company_name}',
                        'body' => "مرحبًا {client_name}،\nهذا تذكير بأن لديك موعدًا غدًا مع د. {doctor_name} في عيادة {company_name}، بتاريخ {date} الساعة {time}.\nبانتظار زيارتكم.",
                    ],
                    'tr' => [
                        'subject' => 'Hatırlatma: Yarın {company_name} kliniğindeki randevunuz',
                        'body' => "Merhaba {client_name},\nYarın {date} tarihinde saat {time}'de {company_name} kliniğinde Dr. {doctor_name} ile bir randevunuz olduğunu hatırlatmak isteriz.\nSizi görmekten mutluluk duyarız.",
                    ],
                ],
            ],
            'patient_recall' => [
                'sms' => [
                    'en' => ['body' => "Hi {client_name}, it's time for your follow-up check-up at {company_name}. Please contact us to book an appointment."],
                    'ar' => ['body' => 'مرحبًا {client_name}، حان وقت المتابعة الدورية في عيادة {company_name}. يرجى التواصل معنا لحجز موعد.'],
                    'tr' => ['body' => 'Merhaba {client_name}, {company_name} kliniğinde kontrol zamanınız geldi. Randevu almak için bizimle iletişime geçin.'],
                ],
                'email' => [
                    'en' => [
                        'subject' => 'Time for your follow-up at {company_name}',
                        'body' => "Hello {client_name},\nIt's been a while since your last visit -- it's time for a follow-up check-up.\nPlease contact us to book your next appointment. We look forward to seeing you.",
                    ],
                    'ar' => [
                        'subject' => 'حان وقت المتابعة في {company_name}',
                        'body' => "مرحبًا {client_name}،\nمرّ وقت منذ زيارتك الأخيرة، حان وقت المتابعة الدورية.\nيرجى التواصل معنا لحجز موعدك القادم. بانتظار زيارتكم.",
                    ],
                    'tr' => [
                        'subject' => '{company_name} kliniğinde kontrol zamanı',
                        'body' => "Merhaba {client_name},\nSon ziyaretinizin üzerinden bir süre geçti, kontrol zamanınız geldi.\nBir sonraki randevunuzu almak için bizimle iletişime geçin. Sizi görmekten mutluluk duyarız.",
                    ],
                ],
            ],
            'booking_confirmation' => [
                'sms' => [
                    'en' => ['body' => 'Your appointment with Dr. {doctor_name} on {date} at {time} is confirmed.'],
                    'ar' => ['body' => 'تم تأكيد حجز موعدك مع د. {doctor_name} بتاريخ {date} الساعة {time}.'],
                    'tr' => ['body' => "Dr. {doctor_name} ile {date} tarihinde saat {time}'deki randevunuz onaylandı."],
                ],
            ],
            'satisfaction_survey' => [
                'sms' => [
                    'en' => ['body' => 'Thank you for visiting {company_name}! Please rate your experience: {survey_link}'],
                    'ar' => ['body' => 'شكرًا لزيارتك عيادة {company_name}! يرجى تقييم تجربتك: {survey_link}'],
                    'tr' => ['body' => '{company_name} kliniğini ziyaret ettiğiniz için teşekkürler! Deneyiminizi değerlendirin: {survey_link}'],
                ],
                'email' => [
                    'en' => [
                        'subject' => 'How was your visit to {company_name}?',
                        'body' => "Hello {client_name},\nThank you for visiting {company_name}. We'd love to hear about your experience -- please take a moment to rate your visit:\n{survey_link}",
                    ],
                    'ar' => [
                        'subject' => 'كيف كانت زيارتك لعيادة {company_name}؟',
                        'body' => "مرحبًا {client_name}،\nشكرًا لزيارتك عيادة {company_name}. يسعدنا معرفة رأيك -- يرجى تخصيص لحظة لتقييم زيارتك:\n{survey_link}",
                    ],
                    'tr' => [
                        'subject' => '{company_name} ziyaretiniz nasıldı?',
                        'body' => "Merhaba {client_name},\n{company_name} kliniğini ziyaret ettiğiniz için teşekkürler. Deneyiminizi öğrenmek isteriz -- lütfen ziyaretinizi değerlendirin:\n{survey_link}",
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, string> placeholder name => human description, for the settings UI
     */
    public static function placeholdersFor(string $key): array
    {
        return match ($key) {
            'appointment_reminder', 'booking_confirmation' => [
                'client_name' => 'Patient name',
                'doctor_name' => 'Doctor name',
                'company_name' => 'Clinic name',
                'date' => 'Appointment date',
                'time' => 'Appointment time',
            ],
            'patient_recall' => [
                'client_name' => 'Patient name',
                'company_name' => 'Clinic name',
            ],
            'satisfaction_survey' => [
                'client_name' => 'Patient name',
                'company_name' => 'Clinic name',
                'survey_link' => 'Link to the rating page',
            ],
            default => [],
        };
    }
}
