<?php

return [
    // Public tile configuration only; never put database or secret service keys here.
    'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'attribution' => env('MAP_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'),
    'boundary_path' => public_path('data/jawa-barat.geojson'),
    'boundary_metadata' => 'https://www.geoboundaries.org/api/current/gbOpen/IDN/ADM1/',
];
