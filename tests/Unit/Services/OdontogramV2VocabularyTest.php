<?php

namespace Tests\Unit\Services;

use App\Services\OdontogramV2Vocabulary;
use PHPUnit\Framework\TestCase;

class OdontogramV2VocabularyTest extends TestCase
{
    public function test_it_exposes_the_priced_odontogram_v2_vocabulary(): void
    {
        $this->assertSame(
            ['implant', 'tooth-under-gum', 'no-tooth-after-extraction'],
            OdontogramV2Vocabulary::toothSelection()
        );

        $this->assertContains('crownprep', OdontogramV2Vocabulary::toothSubstrate());
        $this->assertContains('crown', OdontogramV2Vocabulary::restorationType());
        $this->assertContains('bridge', OdontogramV2Vocabulary::restorationType());
        $this->assertContains('zircon', OdontogramV2Vocabulary::restorationMaterial());
        $this->assertContains('bar-denture', OdontogramV2Vocabulary::prosthesis());
        $this->assertContains('endo-filling', OdontogramV2Vocabulary::endo());
        $this->assertNotContains('endo-resection', OdontogramV2Vocabulary::endo());
        $this->assertContains('amalgam', OdontogramV2Vocabulary::fillingMaterial());
        $this->assertContains('mesial', OdontogramV2Vocabulary::fillingSurfaces());
        $this->assertContains('marginal', OdontogramV2Vocabulary::fillingDefect());
        $this->assertContains('caries-occlusal', OdontogramV2Vocabulary::caries());
        $this->assertSame(['inflammation', 'parodontal'], OdontogramV2Vocabulary::mods());
        $this->assertContains('attrition', OdontogramV2Vocabulary::wearEdge());
        $this->assertContains('abfraction', OdontogramV2Vocabulary::wearCervical());
        $this->assertContains('tetracycline', OdontogramV2Vocabulary::discoloration());
        $this->assertContains('bracket', OdontogramV2Vocabulary::orthoAppliance());
        $this->assertSame(['m1', 'm2', 'm3'], OdontogramV2Vocabulary::mobility());
        $this->assertContains('peri-implantitis-severe', OdontogramV2Vocabulary::periImplant());
        $this->assertContains('irreversible-pulpitis', OdontogramV2Vocabulary::pulpDx());
        $this->assertContains('external-cervical', OdontogramV2Vocabulary::resorptionType());
        $this->assertContains('active-cavitated', OdontogramV2Vocabulary::rootCaries());
        $this->assertContains('crownLeakage', OdontogramV2Vocabulary::indicatorFlags());
        $this->assertNotContains('pulpInflam', OdontogramV2Vocabulary::indicatorFlags());
    }
}
