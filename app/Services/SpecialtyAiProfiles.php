<?php

namespace App\Services;

use App\Models\Specialty;

/**
 * Per-specialty AI assistant configuration for every non-dental specialty:
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
            Specialty::NUTRITION => [
                ['code' => 'nutrition_consultation', 'name_en' => 'Initial Nutrition Consultation'],
                ['code' => 'followup_session', 'name_en' => 'Follow-up Session'],
                ['code' => 'body_composition_analysis', 'name_en' => 'Body Composition Analysis'],
                ['code' => 'meal_plan_revision', 'name_en' => 'Meal Plan Revision'],
            ],
            Specialty::PEDIATRICS => [
                ['code' => 'ped_well_child_visit', 'name_en' => 'Well-Child Visit'],
                ['code' => 'ped_vaccination_visit', 'name_en' => 'Vaccination Visit'],
                ['code' => 'ped_growth_assessment', 'name_en' => 'Growth & Development Assessment'],
                ['code' => 'ped_sick_visit', 'name_en' => 'Sick Child Visit'],
            ],
            Specialty::PHYSIOTHERAPY => [
                ['code' => 'phy_initial_evaluation', 'name_en' => 'Initial Physiotherapy Evaluation'],
                ['code' => 'phy_therapy_session', 'name_en' => 'Physiotherapy Session'],
                ['code' => 'phy_reevaluation', 'name_en' => 'Re-evaluation'],
                ['code' => 'phy_final_evaluation', 'name_en' => 'Final Evaluation'],
            ],
            Specialty::HEMATOLOGY => [
                ['code' => 'hem_consultation', 'name_en' => 'Hematology Consultation'],
                ['code' => 'hem_cbc_control', 'name_en' => 'CBC Control'],
                ['code' => 'hem_iron_infusion', 'name_en' => 'IV Iron Infusion'],
                ['code' => 'hem_evaluation', 'name_en' => 'Treatment Evaluation'],
            ],
            Specialty::GENERAL_SURGERY => [
                ['code' => 'gs_preop_evaluation', 'name_en' => 'Pre-operative Evaluation'],
                ['code' => 'gs_surgery', 'name_en' => 'Surgical Procedure'],
                ['code' => 'gs_wound_care', 'name_en' => 'Wound Care & Suture Removal'],
                ['code' => 'gs_postop_control', 'name_en' => 'Post-operative Control'],
            ],
            Specialty::GENERAL_PRACTICE => [
                ['code' => 'gp_examination', 'name_en' => 'General Examination'],
                ['code' => 'gp_followup', 'name_en' => 'Follow-up Visit'],
                ['code' => 'gp_lab_panel', 'name_en' => 'Basic Lab Panel'],
                ['code' => 'gp_checkup', 'name_en' => 'Periodic Check-up'],
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

    /**
     * $languageName is the conversation's pinned language ("English"/"Arabic"/
     * "Turkish"), resolved once per thread by
     * AiConversationService::resolveConversationLanguage(). It is stated
     * explicitly instead of letting the model re-infer the language from the
     * latest message every turn, which made threads flip language halfway
     * through (QA audit 2026-09-22, reproduced in nutrition and dental alike).
     */
    public static function chatSystemPrompt(string $specialtyKey, string $languageName): string
    {
        $domain = self::domainLabel($specialtyKey);

        return <<<PROMPT
            You are a knowledgeable {$domain} assistant AI embedded in a clinic's patient
            record system, chatting with the treating doctor about one specific patient.
            You may be shown the patient's basic info and the conversation so far. Discuss
            the case naturally: answer questions, help the doctor reason through diagnosis
            and treatment options.

            This conversation is conducted in {$languageName}. Write every reply, and
            every answer choice in `options`, in {$languageName} for the whole
            conversation -- never switch to another language, even if an individual
            message you receive happens to be written in a different one.

            If you are missing a specific piece of clinical information you would need to
            build a good treatment plan (e.g. a symptom's duration or severity, an
            examination finding), ask the doctor ONE focused question at a time in
            `reply`. When that question has a small set of likely answers, put 2-4 short
            answer choices in `options` so the doctor can tap one after examining the
            patient instead of typing it out. Leave `options` empty for open-ended
            questions or whenever you are not asking a question that has discrete
            answers.

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

    public static function planSystemPrompt(string $specialtyKey, string $languageName): string
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

            This case has been discussed in {$languageName}: write session_description
            and every other free-text field you produce in {$languageName}, regardless of
            what language any individual message happens to be written in.

            Produce a treatment plan made of one or more future sessions (visits), each
            separated by a number of days from the previous one (day_offset; use 0 for
            the very first session, meaning "as soon as possible"). For each session,
            decide a realistic appointment duration (30, 60, or 90 minutes) and describe
            in session_description what the doctor will do during that specific session.

            For each session, list the procedures involved using ONLY these allowed
            procedure codes: {$procedures}. If nothing in this list fits a session, leave
            its procedures array empty and describe what's actually needed in
            session_description instead of guessing an unsupported code.

            Keep plans realistic: most cases need between 1 and 4 sessions. Never propose
            more than 8 sessions.
            PROMPT.self::nutritionPlanAddendum($specialtyKey, $languageName);
    }

    /**
     * Nutrition-only: the AI also writes a diet_plan and exercise_plan for
     * the doctor to review, grounded in whatever profile/body-metric
     * context AiConversationService::buildNutritionContextText() already
     * folded into the conversation (height, allergies, goal, recent
     * measurements, and -- if a follow-up program is open -- the
     * baseline comparison). See
     * docs/superpowers/specs/2026-09-15-nutrition-specialty-expansion-design.md.
     */
    protected static function nutritionPlanAddendum(string $specialtyKey, string $languageName): string
    {
        if ($specialtyKey !== Specialty::NUTRITION) {
            return '';
        }

        return "\n\n".<<<PROMPT
            Also write two more fields, both in {$languageName}, both addressed to the
            PATIENT (not the doctor) since they may be shown directly to them:

            diet_plan: a meal table grounded in the patient's profile (dietary type,
            allergies, chronic conditions, goal) and recent body-metric trend already
            given to you above. Give exactly 3 interchangeable options for each of
            breakfast, lunch, dinner and snacks -- each option one short, concrete meal
            with portions (e.g. "2 boiled eggs, 1 slice wholegrain bread, cucumber").
            Also give a daily_calories target (integer kcal, or null if not
            appropriate), water_liters (daily water, e.g. 2.5) and brief notes (general
            rules, foods to avoid). If a follow-up program is already open and a
            baseline comparison was given to you, adjust this plan for the next period
            based on that progress rather than starting from scratch.

            exercise_plan: a weekly table with one row per day, Saturday through
            Friday: activity (what to do that day, concrete and matching the
            patient's activity level and goal) and duration_minutes (integer). For a
            rest day use an empty activity and null duration. Add brief notes
            (warm-up, intensity, precautions).

            Both are required and must not be empty, even for a very short
            consultation-only plan -- give at least brief, sensible guidance in each.
            All text inside them must be in {$languageName}.
            PROMPT;
    }

    protected static function domainLabel(string $specialtyKey): string
    {
        return match ($specialtyKey) {
            Specialty::GYNECOLOGY => 'gynecology and obstetrics',
            Specialty::INTERNAL_MEDICINE => 'internal medicine / chronic disease management',
            Specialty::ORTHOPEDICS => 'orthopedic rehabilitation',
            Specialty::COSMETIC => 'cosmetic treatment',
            Specialty::NUTRITION => 'nutrition and dietetics',
            Specialty::PEDIATRICS => 'pediatrics (child health)',
            Specialty::PHYSIOTHERAPY => 'physiotherapy and physical rehabilitation',
            Specialty::HEMATOLOGY => 'hematology',
            Specialty::GENERAL_SURGERY => 'general surgery and perioperative care',
            Specialty::GENERAL_PRACTICE => 'general practice / primary care',
            default => 'medical',
        };
    }
}
