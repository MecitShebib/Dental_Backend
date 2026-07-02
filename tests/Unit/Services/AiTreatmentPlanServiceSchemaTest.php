<?php

namespace Tests\Unit\Services;

use App\Services\AiTreatmentPlanService;
use Tests\TestCase;

class AiTreatmentPlanServiceSchemaTest extends TestCase
{
    public function test_build_json_schema_is_strict_and_lists_allowed_enums(): void
    {
        $schema = app(AiTreatmentPlanService::class)->buildJsonSchema();

        $this->assertTrue($schema['strict']);
        $this->assertSame('dental_treatment_plan', $schema['name']);

        $sessionSchema = $schema['schema']['properties']['sessions']['items'];
        $this->assertSame(['day_offset', 'duration_minutes', 'session_description', 'teeth'], $sessionSchema['required']);
        $this->assertFalse($sessionSchema['additionalProperties']);
        $this->assertSame(8, $schema['schema']['properties']['sessions']['maxItems']);

        $toothSchema = $sessionSchema['properties']['teeth']['items'];
        $this->assertContains('endo-filling', $toothSchema['properties']['endo']['enum']);
        $this->assertContains('amalgam', $toothSchema['properties']['filling_material']['enum']);
        $this->assertFalse($toothSchema['additionalProperties']);
    }
}
