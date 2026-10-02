<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LandingPageContent;

/**
 * Public, unauthenticated: which pricing plans are switched on in Admin >
 * Landing Page. The static proposal-*.html / pitch-*.html documents fetch
 * this and hide the cards of any plan that is switched off.
 */
class PlanVisibilityController extends Controller
{
    public function __invoke()
    {
        return response()->json(['plans' => LandingPageContent::planVisibility()])
            ->header('Cache-Control', 'public, max-age=60');
    }
}
