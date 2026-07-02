<?php

namespace Tests\Unit\Services;

use App\Services\OdontogramV2Vocabulary;
use PHPUnit\Framework\TestCase;

class OdontogramV2VocabularyTest extends TestCase
{
    public function test_it_exposes_the_priced_odontogram_v2_vocabulary(): void
    {
        $this->assertSame(
            ['implant', 'tooth-crownprep', 'tooth-under-gum', 'no-tooth-after-extraction'],
            OdontogramV2Vocabulary::toothSelection()
        );

        $this->assertContains('endo-filling', OdontogramV2Vocabulary::endo());
        $this->assertContains('amalgam', OdontogramV2Vocabulary::fillingMaterial());
        $this->assertContains('mesial', OdontogramV2Vocabulary::fillingSurfaces());
        $this->assertContains('caries-occlusal', OdontogramV2Vocabulary::caries());
        $this->assertContains('mobility', OdontogramV2Vocabulary::mods());
        $this->assertContains('pulpInflam', OdontogramV2Vocabulary::indicatorFlags());
        $this->assertContains('zircon', OdontogramV2Vocabulary::crownMaterial());
        $this->assertContains('bar-prosthesis', OdontogramV2Vocabulary::bridgeUnit());
    }
}
