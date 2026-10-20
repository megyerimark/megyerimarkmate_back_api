<?php

return [
    'admin_email' => env('PORTFOLIO_ADMIN_EMAIL'),
    'mail_enabled' => filter_var(env('PORTFOLIO_MAIL_ENABLED', false), FILTER_VALIDATE_BOOLEAN),
    'customer_receipt_enabled' => filter_var(env('PORTFOLIO_CUSTOMER_RECEIPT_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
];
