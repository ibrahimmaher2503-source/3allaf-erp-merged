<?php

return [
    // Keep exports bounded when an environment has not supplied an override.
    'export_max_rows' => env('AUDIT_EXPORT_MAX_ROWS', 5000),
];
