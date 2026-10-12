<?php

return ['max_files' => 3, 'max_kilobytes' => max(1, (int) env('PRODUCT_VIDEO_MAX_KB', 51200))];
