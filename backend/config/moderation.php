<?php

// Listings go live without review; the community flags problems and admins act on them.
return [
    // Distinct users with an open report on one live listing before it is hidden for admin review.
    'auto_hide_reports' => (int) env('MODERATION_AUTO_HIDE_REPORTS', 3),
];
