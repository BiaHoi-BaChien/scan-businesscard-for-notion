<?php

return [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],
    'notion' => [
        'api_key' => env('NOTION_API_KEY'),
        'database_id' => env('NOTION_DATA_SOURCE_ID'),
        'version' => env('NOTION_VERSION', '2025-09-03'),
        'property_mapping' => env('NOTION_PROPERTY_MAPPING'),
    ],
];
