<?php

namespace App\Services;

use App\Models\Company;
use App\Models\CustomMessage;
use App\Models\MessageGroup;
use App\Models\Specialty;
use App\Support\MessageTemplateDefaults;

/**
 * Appointment reminder / patient recall / booking confirmation / satisfaction
 * survey no longer have their own dedicated admin-editable template system
 * (Settings > Message Templates' old top section) -- they're seeded as 12
 * ordinary CustomMessage rows (4 keys x 3 languages) into one "System
 * Messages" MessageGroup per company+specialty, the moment that specialty is
 * subscribed (see Admin\SubscriptionController). From then on they behave
 * exactly like any other custom WhatsApp message: a staff member can edit
 * the wording or delete them outright. The 4 automated send pathways
 * (AppointmentReminderService and friends) read the CURRENT body back via
 * bodyFor() at send time -- if it's been deleted, that channel is silently
 * skipped for that send, same as a company that never had a phone/email to
 * send to.
 */
class SystemMessageService
{
    public const GROUP_NAME = 'رسائل السيستم';

    public const KEYS = ['appointment_reminder', 'patient_recall', 'booking_confirmation', 'satisfaction_survey'];

    public const LANGUAGES = ['ar', 'en', 'tr'];

    /** [key][company language] => message name. */
    protected const NAMES = [
        'appointment_reminder' => ['ar' => 'تذكير الموعد', 'en' => 'Appointment Reminder', 'tr' => 'Randevu Hatırlatması'],
        'patient_recall' => ['ar' => 'استدعاء المريض', 'en' => 'Patient Recall', 'tr' => 'Hasta Çağrısı'],
        'booking_confirmation' => ['ar' => 'تأكيد الحجز', 'en' => 'Booking Confirmation', 'tr' => 'Randevu Onayı'],
        'satisfaction_survey' => ['ar' => 'استبيان الرضا', 'en' => 'Satisfaction Survey', 'tr' => 'Memnuniyet Anketi'],
    ];

    /** [company language][message language] => language name. */
    protected const LANGUAGE_NAMES = [
        'ar' => ['ar' => 'عربي', 'en' => 'إنجليزي', 'tr' => 'تركي'],
        'en' => ['ar' => 'Arabic', 'en' => 'English', 'tr' => 'Turkish'],
        'tr' => ['ar' => 'Arapça', 'en' => 'İngilizce', 'tr' => 'Türkçe'],
    ];

    /**
     * Title of one system message, written in the COMPANY's language (chosen
     * in the admin panel) -- e.g. a Turkish company gets "Hasta Çağrısı
     * (Arapça)" for the Arabic-bodied recall message.
     */
    public static function titleFor(Company $company, string $key, string $language): string
    {
        $companyLanguage = $company->language?->value ?? 'tr';

        return self::NAMES[$key][$companyLanguage].' ('.self::LANGUAGE_NAMES[$companyLanguage][$language].')';
    }

    /**
     * Rewrites the titles of this company's system messages into its current
     * language -- only titles still equal to one of the default titles (in
     * any language), so a title staff renamed is left alone.
     */
    public function retitleForCompany(Company $company): void
    {
        $messages = CustomMessage::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id)
            ->whereNotNull('system_key')
            ->get();

        foreach ($messages as $message) {
            if (! isset(self::NAMES[$message->system_key]) || ! in_array($message->language, self::LANGUAGES, true)) {
                continue;
            }

            $defaults = collect(array_keys(self::LANGUAGE_NAMES))->map(
                fn (string $companyLanguage) => self::NAMES[$message->system_key][$companyLanguage].' ('.self::LANGUAGE_NAMES[$companyLanguage][$message->language].')',
            );
            $title = self::titleFor($company, $message->system_key, $message->language);

            if ($message->title !== $title && $defaults->contains($message->title)) {
                $message->update(['title' => $title]);
            }
        }
    }

    /**
     * Idempotent: only creates whichever of the 12 (key, language) slots
     * aren't already present in this company+specialty's System Messages
     * group, so calling it again never duplicates a row. It DOES recreate a
     * slot a staff member deleted, so it only runs when a company newly
     * gains a specialty (Admin\SubscriptionController).
     */
    public function seedForCompanySpecialty(Company $company, Specialty $specialty): void
    {
        $group = MessageGroup::query()->firstOrCreate([
            'company_id' => $company->id,
            'specialty_id' => $specialty->id,
            'name' => self::GROUP_NAME,
        ]);

        $defaults = MessageTemplateDefaults::all();

        $existing = CustomMessage::query()
            ->where('message_group_id', $group->id)
            ->whereNotNull('system_key')
            ->get(['system_key', 'language'])
            ->map(fn (CustomMessage $message) => "{$message->system_key}:{$message->language}")
            ->all();

        foreach (self::KEYS as $key) {
            foreach (self::LANGUAGES as $language) {
                if (in_array("{$key}:{$language}", $existing, true)) {
                    continue;
                }

                CustomMessage::create([
                    'company_id' => $company->id,
                    'message_group_id' => $group->id,
                    'title' => self::titleFor($company, $key, $language),
                    'body' => $defaults[$key][$language] ?? '',
                    'system_key' => $key,
                    'language' => $language,
                ]);
            }
        }
    }

    /**
     * The current wording for one automated-send slot, variables already
     * substituted -- null if there's no company+specialty group yet, or the
     * message for this exact (key, language) was deleted.
     *
     * @param  array<string, string>  $variables
     */
    public function bodyFor(Company $company, ?int $specialtyId, string $key, string $language, array $variables): ?string
    {
        $message = CustomMessage::query()
            ->where('company_id', $company->id)
            ->where('system_key', $key)
            ->where('language', $language)
            ->whereHas('group', fn ($query) => $query->where('specialty_id', $specialtyId))
            ->first();

        if (! $message) {
            return null;
        }

        return $this->substitute($message->body, $variables);
    }

    /**
     * @param  array<string, string>  $variables
     */
    protected function substitute(string $text, array $variables): string
    {
        $replacements = [];

        foreach ($variables as $name => $value) {
            $replacements['{'.$name.'}'] = $value;
        }

        return strtr($text, $replacements);
    }
}
