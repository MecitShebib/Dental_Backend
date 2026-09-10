<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 3D Odontogram
    |--------------------------------------------------------------------------
    |
    | Gates two things at once: the "3D Odontogram" tab on the frontend
    | Client Details page, and the X-ray AI odontogram analysis job
    | (AnalyzeXrayImageJob) that feeds it. The two only exist for each
    | other's sake -- the AI reading has no other consumer -- so one flag
    | covers both rather than needing two toggles kept in sync. Off by
    | default; flip THREE_D_ODONTOGRAM_ENABLED=true in .env to turn it on.
    |
    */

    'three_d_odontogram' => env('THREE_D_ODONTOGRAM_ENABLED', false),

];
