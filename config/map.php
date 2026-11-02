<?php

return [
    // Public tile configuration only; never put database or secret service keys here.
    'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'attribution' => env('MAP_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'),
    'csv_path' => env('MAP_DATASET_CSV', base_path('../Dataset/Dataset_Wilayah_2024_Bersih.csv')),
    'dataset_path' => storage_path('app/map/dataset-2024.json'),
    'locations_path' => resource_path('data/map/jawa-barat-villages.json'),
    'boundary_path' => public_path('data/jawa-barat.geojson'),
    'village_service' => 'https://geoservices.big.go.id/rbi/rest/services/BATASWILAYAH/Administrasi_AR_KelDesa_10K/MapServer/0',
    'boundary_metadata' => 'https://www.geoboundaries.org/api/current/gbOpen/IDN/ADM1/',
];
