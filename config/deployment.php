<?php

return [
    'webhook-secret' => env('GITHUB_WEBHOOK_SECRET'),
    'branch' => env('GITHUB_BRANCH', 'main'),
];
