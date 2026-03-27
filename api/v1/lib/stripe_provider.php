<?php

function stripe_provider_create_payment_intent(array $config, string $depositId, int $userId, float $amount): array
{
    if (empty($config['stripe_secret_key'])) {
        return [
            'ok' => true,
            'provider' => 'sandbox',
            'external_reference' => null,
            'client_secret' => null,
            'mode' => 'mock',
        ];
    }

    try {
        \Stripe\Stripe::setApiKey($config['stripe_secret_key']);

        $intent = \Stripe\PaymentIntent::create([
            'amount' => (int)round($amount * 100),
            'currency' => strtolower($config['default_currency']),
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => [
                'deposit_id' => $depositId,
                'user_id' => (string)$userId,
            ],
        ]);

        return [
            'ok' => true,
            'provider' => 'stripe',
            'external_reference' => $intent->id,
            'client_secret' => $intent->client_secret,
            'mode' => 'stripe',
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'error' => $e->getMessage(),
        ];
    }
}

function stripe_provider_parse_webhook(string $payload, ?string $signature, array $config)
{
    if (empty($config['stripe_webhook_secret'])) {
        throw new RuntimeException('Missing STRIPE_WEBHOOK_SECRET');
    }

    if (empty($signature)) {
        throw new RuntimeException('Missing Stripe signature header');
    }

    return \Stripe\Webhook::constructEvent($payload, $signature, $config['stripe_webhook_secret']);
}
