<?php

// One optional buyer video per product review.
return ['max_kilobytes' => max(1, (int) env('REVIEW_VIDEO_MAX_KB', 51200))];
