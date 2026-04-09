<?php

declare(strict_types=1);

return [
    'routes' => [
        // SPA entry point
        ['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],

        // Monitors CRUD
        ['name' => 'monitor#index',  'url' => '/api/v1/monitors',         'verb' => 'GET'],
        ['name' => 'monitor#create', 'url' => '/api/v1/monitors',         'verb' => 'POST'],
        ['name' => 'monitor#show',   'url' => '/api/v1/monitors/{id}',    'verb' => 'GET'],
        ['name' => 'monitor#update', 'url' => '/api/v1/monitors/{id}',    'verb' => 'PUT'],
        ['name' => 'monitor#destroy','url' => '/api/v1/monitors/{id}',    'verb' => 'DELETE'],
        ['name' => 'monitor#pause',  'url' => '/api/v1/monitors/{id}/pause', 'verb' => 'POST'],
        ['name' => 'monitor#test',   'url' => '/api/v1/monitors/test',    'verb' => 'POST'],

        // History
        ['name' => 'history#index',  'url' => '/api/v1/monitors/{monitorId}/history', 'verb' => 'GET'],

        // Settings
        ['name' => 'settings#show',  'url' => '/api/v1/settings', 'verb' => 'GET'],
        ['name' => 'settings#save',  'url' => '/api/v1/settings', 'verb' => 'PUT'],

        // Talk rooms
        ['name' => 'talk_room#index', 'url' => '/api/v1/talk/rooms', 'verb' => 'GET'],

        // Dashboard
        ['name' => 'dashboard#recent_finds', 'url' => '/api/v1/dashboard/recent-finds', 'verb' => 'GET'],
    ],
];
