<?php

return [
    'query_budget' => (int) env('QUERY_BUDGET', 100),
    // Posting routes whose query count grows with the document size (one sale line, stock movement
    // and balance update per item). Over budget they are logged, never aborted halfway.
    'query_budget_log_only_routes' => ['pos.checkout', 'pos.checkout.legacy', 'reports.index'],
    'query_budget_enabled' => filter_var(env('QUERY_BUDGET_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'slow_query_ms' => (float) env('SLOW_QUERY_MS', 100),
    'slow_query_logging_enabled' => filter_var(env('SLOW_QUERY_LOG_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
];
