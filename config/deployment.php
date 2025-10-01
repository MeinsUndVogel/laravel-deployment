<?php

return [
    'use-cronjob' => env('DEPLOYMENT_USE_CRONJOB', false),
    'webhook-secret' => env('GITHUB_WEBHOOK_SECRET'),
    'branch' => env('DEPLOYMENT_BRANCH', 'main'),
];
