<?php

return [
    'webhook-secret' => env('GITHUB_WEBHOOK_SECRET'),
    'branch' => env('DEPLOYMENT_BRANCH', 'main'),
];
