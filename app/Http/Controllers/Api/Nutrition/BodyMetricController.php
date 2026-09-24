<?php

namespace App\Http\Controllers\Api\Nutrition;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nutrition\ExtractNutritionBodyMetricRequest;
use App\Http\Requests\Nutrition\StoreNutritionBodyMetricRequest;
use App\Http\Requests\Nutrition\UpdateNutritionBodyMetricRequest;
use App\Http\Resources\NutritionBodyMetricResource;
use App\Models\Client;
use App\Models\NutritionBodyMetric;
use App\Services\AiTokenUsageService;
use App\Services\OpenAiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BodyMetricController extends Controller
{
    use AuthorizesOwnDoctorRecords;

    public function __construct(
        protected OpenAiClient $openAi,
        protected AiTokenUsageService $aiTokenUsage,
    ) {}

    public function index(Request $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $metrics = $client->nutritionBodyMetrics()->get();

        return $this->success(NutritionBodyMetricResource::collection($metrics));
    }

    public function store(StoreNutritionBodyMetricRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);

        $data = $request->validated();

        $bmi = $this->computeBmi($data['weight_kg'] ?? null, $client->nutritionProfile?->height_cm);

        $reportPath = null;
        $reportOriginalFilename = null;
        if ($request->hasFile('report')) {
            $file = $request->file('report');
            $reportPath = $file->store('nutrition-body-metric-reports', 'local');
            $reportOriginalFilename = $file->getClientOriginalName();
        }

        $metric = $client->nutritionBodyMetrics()->create([
            ...collect($data)->except(['report', 'source'])->all(),
            'source' => $data['source'] ?? NutritionBodyMetric::SOURCE_MANUAL,
            'bmi' => $bmi,
            'report_path' => $reportPath,
            'report_original_filename' => $reportOriginalFilename,
            'recorded_by' => $request->user()->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(NutritionBodyMetricResource::make($metric), 'Measurement recorded successfully.', 201);
    }

    public function update(UpdateNutritionBodyMetricRequest $request, NutritionBodyMetric $bodyMetric)
    {
        $this->assertActingDoctorOwnsClient($request, $bodyMetric->client);

        $data = $request->validated();

        $bmi = $this->computeBmi($data['weight_kg'] ?? null, $bodyMetric->client->nutritionProfile?->height_cm);

        $reportPath = $bodyMetric->report_path;
        $reportOriginalFilename = $bodyMetric->report_original_filename;
        if ($request->hasFile('report')) {
            if ($reportPath) {
                Storage::disk('local')->delete($reportPath);
            }
            $file = $request->file('report');
            $reportPath = $file->store('nutrition-body-metric-reports', 'local');
            $reportOriginalFilename = $file->getClientOriginalName();
        }

        $bodyMetric->update([
            ...collect($data)->except('report')->all(),
            'bmi' => $bmi,
            'report_path' => $reportPath,
            'report_original_filename' => $reportOriginalFilename,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(NutritionBodyMetricResource::make($bodyMetric->fresh()), 'Measurement updated successfully.');
    }

    /**
     * Reads a photo or PDF of a body-composition device's printed/on-screen
     * report (InBody, Tanita, etc.) via OpenAI vision and returns the
     * extracted values for the frontend to pre-fill the same manual-entry
     * form with -- nothing is persisted here, the doctor still reviews/edits
     * and hits Save (store() above) same as the fully-manual path. See
     * OpenAiClient::buildVisionContentBlock() for the image-vs-PDF content
     * block split (the private disk isn't involved at all here -- the
     * upload is read straight from the request, never written to storage
     * unless/until the doctor actually saves it via store()).
     */
    public function extract(ExtractNutritionBodyMetricRequest $request, Client $client)
    {
        $this->assertActingDoctorOwnsClient($request, $client);
        $this->aiTokenUsage->assertCanUseAiTokens($request->user()->company);

        $messages = [
            ['role' => 'system', 'content' => $this->buildExtractionSystemPrompt()],
            ['role' => 'user', 'content' => [
                ['type' => 'text', 'text' => 'Extract the body composition values from this report.'],
                $this->openAi->buildVisionContentBlock($request->file('report')),
            ]],
        ];

        $response = $this->openAi->chatCompletionJson($messages, $this->extractionJsonSchema());

        $this->aiTokenUsage->recordUsage(
            $request->user()->company,
            $request->user(),
            $client,
            'nutrition_body_metric_extraction',
            (string) config('services.openai.chat_model', 'gpt-4o-mini'),
            (int) $response['usage']['prompt_tokens'],
            (int) $response['usage']['completion_tokens'],
        );

        return $this->success($response['content']);
    }

    protected function buildExtractionSystemPrompt(): string
    {
        return <<<'PROMPT'
            You are reading a photo of a body-composition analyzer's printed or
            on-screen report (e.g. InBody, Tanita, Omron). Extract the values it
            shows into the given fields. Use standard units: weight in kilograms,
            muscle/bone mass in kilograms, body fat/water in percent, waist/hip in
            centimeters, basal metabolic rate and daily calorie need in kcal,
            metabolic age in years. Some reports also include a segmental
            (right/left arm and leg, and trunk) breakdown -- extract those into
            the right_/left_ arm/leg muscle_kg and fat_percent fields, and
            trunk_muscle_kg/trunk_fat_percent, when the report shows them. If a
            field isn't shown on the report at all, return null for it -- never
            guess or estimate a value that isn't actually printed on the report.
            If the report shows a date, return it as recorded_at in YYYY-MM-DD
            format; otherwise return null for recorded_at (the frontend will
            default it to today).
            PROMPT;
    }

    protected function extractionJsonSchema(): array
    {
        $numberOrNull = ['type' => ['number', 'null']];

        return [
            'name' => 'nutrition_body_metric_extraction',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'recorded_at' => ['type' => ['string', 'null']],
                    'weight_kg' => $numberOrNull,
                    'body_fat_percent' => $numberOrNull,
                    'muscle_mass_kg' => $numberOrNull,
                    'visceral_fat_rating' => $numberOrNull,
                    'water_percent' => $numberOrNull,
                    'bone_mass_kg' => $numberOrNull,
                    'basal_metabolic_rate' => $numberOrNull,
                    'waist_cm' => $numberOrNull,
                    'hip_cm' => $numberOrNull,
                    'right_arm_muscle_kg' => $numberOrNull,
                    'left_arm_muscle_kg' => $numberOrNull,
                    'right_arm_fat_percent' => $numberOrNull,
                    'left_arm_fat_percent' => $numberOrNull,
                    'right_leg_muscle_kg' => $numberOrNull,
                    'left_leg_muscle_kg' => $numberOrNull,
                    'right_leg_fat_percent' => $numberOrNull,
                    'left_leg_fat_percent' => $numberOrNull,
                    'trunk_muscle_kg' => $numberOrNull,
                    'trunk_fat_percent' => $numberOrNull,
                    'metabolic_age' => $numberOrNull,
                    'daily_calorie_need' => $numberOrNull,
                ],
                'required' => [
                    'recorded_at', 'weight_kg', 'body_fat_percent', 'muscle_mass_kg',
                    'visceral_fat_rating', 'water_percent', 'bone_mass_kg',
                    'basal_metabolic_rate', 'waist_cm', 'hip_cm',
                    'right_arm_muscle_kg', 'left_arm_muscle_kg',
                    'right_arm_fat_percent', 'left_arm_fat_percent',
                    'right_leg_muscle_kg', 'left_leg_muscle_kg',
                    'right_leg_fat_percent', 'left_leg_fat_percent',
                    'trunk_muscle_kg', 'trunk_fat_percent',
                    'metabolic_age', 'daily_calorie_need',
                ],
                'additionalProperties' => false,
            ],
        ];
    }

    public function destroy(Request $request, NutritionBodyMetric $bodyMetric)
    {
        $this->assertActingDoctorOwnsClient($request, $bodyMetric->client);

        if ($bodyMetric->report_path) {
            Storage::disk('local')->delete($bodyMetric->report_path);
        }

        $bodyMetric->delete();

        return $this->success(null, 'Measurement deleted successfully.');
    }

    /**
     * Streams the private-disk report file. Reached only via a signed URL
     * (see NutritionBodyMetricResource::report_url) -- same pattern as
     * XrayImageController::file().
     */
    public function file(NutritionBodyMetric $bodyMetric)
    {
        abort_unless($bodyMetric->report_path, 404);

        return Storage::disk('local')->response($bodyMetric->report_path, $bodyMetric->report_original_filename);
    }

    /**
     * weight_kg=0 is a legal (if clinically meaningless) value per
     * Store/UpdateNutritionBodyMetricRequest's 'min:0' rule, so this checks
     * !== null rather than a truthy check, which would silently skip
     * computing BMI for it. The >= 1000 guard keeps an extreme
     * height/weight combination from overflowing the bmi column's
     * decimal(4,1) range (max 999.9) and throwing an unhandled
     * QueryException instead of just leaving bmi null.
     */
    protected function computeBmi(?float $weightKg, ?string $heightCm): ?float
    {
        if ($weightKg === null || $heightCm === null || (float) $heightCm <= 0) {
            return null;
        }

        $bmi = round($weightKg / (((float) $heightCm / 100) ** 2), 1);

        return $bmi >= 1000 ? null : $bmi;
    }
}
