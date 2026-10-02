<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Company;
use App\Models\User;

/**
 * Turns a Client (+ optional Doctor/Company context) into the flat
 * {name => value} array SystemMessageService substitutes into a "System
 * Messages" body's {token} placeholders. Two vocabularies live side by side
 * here:
 *
 *  - The original 5 flat keys (client_name/doctor_name/company_name/date/time)
 *    every seeded message already uses -- untouched, so existing wording
 *    keeps rendering exactly as before.
 *  - A richer dot-path vocabulary (patient.xxx, doctor.xxx, clinic.xxx) the
 *    "#" mention picker (Custom Messages, MentionableField.jsx) offers, so a
 *    company can build its own wording using real patient/doctor/clinic
 *    fields -- for patient.xxx, that's every column on the client's OWN
 *    specialty profile (gynecologyProfile, nutritionProfile, etc.),
 *    discovered generically from that model's attributes rather than
 *    hand-listed per specialty, so it never drifts out of sync with the
 *    real schema.
 *
 * Callers merge this into whatever extra variables they already build
 * (date/time, survey_link, ...) -- see AppointmentReminderService,
 * PatientRecallService, PublicBookingService, SatisfactionSurveyService.
 */
class MessageTemplateVariableBuilder
{
    private const SPECIALTY_PROFILE_RELATIONS = [
        'gynecology' => 'gynecologyProfile',
        'internal_medicine' => 'internalMedicineProfile',
        'orthopedics' => 'orthopedicsProfile',
        'cosmetic' => 'cosmeticProfile',
        'pediatrics' => 'pediatricsProfile',
        'physiotherapy' => 'physiotherapyProfile',
        'hematology' => 'hematologyProfile',
        'general_surgery' => 'generalSurgeryProfile',
        'general_practice' => 'generalPracticeProfile',
        'nutrition' => 'nutritionProfile',
    ];

    private const EXCLUDED_PROFILE_COLUMNS = [
        'id', 'uuid', 'client_id', 'company_id', 'specialty_id',
        'created_at', 'updated_at', 'created_by', 'updated_by',
    ];

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    public function build(Client $client, ?User $doctor, Company $company, ?string $specialtyKey = null, array $extra = []): array
    {
        $variables = [
            'client_name' => $client->name ?? '',
            'doctor_name' => $doctor?->name ?? '',
            'company_name' => $company->name ?? '',

            'patient.name' => $client->name ?? '',
            'patient.phone' => $client->phone ?? '',
            'patient.email' => $client->email ?? '',
            'patient.age' => $client->age !== null ? (string) $client->age : '',
            'patient.gender' => $client->gender?->value ?? '',
            'patient.city' => $client->city ?? '',
            'patient.address' => $client->address ?? '',

            'doctor.name' => $doctor?->name ?? '',
            'doctor.phone' => $doctor?->phone ?? '',
            'doctor.email' => $doctor?->email ?? '',

            'clinic.name' => $company->name ?? '',
            'clinic.phone' => $company->phone ?? '',
            'clinic.email' => $company->email ?? '',
            'clinic.address' => $company->address ?? '',
        ];

        $relation = $specialtyKey ? (self::SPECIALTY_PROFILE_RELATIONS[$specialtyKey] ?? null) : null;

        if ($relation && method_exists($client, $relation)) {
            $profile = $client->{$relation};

            if ($profile) {
                foreach ($profile->getAttributes() as $column => $value) {
                    if (in_array($column, self::EXCLUDED_PROFILE_COLUMNS, true)) {
                        continue;
                    }

                    $variables["patient.{$column}"] = is_scalar($value) ? (string) $value : '';
                }
            }
        }

        return array_merge($variables, $extra);
    }
}
