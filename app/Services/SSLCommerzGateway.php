<?php

namespace Helpyard\App\Services;

use Helpyard\App\Core\PaymentException;
use JsonException;
use RuntimeException;

class SSLCommerzGateway
{
    private string $gatewayHost;

    public function __construct(private array $config)
    {
        $this->gatewayHost = ($config['sandbox'] ?? true)
            ? 'sandbox.sslcommerz.com'
            : 'securepay.sslcommerz.com';
    }

    public function enabled(): bool
    {
        return ($this->config['enabled'] ?? false) === true
            && is_string($this->config['store_id'] ?? null)
            && $this->config['store_id'] !== ''
            && is_string($this->config['store_password'] ?? null)
            && $this->config['store_password'] !== '';
    }

    public function initiate(array $payment): array
    {
        if (!$this->enabled()) {
            throw new PaymentException('Online payment is not configured yet.');
        }

        $amount = self::normalizedAmount($payment['amount'] ?? null);
        if ($amount === null || (float) $amount < 10 || (float) $amount > 500000) {
            throw new PaymentException('The order amount must be between 10.00 and 500000.00 BDT for online payment.');
        }

        $baseUrl = $this->callbackBaseUrl();
        $address = $payment['address'];
        $items = $payment['items'];
        $customerEmail = $payment['customer_email'] ?? '';
        if (!is_string($customerEmail) || strlen($customerEmail) > 50
            || filter_var($customerEmail, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new PaymentException('The customer email must be valid and 50 characters or fewer for online payment.');
        }
        $fields = [
            'store_id' => $this->config['store_id'],
            'store_passwd' => $this->config['store_password'],
            'total_amount' => $amount,
            'currency' => 'BDT',
            'tran_id' => $payment['transaction_id'],
            'success_url' => $baseUrl . '/payments/return/success',
            'fail_url' => $baseUrl . '/payments/return/fail',
            'cancel_url' => $baseUrl . '/payments/return/cancel',
            'ipn_url' => $baseUrl . '/payments/ipn',
            'product_category' => 'ecommerce',
            'product_name' => self::safeText($items[0]['product_name'] ?? 'Helpyard order', 240),
            'product_profile' => 'general',
            'shipping_method' => 'YES',
            'num_of_item' => (string) array_sum(array_column($items, 'quantity')),
            'cus_name' => self::safeText($address['full_name'], 50),
            'cus_email' => $customerEmail,
            'cus_add1' => self::safeText($address['address_line_1'], 50),
            'cus_city' => self::safeText($address['city'], 50),
            'cus_country' => self::safeText($address['country'], 50),
            'cus_phone' => self::safeText($address['phone'], 20),
            'ship_name' => self::safeText($address['full_name'], 50),
            'ship_add1' => self::safeText($address['address_line_1'], 50),
            'ship_city' => self::safeText($address['city'], 50),
            'ship_country' => self::safeText($address['country'], 50),
            'ship_postcode' => self::safeText($address['postal_code'] ?? '', 20),
        ];
        $response = $this->request(
            'POST',
            'https://' . $this->gatewayHost . '/gwprocess/v4/api.php',
            http_build_query($fields, '', '&', PHP_QUERY_RFC1738),
            'application/x-www-form-urlencoded'
        );

        if (($response['status'] ?? null) !== 'SUCCESS'
            || !is_string($response['sessionkey'] ?? null)
            || $response['sessionkey'] === ''
            || !self::isTrustedGatewayUrl($response['GatewayPageURL'] ?? null, $this->gatewayHost)
        ) {
            throw new PaymentException('The payment provider did not return a valid checkout session.');
        }

        return [
            'session_key' => $response['sessionkey'],
            'redirect_url' => $response['GatewayPageURL'],
        ];
    }

    public function validate(string $validationId): array
    {
        if (!$this->enabled()) {
            throw new PaymentException('Online payment is not configured yet.');
        }

        $query = http_build_query([
            'val_id' => $validationId,
            'store_id' => $this->config['store_id'],
            'store_passwd' => $this->config['store_password'],
            'format' => 'json',
        ], '', '&', PHP_QUERY_RFC3986);
        $result = $this->request(
            'GET',
            'https://' . $this->gatewayHost . '/validator/api/validationserverAPI.php?' . $query,
            '',
            'application/json'
        );
        $status = strtoupper((string) ($result['status'] ?? ''));
        if (!in_array($status, ['VALID', 'VALIDATED'], true)) {
            throw new PaymentException('The payment provider has not verified this transaction.');
        }

        return $result;
    }

    public static function isTrustedGatewayUrl(mixed $url, string $gatewayHost): bool
    {
        if (!is_string($url) || strlen($url) > 2048) {
            return false;
        }
        $parts = parse_url($url);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && strtolower($parts['host'] ?? '') === strtolower($gatewayHost)
            && !isset($parts['user'])
            && !isset($parts['pass']);
    }

    public static function normalizedAmount(mixed $amount): ?string
    {
        if (!is_string($amount) && !is_int($amount) && !is_float($amount)) {
            return null;
        }
        $value = (string) $amount;
        if (!preg_match('/^(0|[1-9][0-9]{0,5})(?:\.([0-9]{1,2}))?$/', $value, $parts)) {
            return null;
        }

        return $parts[1] . '.' . str_pad($parts[2] ?? '', 2, '0');
    }

    private function callbackBaseUrl(): string
    {
        $url = $this->config['app_url'] ?? '';
        $parts = is_string($url) ? parse_url($url) : false;
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || (($this->config['sandbox'] ?? true) === false && strtolower($parts['scheme']) !== 'https')
        ) {
            throw new PaymentException('Set APP_URL to the public HTTPS storefront URL before enabling live payments.');
        }

        return rtrim($url, '/');
    }

    private function request(string $method, string $url, string $content, string $contentType): array
    {
        $headers = ['Accept: application/json'];
        if ($method === 'POST') {
            $headers[] = 'Content-Type: ' . $contentType;
            $headers[] = 'Content-Length: ' . strlen($content);
        }
        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'content' => $content,
                'timeout' => 20,
                'ignore_errors' => true,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
            ],
        ]);
        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new RuntimeException('Could not connect to the configured payment provider.');
        }
        $statusLine = $http_response_header[0] ?? '';
        if (!preg_match('/^HTTP\/\S+\s+2\d\d(?:\s|$)/', $statusLine)) {
            throw new RuntimeException('The payment provider returned an unsuccessful HTTP response.');
        }

        try {
            $response = json_decode($responseBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('The payment provider returned an unreadable response.', 0, $exception);
        }
        if (!is_array($response)) {
            throw new RuntimeException('The payment provider returned an invalid response.');
        }

        return $response;
    }

    private static function safeText(mixed $value, int $limit): string
    {
        if (!is_string($value)) {
            return '';
        }
        $value = preg_replace('/[\x00-\x1F\x7F]/', ' ', $value) ?? '';
        $value = trim($value);

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $limit);
        }

        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
        return $characters === false ? '' : implode('', array_slice($characters, 0, $limit));
    }
}
