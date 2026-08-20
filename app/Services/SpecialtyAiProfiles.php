<?php

namespace App\Services;

use App\Models\Specialty;

/**
 * Per-specialty AI assistant configuration for the 4 non-dental specialties:
 * chat system prompt, plan-generation system prompt, and the fixed procedure
 * vocabulary the AI's structured plan output is constrained to (kept small
 * and flat, matching each specialty's own 4-item treatment catalog -- see
 * app/Specialties/{Specialty}/{Specialty}Module.php's catalogItems(), the
 * exact same codes/prices, so a proposed procedure always resolves to a
 * real, already-priced TreatmentCatalog row). Dental's own AI system
 * (AiTreatmentPlanService/AiConversationService's dental-specific prompts)
 * is untouched and does not use this class -- dental's is odontogram-based,
 * not procedure-code-based, a different shape entirely.
 *
 * v1 prototype prompts, same caveat as every other Doctovaria specialty
 * clinical flow: not clinically validated, a starting point.
 */
class SpecialtyAiProfiles
{
    /**
     * @return array{procedure_code?: ?string, name_en: string}[]
     */
    public static function procedureVocabulary(string $specialtyKey): array
    {
        return match ($specialtyKey) {
            Specialty::GYNECOLOGY => [
                ['code' => 'prenatal_checkup', 'name_en' => 'Prenatal Checkup'],
                ['code' => 'ultrasound', 'name_en' => 'Ultrasound'],
                ['code' => 'delivery_package', 'name_en' => 'Delivery Package'],
                ['code' => 'postpartum_checkup', 'name_en' => 'Postpartum Checkup'],
            ],
            Specialty::INTERNAL_MEDICINE => [
                ['code' => 'chronic_initial_assessment', 'name_en' => 'Initial Chronic Disease Assessment'],
                ['code' => 'chronic_followup_visit', 'name_en' => 'Follow-up Visit'],
                ['code' => 'lab_panel', 'name_en' => 'Lab Panel'],
                ['code' => 'vital_signs_check', 'name_en' => 'Vital Signs Check'],
            ],
            Specialty::ORTHOPEDICS => [
                ['code' => 'ortho_assessment', 'name_en' => 'Orthopedic Assessment'],
                ['code' => 'physical_therapy_session', 'name_en' => 'Physical Therapy Session'],
                ['code' => 'followup_xray', 'name_en' => 'Follow-up X-Ray'],
                ['code' => 'final_assessment', 'name_en' => 'Final Rehab Assessment'],
            ],
            Specialty::COSMETIC => [
                ['code' => 'cosmetic_consultation', 'name_en' => 'Cosmetic Consultation'],
                ['code' => 'laser_session', 'name_en' => 'Laser Session'],
                ['code' => 'botox_session', 'name_en' => 'Botox Session'],
                ['code' => 'filler_session', 'name_en' => 'Filler Session'],
            ],
            default => [],
        };
    }

    /**
     * @return string[] just the codes, for the JSON schema enum
     */
    public static function procedureCodes(string $specialtyKey): array
    {
        return array_column(self::procedureVocabulary($specialtyKey), 'code');
    }

    public static function chatSystemPrompt(string $specialtyKey): string
    {
        $domain = self::domainLabel($specialtyKey);

        return <<<PROMPT
            You are a knowledgeable {$domain} assistant AI embedded in a clinic's patient
            record system, chatting with the treating doctor about one specific patient.
            You may be shown the patient's basic info and the conversation so far. Discuss
            the case naturally: answer questions, help the doctor reason through diagnosis
            and treatment options. Reply in the same language the doctor is writing in.

            If you are missing a specific piece of clinical information you would need to
            build a good treatment plan (e.g. a symptom's duration or severity, an
            examination finding), ask the doctor ONE focused question at a time in
            `reply`. When that question has a small set of likely answers, put 2-4 short
            answer choices in `options` (in the doctor's own language) so the doctor can
            tap one after examining the patient instead of typing it out. Leave `options`
            empty for open-ended questions or whenever you are not asking a question that
            has discrete answers.

            Once you have enough information to build a solid plan, set `ready_for_plan`
            to true and end `reply` with a short sentence telling the doctor they can now
            press the "Create Plan" button. Otherwise set `ready_for_plan` to false. Do
            not set it to true just because the doctor said something -- only once the
            case is actually clear enough to plan from.

            Do not produce a structured treatment plan yourself in this mode. If the
            doctor asks you to build/generate a treatment plan, respond as above -- the
            actual structured plan is produced separately once they trigger plan
            generation, grounded in this same conversation.
            PROMPT;
    }

    public static function planSystemPrompt(string $specialtyKey): string
    {
        $domain = self::domainLabel($specialtyKey);
        $procedures = implode(', ', array_column(self::procedureVocabulary($specialtyKey), 'code'));

        return <<<PROMPT
            You are a {$domain} treatment planning assistant used inside a clinic's
            patient record system. You will receive the patient's basic info and possibly
            a prior conversation between you and the treating doctor about this patient's
            case -- ending in a message from the doctor (their diagnosis, or a request to
            build the plan, or both). Use all of this context together, not just the
            final message alone.

            Produce a treatment plan made of one or more future sessions (visits), each
            separated by a number of days from the previous one (day_offset; use 0 for
            the very first session, meaning "as soon as possible"). For each session,
            decide a realistic appointment duration (30, 60, or 90 minutes) and describe
            in session_description, in the same language the doctor used, what the doctor
            will do during that specific session.

            For each session, list the procedures involved using ONLY these allowed
            procedure codes: {$procedures}. If nothing in this list fits a session, leave
            its procedures array empty and describe what's actually needed in
            session_description instead of guessing an unsupported code.

            Keep plans realistic: most cases need between 1 and 4 sessions. Never propose
            more than 8 sessions.
            PROMPT;
    }

    protected static function domainLabel(string $specialtyKey): string
    {
        return match ($specialtyKey) {
            Specialty::GYNECOLOGY => 'gynecology and obstetrics',
            Specialty::INTERNAL_MEDICINE => 'internal medicine / chronic disease management',
            Specialty::ORTHOPEDICS => 'orthopedic rehabilitation',
            Specialty::COSMETIC => 'cosmetic treatment',
            default => 'medical',
        };
    }
}
