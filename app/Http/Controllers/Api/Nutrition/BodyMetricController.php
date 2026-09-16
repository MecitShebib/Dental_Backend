<?php

namespace App\Http\Controllers\Api\Nutrition;

use App\Http\Controllers\Concerns\AuthorizesOwnDoctorRecords;
use App\Http\Controllers\Controller;
use App\Http\Requests\Nutrition\StoreNutritionBodyMetricRequest;
use App\Http\Resources\NutritionBodyMetricResource;
use App\Models\Client;
use App\Models\NutritionBodyMetric;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BodyMetricController extends Controller
{
    use AuthorizesOwnDoctorRecords;

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

        $weight = $data['weight_kg'] ?? null;
        $heightCm = $client->nutritionProfile?->height_cm;
        $bmi = ($weight && $heightCm)
            ? round((float) $weight / (((float) $heightCm / 100) ** 2), 1)
            : null;

        $reportPath = null;
        $reportOriginalFilename = null;
        if ($request->hasFile('report')) {
            $file = $request->file('report');
            $reportPath = $file->store('nutrition-body-metric-reports', 'local');
            $reportOriginalFilename = $file->getClientOriginalName();
        }

        $metric = $client->nutritionBodyMetrics()->create([
            ...collect($data)->except(['report'])->all(),
            'source' => NutritionBodyMetric::SOURCE_MANUAL,
            'bmi' => $bmi,
            'report_path' => $reportPath,
            'report_original_filename' => $reportOriginalFilename,
            'recorded_by' => $request->user()->id,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return $this->success(NutritionBodyMetricResource::make($metric), 'Measurement recorded successfully.', 201);
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
}
