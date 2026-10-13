<?php

return [
    // Final score = sum(weight × component); every component is normalised to 0..1.
    'weights' => [
        'category' => 0.30,
        'keyword' => 0.20,
        'popularity' => 0.15,
        'recency' => 0.15,
        'shop' => 0.10,
        'rating' => 0.10,
    ],
    // Guests and users without any signal: popularity and recency only.
    'cold_start_weights' => [
        'popularity' => 0.60,
        'recency' => 0.40,
    ],
    // How much one interaction counts towards category and shop affinity.
    'signals' => ['view' => 1, 'wishlist' => 3, 'order' => 5],
    // Share of a category's affinity passed to its parent, children and siblings.
    'related_category_factor' => 0.5,
    // Affinity a / (a + k): k interaction points give a component of 0.5.
    'affinity_half_saturation' => 5,
    // Views, wishlists, orders and popularity look back this many days.
    'window_days' => 30,
    'recent_searches' => 5,
    // Bayesian rating: prior_weight "virtual" reviews at the candidates' mean (fallback when nobody is rated).
    'rating_prior_weight' => 5,
    'rating_prior_mean' => 4.0,
    // ln(1 + n) / ln(1 + cap), capped at 1.
    'popularity_cap' => 50,
    // Recency halves every this many days.
    'recency_half_life_days' => 14,
    'candidate_limit' => 500,
    'popular_candidates' => 250,
    'cache_minutes' => 10,
    'view_dedupe_minutes' => 30,
    'max_results' => 24,
    'similar_price_tolerance' => '0.50',
];
