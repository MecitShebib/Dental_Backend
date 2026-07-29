<?php

namespace Tests\Unit\Services;

use App\Services\AiTreatmentPlanService;
use Tests\TestCase;

class AiTreatmentPlanServiceOdontogramTest extends TestCase
{
    protected function baseTooth(array $overrides = []): array
    {
        return array_merge([
            'tooth_number' => 13,
            'tooth_selection' => null,
            'tooth_substrate' => null,
            'restoration_type' => null,
            'restoration_material' => null,
            'prosthesis' => null,
            'endo' => null,
            'filling_material' => null,
            'filling_surfaces' => [],
            'filling_defect' => null,
            'filling_defect_surfaces' => [],
            'caries' => [],
            'mods' => [],
            'wear_edge' => null,
            'wear_cervical' => null,
            'discoloration' => null,
            'ortho_appliance' => null,
            'mobility' => null,
            'peri_implant' => null,
            'pulp_dx' => null,
            'resorption_type' => null,
            'root_caries' => null,
            'indicator_flags' => [],
        ], $overrides);
    }

    public function test_build_odontogram_status_maps_ai_fields_to_widget_shape(): void
    {
        $status = app(AiTreatmentPlanService::class)->buildOdontogramStatus([
            $this->baseTooth([
                'endo' => 'endo-filling-incomplete',
                'indicator_flags' => ['endoResection'],
            ]),
        ]);

        $this->assertSame('1.3', $status['version']);
        $this->assertSame('endo-filling-incomplete', $status['teeth']['13']['endo']);
        $this->assertTrue($status['teeth']['13']['endoResection']);
        $this->assertArrayNotHasKey('restorationType', $status['teeth']['13']);
    }

    public function test_restoration_type_and_material_are_only_set_as_a_pair(): void
    {
        $onlyType = app(AiTreatmentPlanService::class)->buildOdontogramStatus([
            $this->baseTooth(['restoration_type' => 'crown']),
        ]);
        $this->assertArrayNotHasKey('restorationType', $onlyType['teeth']['13']);

        $both = app(AiTreatmentPlanService::class)->buildOdontogramStatus([
            $this->baseTooth(['restoration_type' => 'crown', 'restoration_material' => 'zircon']),
        ]);
        $this->assertSame('crown', $both['teeth']['13']['restorationType']);
        $this->assertSame('zircon', $both['teeth']['13']['restorationMaterial']);
    }

    public function test_filling_defect_folds_into_a_per_surface_map(): void
    {
        $status = app(AiTreatmentPlanService::class)->buildOdontogramStatus([
            $this->baseTooth(['filling_defect' => 'marginal', 'filling_defect_surfaces' => ['mesial', 'distal']]),
        ]);

        $this->assertSame(
            ['mesial' => 'marginal', 'distal' => 'marginal'],
            $status['teeth']['13']['fillingDefect'],
        );
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
