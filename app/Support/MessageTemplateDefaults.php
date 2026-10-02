<?php

namespace App\Support;

/**
 * The seed wording for the 4 "System Messages" every company+specialty gets
 * (see SystemMessageService::seedForCompanySpecialty()) -- appointment
 * reminder, patient recall, booking confirmation, satisfaction survey, each
 * in all 3 languages. {placeholder} tokens are substituted by
 * SystemMessageService at send time. This is only ever read once, at seed
 * time: the actual wording a company sends lives in the custom_messages row
 * from then on, which staff can edit or delete freely like any other
 * message -- this class never overrides or falls back for an existing row.
 */
class MessageTemplateDefaults
{
    /**
     * @return array<string, array<string, string>> [key][language] => body
     */
    public static function all(): array
    {
        return [
            'appointment_reminder' => [
                'en' => 'Reminder: You have an appointment tomorrow with Dr. {doctor_name} at {company_name}, on {date} at {time}.',
                'ar' => 'تذكير: لديك موعد غدًا مع د. {doctor_name} في عيادة {company_name}، بتاريخ {date} الساعة {time}.',
                'tr' => "Hatırlatma: Yarın {date} tarihinde saat {time}'de {company_name} kliniğinde Dr. {doctor_name} ile randevunuz bulunmaktadır.",
            ],
            'patient_recall' => [
                'en' => "Hi {client_name}, it's time for your follow-up check-up at {company_name}. Please contact us to book an appointment.",
                'ar' => 'مرحبًا {client_name}، حان وقت المتابعة الدورية في عيادة {company_name}. يرجى التواصل معنا لحجز موعد.',
                'tr' => 'Merhaba {client_name}, {company_name} kliniğinde kontrol zamanınız geldi. Randevu almak için bizimle iletişime geçin.',
            ],
            'booking_confirmation' => [
                'en' => 'Your appointment with Dr. {doctor_name} on {date} at {time} is confirmed.',
                'ar' => 'تم تأكيد حجز موعدك مع د. {doctor_name} بتاريخ {date} الساعة {time}.',
                'tr' => "Dr. {doctor_name} ile {date} tarihinde saat {time}'deki randevunuz onaylandı.",
            ],
            'satisfaction_survey' => [
                'en' => 'Thank you for visiting {company_name}! Please rate your experience: {survey_link}',
                'ar' => 'شكرًا لزيارتك عيادة {company_name}! يرجى تقييم تجربتك: {survey_link}',
                'tr' => '{company_name} kliniğini ziyaret ettiğiniz için teşekkürler! Deneyiminizi değerlendirin: {survey_link}',
            ],
        ];
    }
}
