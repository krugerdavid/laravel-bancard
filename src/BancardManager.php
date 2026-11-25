<?php

namespace KrugerDavid\Bancard;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use KrugerDavid\Bancard\DTOs\Billing;

class BancardManager
{
    protected Client $client;
    protected string $baseUrl;
    protected string $privateKey;
    protected string $publicKey;

    public const PRODUCTION_URL = 'https://vpos.infonet.com.py';
    public const STAGING_URL = 'https://vpos.infonet.com.py:8888';

    public function __construct(array $config)
    {
        $this->baseUrl = ($config['staging'] ?? true)
            ? rtrim(self::STAGING_URL, '/')
            : rtrim(self::PRODUCTION_URL, '/');

        $this->privateKey = $config['private_key'];
        $this->publicKey = $config['public_key'];

        $this->client = new Client([
            'base_uri' => $this->baseUrl . '/',
            'timeout' => $config['timeout'] ?? 30,
        ]);
    }

    /**
     * Format amount for API: Decimal (15,2) with '.' separator.
     */
    protected function formatAmount(string|float|int $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    /**
     * v1.22: description es String(20), se muestra al usuario en el checkout.
     */
    protected function formatDescription(string $description): string
    {
        return mb_substr($description, 0, 20);
    }

    /**
     * Single Buy token: md5(private_key + shop_process_id + amount + currency)
     */
    protected function singleBuyToken(int|string $shopProcessId, string $amount, string $currency = 'PYG'): string
    {
        return md5($this->privateKey . $shopProcessId . $amount . $currency);
    }

    /**
     * Single Buy Confirm token: md5(private_key + shop_process_id + "confirm" + amount + currency)
     */
    protected function singleBuyConfirmToken(int|string $shopProcessId, string $amount, string $currency = 'PYG'): string
    {
        return md5($this->privateKey . $shopProcessId . 'confirm' . $amount . $currency);
    }

    /**
     * Get Confirmation token: md5(private_key + shop_process_id + "get_confirmation")
     */
    protected function getConfirmationToken(int|string $shopProcessId): string
    {
        return md5($this->privateKey . $shopProcessId . 'get_confirmation');
    }

    /**
     * Rollback token: md5(private_key + shop_process_id + "rollback" + "0.00")
     */
    protected function rollbackToken(int|string $shopProcessId): string
    {
        return md5($this->privateKey . $shopProcessId . 'rollback' . '0.00');
    }

    /**
     * Cards New token: md5(private_key + card_id + user_id + "request_new_card")
     */
    protected function cardsNewToken(int $cardId, int $userId): string
    {
        return md5($this->privateKey . $cardId . $userId . 'request_new_card');
    }

    /**
     * Users Cards token: md5(private_key + user_id + "request_user_cards")
     */
    protected function usersCardsToken(int $userId): string
    {
        return md5($this->privateKey . $userId . 'request_user_cards');
    }

    /**
     * Charge token: md5(private_key + shop_process_id + "charge" + amount + currency + alias_token)
     */
    protected function chargeToken(int|string $shopProcessId, string $amount, string $aliasToken, string $currency = 'PYG'): string
    {
        return md5($this->privateKey . $shopProcessId . 'charge' . $amount . $currency . $aliasToken);
    }

    /**
     * Delete Card token: md5(private_key + "delete_card" + user_id + card_token)
     */
    protected function deleteCardToken(int $userId, string $aliasToken): string
    {
        return md5($this->privateKey . 'delete_card' . $userId . $aliasToken);
    }

    /**
     * Single Buy - Initiate payment (pago ocasional).
     * Returns process_id for iframe checkout.
     *
     * @param  array{additional_data?: string, iva_amount?: string, preauthorization?: string, cancel_url?: string, extra_response_attributes?: string[], billing?: Billing}  $options
     */
    public function singleBuy(
        int $shopProcessId,
        string $amount,
        string $description,
        string $returnUrl,
        array $options = []
    ): array {
        $amountFormatted = $this->formatAmount($amount);
        $token = $this->singleBuyToken($shopProcessId, $amountFormatted);

        $operation = [
            'token' => $token,
            'shop_process_id' => $shopProcessId,
            'amount' => $amountFormatted,
            'currency' => 'PYG',
            'description' => $this->formatDescription($description),
            'return_url' => $returnUrl,
        ];

        if (isset($options['additional_data'])) {
            $operation['additional_data'] = $options['additional_data'];
        }
        if (isset($options['iva_amount'])) {
            $operation['iva_amount'] = $this->formatAmount($options['iva_amount']);
        }
        if (isset($options['preauthorization']) && $options['preauthorization'] === 'S') {
            $operation['preauthorization'] = 'S';
        }
        if (isset($options['cancel_url'])) {
            $operation['cancel_url'] = $options['cancel_url'];
        }
        if (isset($options['extra_response_attributes']) && is_array($options['extra_response_attributes'])) {
            $operation['extra_response_attributes'] = $options['extra_response_attributes'];
        }
        if (isset($options['billing']) && $options['billing'] instanceof Billing) {
            $operation['billing'] = $options['billing']->toArray();
        }

        return $this->post('vpos/api/0.3/single_buy', $operation);
    }

    /**
     * Single Buy Zimple - Initiate Zimple payment.
     */
    public function singleBuyZimple(
        int $shopProcessId,
        string $amount,
        string $description,
        string $returnUrl,
        string $userPhone,
        array $options = []
    ): array {
        $options['additional_data'] = $userPhone;
        $options['zimple'] = 'S';

        $amountFormatted = $this->formatAmount($amount);
        $token = $this->singleBuyToken($shopProcessId, $amountFormatted);

        $operation = [
            'token' => $token,
            'shop_process_id' => $shopProcessId,
            'amount' => $amountFormatted,
            'currency' => 'PYG',
            'additional_data' => $userPhone,
            'description' => $this->formatDescription($description),
            'return_url' => $returnUrl,
            'zimple' => 'S',
        ];

        if (isset($options['cancel_url'])) {
            $operation['cancel_url'] = $options['cancel_url'];
        }
        if (isset($options['billing']) && $options['billing'] instanceof Billing) {
            $operation['billing'] = $options['billing']->toArray();
        }

        return $this->post('vpos/api/0.3/single_buy', $operation);
    }

    /**
     * Single Buy Rollback - Cancel/reverse a transaction.
     */
    public function rollback(int|string $shopProcessId): array
    {
        $shopProcessId = (string) $shopProcessId;
        $token = $this->rollbackToken($shopProcessId);

        return $this->post('vpos/api/0.3/single_buy/rollback', [
            'token' => $token,
            'shop_process_id' => $shopProcessId,
        ]);
    }

    /**
     * Get Single Buy Confirmation - Query transaction status.
     */
    public function getConfirmation(int|string $shopProcessId): array
    {
        $shopProcessId = (string) $shopProcessId;
        $token = $this->getConfirmationToken($shopProcessId);

        return $this->post('vpos/api/0.3/single_buy/confirmations', [
            'token' => $token,
            'shop_process_id' => (int) $shopProcessId,
        ]);
    }

    /**
     * Cards New - Initiate card registration.
     */
    public function cardsNew(
        int $cardId,
        int $userId,
        string $userCellPhone,
        string $userMail,
        string $returnUrl
    ): array {
        $token = $this->cardsNewToken($cardId, $userId);

        return $this->post('vpos/api/0.3/cards/new', [
            'token' => $token,
            'card_id' => $cardId,
            'user_id' => $userId,
            'user_cell_phone' => $userCellPhone,
            'user_mail' => $userMail,
            'return_url' => $returnUrl,
        ]);
    }

    /**
     * Users Cards - List user's registered cards.
     *
     * @param  array{extra_response_attributes?: string[]}  $options
     */
    public function usersCards(int $userId, array $options = []): array
    {
        $token = $this->usersCardsToken($userId);

        $operation = ['token' => $token];
        if (isset($options['extra_response_attributes']) && is_array($options['extra_response_attributes'])) {
            $operation['extra_response_attributes'] = $options['extra_response_attributes'];
        }

        return $this->post("vpos/api/0.3/users/{$userId}/cards", $operation);
    }

    /**
     * Charge - Pay with token (alias_token from users_cards).
     *
     * @param  array{iva_amount?: string, number_of_payments?: int, additional_data?: string, preauthorization?: string, return_url?: string, extra_response_attributes?: string[], billing?: Billing}  $options
     */
    public function charge(
        int $shopProcessId,
        string $amount,
        string $aliasToken,
        array $options = []
    ): array {
        $amountFormatted = $this->formatAmount($amount);
        $token = $this->chargeToken($shopProcessId, $amountFormatted, $aliasToken);

        $operation = [
            'token' => $token,
            'shop_process_id' => $shopProcessId,
            'amount' => $amountFormatted,
            'currency' => 'PYG',
            'alias_token' => $aliasToken,
            'number_of_payments' => $options['number_of_payments'] ?? 1,
        ];

        if (isset($options['iva_amount'])) {
            $operation['iva_amount'] = $this->formatAmount($options['iva_amount']);
        }
        if (isset($options['additional_data'])) {
            $operation['additional_data'] = $options['additional_data'];
        }
        if (isset($options['preauthorization']) && $options['preauthorization'] === 'S') {
            $operation['preauthorization'] = 'S';
        }
        if (isset($options['return_url'])) {
            $operation['return_url'] = $options['return_url'];
        }
        if (isset($options['extra_response_attributes']) && is_array($options['extra_response_attributes'])) {
            $operation['extra_response_attributes'] = $options['extra_response_attributes'];
        }
        if (isset($options['billing']) && $options['billing'] instanceof Billing) {
            $operation['billing'] = $options['billing']->toArray();
        }

        return $this->post('vpos/api/0.3/charge', $operation);
    }

    /**
     * Delete Card - Remove a registered card.
     */
    public function deleteCard(int $userId, string $aliasToken): array
    {
        $token = $this->deleteCardToken($userId, $aliasToken);

        return $this->delete("vpos/api/0.3/users/{$userId}/cards", [
            'token' => $token,
            'alias_token' => $aliasToken,
        ]);
    }

    /**
     * Verify webhook/confirm token from Bancard.
     * Token: md5(private_key + shop_process_id + "confirm" + amount + currency)
     */
    public function verifyConfirmToken(int|string $shopProcessId, string $amount, string $token, string $currency = 'PYG'): bool
    {
        // v1.22: el token usa el monto como Decimal(15,2) con '.' (ej. "10100.00").
        $expected = $this->singleBuyConfirmToken($shopProcessId, $this->formatAmount($amount), $currency);

        return hash_equals($expected, $token);
    }

    /**
     * Get checkout script URL for iframe (bancard-checkout-4.0.0.js).
     */
    public function getCheckoutScriptUrl(): string
    {
        return $this->baseUrl . '/checkout/javascript/dist/bancard-checkout-4.0.0.js';
    }

    /**
     * Get base checkout URL.
     */
    public function getCheckoutUrl(): string
    {
        return $this->baseUrl;
    }

    protected function post(string $path, array $operation): array
    {
        $payload = [
            'public_key' => $this->publicKey,
            'operation' => $operation,
        ];

        try {
            $response = $this->client->post($path, ['json' => $payload]);
            $body = json_decode($response->getBody()->getContents(), true);

            return $body ?? [];
        } catch (GuzzleException $e) {
            Log::error('Bancard API error', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw $this->formatException($e);
        }
    }

    protected function delete(string $path, array $operation): array
    {
        $payload = [
            'public_key' => $this->publicKey,
            'operation' => $operation,
        ];

        try {
            $response = $this->client->request('DELETE', $path, ['json' => $payload]);
            $body = json_decode($response->getBody()->getContents(), true);

            return $body ?? [];
        } catch (GuzzleException $e) {
            Log::error('Bancard API error', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
            throw $this->formatException($e);
        }
    }

    protected function formatException(GuzzleException $e): \Exception
    {
        $message = $e->getMessage();
        $response = $e->getResponse();

        if ($response !== null) {
            try {
                $body = json_decode($response->getBody()->getContents(), true);
                if (isset($body['messages']) && is_array($body['messages'])) {
                    $keys = array_column($body['messages'], 'key');
                    $message = implode('; ', $keys);
                } elseif (isset($body['status'])) {
                    $message = $body['status'] . ': ' . ($body['messages'][0]['dsc'] ?? $message);
                }
            } catch (\Throwable) {
                // Fallback
            }
        }

        return new \Exception("Bancard API error: {$message}", $e->getCode(), $e);
    }
}
