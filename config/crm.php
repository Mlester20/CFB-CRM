<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Revenue target
    |--------------------------------------------------------------------------
    |
    | The goal shown on the dashboard's "Revenue Goal" card, in pesos. There is no
    | source for it yet, so it is optional: leave CRM_REVENUE_TARGET empty and the
    | progress bar is hidden.
    |
    */

    'revenue_target' => is_numeric(env('CRM_REVENUE_TARGET')) && (float) env('CRM_REVENUE_TARGET') > 0
        ? (float) env('CRM_REVENUE_TARGET')
        : null,

];
