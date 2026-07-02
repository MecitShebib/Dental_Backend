<?php

namespace Tests\Unit\Services;

use App\Services\AiTreatmentPlanService;
use Tests\TestCase;

class AiTreatmentPlanServiceOdontogramTest extends TestCase
{
    public function test_build_odontogram_status_maps_ai_fields_to_widget_shape(): void
    {
        $status = app(AiTreatmentPlanService::class)->buildOdontogramStatus([
            [
                'tooth_number' => 13,
                'tooth_selection' => null,
                'crown_material' => null,
                'bridge_unit' => null,
                'endo' => 'endo-filling-incomplete',
                'filling_material' => null,
                'filling_surfaces' => [],
                'caries' => [],
                'mods' => [],
                'indicator_flags' => ['pulpInflam'],
            ],
        ]);

        $this->assertSame('1.3', $status['version']);
        $this->assertSame('endo-filling-incomplete', $status['teeth']['13']['endo']);
        $this->assertTrue($status['teeth']['13']['pulpInflam']);
        $this->assertArrayNotHasKey('crownMaterial', $status['teeth']['13']);
    }

    public function test_build_planned_summary_matches_the_frontend_visit_odontogram_envelope(): void
    {
        $json = app(AiTreatmentPlanService::class)->buildPlannedSummary([
            'version' => '1.3',
            'globals' => [],
            'teeth' => ['13' => ['endo' => 'endo-filling-incomplete']],
        ]);
        $decoded = json_decode($json, true);

        $this->assertTrue($decoded['__visit_odontogram__']);
        $this->assertSame(2, $decoded['companyVersion']);
        $this->assertSame([], $decoded['selectedTeeth']);
        $this->assertSame('endo-filling-incomplete', $decoded['odontogramV2Status']['teeth']['13']['endo']);
    }
}
