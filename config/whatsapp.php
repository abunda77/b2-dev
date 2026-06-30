<?php

return [
    'auth' => env('WHATSAPP_AUTH', ''),
    'ip' => env('WHATSAPP_IP', '127.0.0.1'),
    'port' => env('WHATSAPP_PORT', '3000'),
    'device_id' => env('WHATSAPP_DEVICE_ID', ''),
    'action' => env('WHATSAPP_ACTION', 'stop'),
    'duration' => env('WHATSAPP_DURATION', 86400),
];
