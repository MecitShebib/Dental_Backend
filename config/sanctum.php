<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | Sanctum's package default is ['web'], which makes auth('sanctum')
    | check the session-based 'web' guard FIRST and only fall back to the
    | bearer token if that guard has no authenticated user. That silently
    | broke BelongsToCompany's documented guarantee of never touching the
    | Admin Panel's web-guard queries: any admin-panel request (which does
    | have an authenticated 'web' session) made auth('sanctum')->user()
    | resolve to the logged-in admin, scoping every BelongsToCompany model
    | query to the admin's own company_id -- empty (or wrong-company) data
    | on admin pages, most visibly an always-empty "Company Users" list for
    | a project admin who (correctly, per SubscriptionAccessService) isn't
    | tied to any company at all.
    |
    | The mobile/SPA API is deliberately stateless and Sanctum-token-only
    | (see CLAUDE.md), never first-party-SPA-cookie authenticated, so there
    | is no legitimate use for the session-guard fallback here. Emptying
    | this array forces auth('sanctum') to resolve strictly from the
    | bearer token, matching what BelongsToCompany already assumes.
    |
    */

    'guard' => [],

];
