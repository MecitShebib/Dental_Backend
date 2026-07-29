<?php

namespace App\Services;

/**
 * Mirrors the Odontogram v2 vendored library's actual data model (v1.30.0,
 * Dental_FrontEnd's src/vendor/odontogram-v2/odontogram.ts defaultState() /
 * registry/restorations.ts RESTORATION_MATRIX) and the pricing vocabulary in
 * Dental_FrontEnd's src/utils/odontogramV2.js (DEFAULT_PRICE_BY_KIND).
 * Update this file if either of those change.
 */
class OdontogramV2Vocabulary
{
    public static function toothSelection(): array
    {
        return ['implant', 'tooth-under-gum', 'no-tooth-after-extraction'];
    }

    public static function toothSubstrate(): array
    {
        return ['radix', 'broken', 'crownprep'];
    }

    public static function restorationType(): array
    {
        return ['crown', 'inlay', 'onlay', 'veneer', 'bridge'];
    }

    public static function restorationMaterial(): array
    {
        return ['emax', 'gold', 'gradia', 'zircon', 'metal', 'metal-ceramic', 'telescope', 'temporary'];
    }

    public static function prosthesis(): array
    {
        return ['healing-abutment', 'locator', 'locator-denture', 'bar', 'bar-denture', 'removable-partial', 'removable-full'];
    }

    public static function endo(): array
    {
        return [
            'endo-medical-filling',
            'endo-filling',
            'endo-filling-incomplete',
            'endo-glass-pin',
            'endo-metal-pin',
        ];
    }

    public static function fillingMaterial(): array
    {
        return ['amalgam', 'composite', 'gic', 'temporary'];
    }

    public static function fillingSurfaces(): array
    {
        return ['buccal', 'lingual', 'mesial', 'distal', 'occlusal'];
    }

    public static function fillingDefect(): array
    {
        return ['marginal', 'fracture', 'wear'];
    }

    public static function caries(): array
    {
        return ['caries-subcrown', 'caries-buccal', 'caries-lingual', 'caries-mesial', 'caries-distal', 'caries-occlusal'];
    }

    public static function mods(): array
    {
        return ['inflammation', 'parodontal'];
    }

    public static function wearEdge(): array
    {
        return ['attrition', 'erosion'];
    }

    public static function wearCervical(): array
    {
        return ['abrasion', 'abfraction', 'erosion'];
    }

    public static function discoloration(): array
    {
        return ['tetracycline', 'fluorosis', 'nonvital', 'extrinsic', 'other'];
    }

    public static function orthoAppliance(): array
    {
        return ['bracket', 'band'];
    }

    public static function mobility(): array
    {
        return ['m1', 'm2', 'm3'];
    }

    public static function periImplant(): array
    {
        return ['mucositis', 'peri-implantitis-mild', 'peri-implantitis-moderate', 'peri-implantitis-severe'];
    }

    public static function pulpDx(): array
    {
        return ['reversible-pulpitis', 'irreversible-pulpitis', 'necrosis'];
    }

    public static function resorptionType(): array
    {
        return ['internal', 'external-cervical'];
    }

    public static function rootCaries(): array
    {
        return ['active', 'arrested', 'active-cavitated'];
    }

    public static function indicatorFlags(): array
    {
        return [
            'crownNeeded',
            'crownReplace',
            'missingClosed',
            'extractionPlan',
            'extractionWound',
            'bridgePillar',
            'fissureSealing',
            'contactMesial',
            'contactDistal',
            'brokenMesial',
            'brokenIncisal',
            'brokenDistal',
            'parapulpalPin',
            'endoResection',
            'calculus',
            'crownLeakage',
        ];
    }
}
