<?php

namespace App\Services;

/**
 * Mirrors the Odontogram v2 vocabulary in Dental_FrontEnd's
 * app/frontend/src/utils/odontogramV2.js (DEFAULT_PRICE_BY_KIND / FLAG_KEYS)
 * and the vendored odontogram-v2/odontogram.ts engine (GROUPS.caries /
 * GROUPS.fillingSurfaces). Update this file if those change.
 */
class OdontogramV2Vocabulary
{
    public static function toothSelection(): array
    {
        return ['implant', 'tooth-crownprep', 'tooth-under-gum', 'no-tooth-after-extraction'];
    }

    public static function crownMaterial(): array
    {
        return ['emax', 'zircon', 'metal', 'temporary', 'telescope', 'radix', 'broken'];
    }

    public static function bridgeUnit(): array
    {
        return ['zircon', 'metal', 'temporary', 'removable', 'bar', 'bar-prosthesis'];
    }

    public static function endo(): array
    {
        return [
            'endo-medical-filling',
            'endo-filling',
            'endo-filling-incomplete',
            'endo-glass-pin',
            'endo-metal-pin',
            'endo-resection',
            'parapulpal-pin',
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

    public static function caries(): array
    {
        return ['caries-subcrown', 'caries-buccal', 'caries-lingual', 'caries-mesial', 'caries-distal', 'caries-occlusal'];
    }

    public static function mods(): array
    {
        return ['inflammation', 'parodontal', 'mobility'];
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
            'bruxismWear',
            'bruxismNeckWear',
            'pulpInflam',
            'endoResection',
            'parapulpalPin',
        ];
    }
}
