<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class ZaloPayGateway
{
    public const CHANNELS = ['international_card', 'domestic_card', 'account', 'zalopay_wallet', 'vietqr'];

    public function configured(): void
    {
        abort_unless(ctype_digit((string) config('zalopay.app_id')) && config('zalopay.key1') && config('zalopay.key2'), 503, 'ZaloPay is not configured.');
        foreach (['create', 'query'] as $operation) {
            abort_unless(in_array(config("zalopay.{$operation}_url"), ["https://sb-openapi.zalopay.vn/v2/$operation", "https://openapi.zalopay.vn/v2/$operation"], true), 503, 'Invalid ZaloPay endpoint configuration.');
        }
        abort_unless(parse_url(config('zalopay.create_url'), PHP_URL_HOST) === parse_url(config('zalopay.query_url'), PHP_URL_HOST), 503, 'Mismatched gateway environments.');
        abort_if(app()->isProduction() && str_contains(config('zalopay.create_url'), 'sb-openapi.'), 503, 'Sandbox payments are disabled in production.');
        abort_unless(config('zalopay.expire_seconds') >= 300 && config('zalopay.expire_seconds') <= 2592000, 503, 'Invalid payment expiry configuration.');
    }

    public static function amount(string $amount): int
    {
        abort_unless(preg_match('/^[0-9]+\.00$/D', $amount) && bccomp($amount, '0', 2) > 0, 422, 'ZaloPay requires a positive whole VND amount. Choose COD for fractional prices.');

        return (int) bcadd($amount, '0', 0);
    }

    public static function safeUrl(mixed $url): bool
    {
        if (! is_string($url) || strlen($url) > 4000) {
            return false;
        }
        $parts = parse_url($url);

        return is_array($parts) && ($parts['scheme'] ?? '') === 'https' && ! isset($parts['user']) && ! isset($parts['pass']) && (! isset($parts['port']) || $parts['port'] === 443) && preg_match('/^(?:[a-z0-9-]+\.)*zalopay\.vn$/D', strtolower($parts['host'] ?? '')) === 1;
    }

    /** @return array<string, int|string> */
    public function payload(Payment $payment): array
    {
        $embed = json_encode(['redirecturl' => config('zalopay.redirect_url').'?order='.$payment->order_id, 'preferred_payment_method' => $payment->preferred_payment_method ?? []], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $data = ['app_id' => $payment->gateway_app_id, 'app_user' => 'buyer-'.$payment->order->buyer_id, 'app_trans_id' => $payment->transaction_reference, 'app_time' => $payment->gateway_app_time, 'amount' => self::amount($payment->amount), 'embed_data' => $embed, 'item' => '[]', 'bank_code' => '', 'description' => 'Thanh toan '.$payment->order->order_code, 'expire_duration_seconds' => config('zalopay.expire_seconds')];
        $data['mac'] = hash_hmac('sha256', implode('|', array_map(fn (string $key): string => (string) $data[$key], ['app_id', 'app_trans_id', 'app_user', 'amount', 'app_time', 'embed_data', 'item'])), config('zalopay.key1'));
        if (config('zalopay.callback_url')) {
            $data['callback_url'] = config('zalopay.callback_url');
        }

        return $data;
    }

    public function create(Payment $payment): ?array
    {
        $this->configured();

        return $this->send('create', $this->payload($payment));
    }

    public function query(Payment $payment): ?array
    {
        $this->configured();
        abort_unless($payment->gateway_app_id === (int) config('zalopay.app_id'), 503, 'Payment belongs to a different merchant configuration.');
        $data = ['app_id' => $payment->gateway_app_id, 'app_trans_id' => $payment->transaction_reference];
        $data['mac'] = hash_hmac('sha256', $data['app_id'].'|'.$data['app_trans_id'].'|'.config('zalopay.key1'), config('zalopay.key1'));

        return $this->send('query', $data);
    }

    private function send(string $operation, array $data): ?array
    {
        try {
            $response = Http::asForm()->connectTimeout(5)->timeout(15)->withoutRedirecting()->post(config("zalopay.{$operation}_url"), $data);
            $body = $response->successful() ? $response->json() : null;

            return is_array($body) ? $body : null;
        } catch (ConnectionException) {
            return null;
        }
    }

    public function callbackData(array $input): ?array
    {
        if (! config('zalopay.key2') || ($input['type'] ?? null) !== 1 || ! is_string($input['data'] ?? null) || ! is_string($input['mac'] ?? null)) {
            return null;
        }
        $mac = hash_hmac('sha256', $input['data'], config('zalopay.key2'));
        if (! hash_equals($mac, strtolower($input['mac']))) {
            return null;
        }
        $data = json_decode($input['data'], true, 32, JSON_BIGINT_AS_STRING);

        return is_array($data) ? $data : null;
    }
}
