<?php

return [
    // A user is flagged once this many open reports target their shop, products or video comments…
    'flagged_min_reports' => max(1, (int) env('ADMIN_FLAGGED_MIN_REPORTS', 3)),
    // …within this many days.
    'flagged_window_days' => max(1, (int) env('ADMIN_FLAGGED_WINDOW_DAYS', 30)),
    // An unfinished auction this long past its end indicates a stopped scheduler.
    'overdue_grace_minutes' => max(1, (int) env('ADMIN_AUCTION_OVERDUE_MINUTES', 2)),
];
