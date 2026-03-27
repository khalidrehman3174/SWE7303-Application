<?php

function api_config(): array
{
    $config = [
        'stripe_secret_key' => getenv('STRIPE_SECRET_KEY') ?: '',
        'stripe_publishable_key' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
        'stripe_webhook_secret' => getenv('STRIPE_WEBHOOK_SECRET') ?: '',
        'api_allowed_origin' => getenv('API_ALLOWED_ORIGIN') ?: '',
        'default_currency' => 'GBP',
        'default_wallet_symbol' => 'GBP',
        'max_deposit_amount' => 100000,
    ];

    $localConfigFile = __DIR__ . '/config.local.php';
    if (is_file($localConfigFile)) {
        $localConfig = require $localConfigFile;
        if (is_array($localConfig)) {
            $config = array_merge($config, $localConfig);
        }
    }

    return $config;
}
