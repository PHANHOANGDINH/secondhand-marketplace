<?php

return [
    'low_stock_threshold' => max(1, (int) env('SELLER_LOW_STOCK_THRESHOLD', 5)),
    'ending_soon_minutes' => max(1, (int) env('SELLER_AUCTION_ENDING_SOON_MINUTES', 60)),
];
