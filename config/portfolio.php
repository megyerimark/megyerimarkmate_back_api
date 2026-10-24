<?php

return [
    'admin_email' => env('PORTFOLIO_ADMIN_EMAIL'),
    'frontend_url' => rtrim(env('PORTFOLIO_FRONTEND_URL', env('APP_URL', 'http://localhost:4200')), '/'),
    'mail_enabled' => filter_var(env('PORTFOLIO_MAIL_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'customer_receipt_enabled' => filter_var(env('PORTFOLIO_CUSTOMER_RECEIPT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
    'client_access_days' => max(1, (int) env('PORTFOLIO_CLIENT_ACCESS_DAYS', 90)),
];
