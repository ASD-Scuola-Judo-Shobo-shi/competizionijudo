<?php

declare(strict_types=1);

// Broad review prompts only: these are not eligibility rules or medical ranges.
return [
    'weight_by_age' => [
        ['max_age' => 7, 'min' => 8.0, 'max' => 65.0],
        ['max_age' => 11, 'min' => 12.0, 'max' => 100.0],
        ['max_age' => 15, 'min' => 20.0, 'max' => 150.0],
        ['max_age' => 120, 'min' => 25.0, 'max' => 220.0],
    ],
    'belt_minimum_ages' => [
        'green' => 8, 'green_blue' => 9, 'blue' => 10, 'brown' => 12,
        'black' => 14, 'red_white' => 25, 'red' => 35,
    ],
];
