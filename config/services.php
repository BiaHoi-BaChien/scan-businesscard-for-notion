<?php

return [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-5.6-luna'),
    ],
    'notion' => [
        'api_key' => env('NOTION_API_KEY'),
        'data_source_id' => env('NOTION_DATA_SOURCE_ID'),
        'version' => env('NOTION_VERSION', '2026-03-11'),
        'property_mapping' => env('NOTION_PROPERTY_MAPPING'),
    ],
];
