<?php
declare(strict_types=1);

namespace CRM\Integration;

use InvalidArgumentException;
use RuntimeException;

class ProviderUnavailable extends RuntimeException {}
class InvalidNotification extends RuntimeException {}

interface HttpTransport
{
    /** @return array{status:int,body:string} Never retry a mutation automatically. */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): array;
}

final class CurlTransport implements HttpTransport
{
    public function __construct(private array $allowedHosts, private int $timeoutSeconds = 28) {}

    public function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || !in_array(strtolower($parts['host'] ?? ''), $this->allowedHosts, true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw new InvalidArgumentException('The provider URL is not permitted.');
        }
        if (!function_exists('curl_init')) throw new ProviderUnavailable('PHP cURL is required.');
        $handle = curl_init($url);
        $lines = [];
        foreach ($headers as $key => $value) {
            if (preg_match('/[\r\n]/', $key . $value)) throw new InvalidArgumentException('Invalid HTTP header.');
            $lines[] = $key . ': ' . $value;
        }
        $received = '';
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $lines,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$received): int {
                if (strlen($received) + strlen($chunk) > 2 * 1024 * 1024) return 0;
                $received .= $chunk;
                return strlen($chunk);
            },
        ]);
        if ($body !== null) curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        $ok = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
        if ($ok === false) throw new ProviderUnavailable('Provider request failed or timed out; reconcile before retrying a booking or payment.');
        return ['status' => $status, 'body' => $received];
    }
}

final class Money
{
    public static function cents(string $amount): int
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,10})(?:\.([0-9]{1,2}))?$/D', $amount, $m)) {
            throw new InvalidArgumentException('Expected a non-negative ZAR decimal with at most two places.');
        }
        return (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    }
    public static function decimal(int $cents): string
    {
        if ($cents < 0) throw new InvalidArgumentException('Amount must not be negative.');
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}

final class ExactJson
{
    /** Preserve JSON decimal tokens as strings before PHP can turn money into binary floats. */
    public static function decode(string $json): array
    {
        // Validate the original JSON first so malformed tokens cannot be repaired by replacement.
        json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        $preserved = preg_replace_callback('/"(?:[^"\\\\]|\\\\.)*"|(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/s',
            static fn(array $match): string => isset($match[1]) && strpbrk($match[1], '.eE') !== false
                ? '"' . $match[1] . '"' : $match[0], $json);
        $result = json_decode($preserved ?? '', true, 64, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        if (!is_array($result)) throw new InvalidArgumentException('Expected a JSON object or array.');
        return $result;
    }
}

final class PayFast
{
    public function __construct(
        private string $merchantId,
        private string $merchantKey,
        private string $passphrase,
        private bool $sandbox,
        private HttpTransport $http
    ) {}

    public function configured(): bool { return $this->merchantId !== '' && $this->merchantKey !== '' && $this->passphrase !== ''; }
    private function host(): string { return $this->sandbox ? 'sandbox.payfast.co.za' : 'www.payfast.co.za'; }

    /** Order of fields is significant for PayFast signatures. */
    public static function parameterString(array $fields, bool $includeEmpty = false): string
    {
        $pairs = [];
        foreach ($fields as $key => $value) {
            if ($key === 'signature') continue;
            if (!is_scalar($value)) throw new InvalidArgumentException('Only scalar PayFast fields are allowed.');
            if ($includeEmpty || (string) $value !== '') $pairs[] = $key . '=' . urlencode(trim((string) $value));
        }
        return implode('&', $pairs);
    }
    public static function signature(array $fields, string $passphrase, bool $includeEmpty = false): string
    {
        $encoded = self::parameterString($fields, $includeEmpty);
        if ($passphrase !== '') $encoded .= '&passphrase=' . urlencode($passphrase);
        return md5($encoded);
    }

    /** Only pass the immutable database order, never browser totals. */
    public function checkout(array $order, array $urls): array
    {
        if (!$this->configured()) throw new ProviderUnavailable('PayFast credentials and passphrase are required.');
        if (($order['role'] ?? '') === 'approved_dealer' || ($order['role'] ?? '') === 'dealer') throw new InvalidArgumentException('Dealer orders require EFT.');
        if (($order['payment_method'] ?? 'payfast') !== 'payfast') throw new InvalidArgumentException('This order was not created for PayFast.');
        if ((int) ($order['total_cents'] ?? 0) < 500) throw new InvalidArgumentException('The PayFast amount must be at least R5.00.');
        foreach (['return_url', 'cancel_url', 'notify_url'] as $name) {
            $p = parse_url($urls[$name] ?? '');
            if (!$p || ($p['scheme'] ?? '') !== 'https' || empty($p['host']) || isset($p['user']) || isset($p['pass'])) {
                throw new InvalidArgumentException('PayFast callback URLs must be configured HTTPS URLs.');
            }
        }
        $fields = ['merchant_id' => $this->merchantId, 'merchant_key' => $this->merchantKey,
            'return_url' => $urls['return_url'], 'cancel_url' => $urls['cancel_url'], 'notify_url' => $urls['notify_url'],
            'email_address' => (string) ($order['email'] ?? ''), 'm_payment_id' => (string) $order['id'],
            'amount' => Money::decimal((int) $order['total_cents']), 'item_name' => 'CRM Technology order ' . $order['id']];
        $fields['signature'] = self::signature($fields, $this->passphrase);
        return ['action' => 'https://' . $this->host() . '/eng/process', 'fields' => $fields];
    }

    /** Use REMOTE_ADDR, not an untrusted forwarded header. Empty DNS results must fail closed. */
    public static function trustedIps(bool $sandbox): array
    {
        $hosts = $sandbox ? ['sandbox.payfast.co.za'] : ['www.payfast.co.za', 'w1w.payfast.co.za', 'w2w.payfast.co.za'];
        $ips = [];
        foreach ($hosts as $host) {
            foreach (gethostbynamel($host) ?: [] as $ip) $ips[] = $ip;
            foreach (@dns_get_record($host, DNS_AAAA) ?: [] as $entry) if (isset($entry['ipv6'])) $ips[] = $entry['ipv6'];
        }
        return array_values(array_unique($ips));
    }

    public static function parseItn(string $rawBody): array
    {
        if ($rawBody === '' || strlen($rawBody) > 65536) throw new InvalidNotification('Invalid payment notification.');
        $fields = [];
        foreach (explode('&', $rawBody) as $part) {
            if (!str_contains($part, '=')) throw new InvalidNotification('Invalid form encoding.');
            [$key, $value] = explode('=', $part, 2);
            $key = urldecode($key);
            if (!preg_match('/^[a-zA-Z0-9_]+$/D', $key) || array_key_exists($key, $fields)) throw new InvalidNotification('Repeated or invalid payment field.');
            $fields[$key] = urldecode($value);
        }
        return $fields;
    }

    /** Pure verification. The caller MUST persist/dedupe the returned event in a database transaction. */
    public function validateItn(string $rawBody, string $remoteIp, array $order, array $trustedIps): array
    {
        if (!$this->configured()) throw new ProviderUnavailable('PayFast is not configured.');
        $packed = @inet_pton($remoteIp);
        $allowed = array_filter(array_map(static fn(string $ip) => @inet_pton($ip), $trustedIps));
        if ($packed === false || !in_array($packed, $allowed, true)) throw new InvalidNotification('Untrusted payment notification source.');
        $fields = self::parseItn($rawBody);
        if (!isset($fields['signature']) || !hash_equals(self::signature($fields, $this->passphrase, true), $fields['signature'])) throw new InvalidNotification('Invalid payment signature.');
        if (($fields['merchant_id'] ?? '') !== $this->merchantId || ($fields['m_payment_id'] ?? '') !== (string) $order['id']) throw new InvalidNotification('Payment account or order mismatch.');
        if (($order['payment_method'] ?? '') !== 'payfast' || in_array($order['role'] ?? '', ['dealer', 'approved_dealer'], true)) throw new InvalidNotification('This order cannot accept a card payment.');
        try { $amount = Money::cents($fields['amount_gross'] ?? ''); }
        catch (InvalidArgumentException) { throw new InvalidNotification('Invalid payment amount.'); }
        if ($amount !== (int) ($order['total_cents'] ?? -1)) throw new InvalidNotification('Payment amount mismatch.');
        if (($fields['payment_status'] ?? '') !== 'COMPLETE') throw new InvalidNotification('Payment is not complete.');
        if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $fields['pf_payment_id'] ?? '')) throw new InvalidNotification('Missing payment reference.');
        $response = $this->http->request('POST', 'https://' . $this->host() . '/eng/query/validate',
            ['Content-Type' => 'application/x-www-form-urlencoded'], self::parameterString($fields, true));
        if ($response['status'] !== 200 || trim($response['body']) !== 'VALID') throw new InvalidNotification('Provider did not validate payment.');
        return ['provider' => 'payfast', 'provider_id' => $fields['pf_payment_id'], 'order_id' => (int) $order['id'],
            'amount_cents' => $amount, 'status' => 'complete', 'payload_sha256' => hash('sha256', $rawBody)];
    }
}

interface ShippingProvider
{
    /** JSON must be constructed on the server from validated dimensions, addresses and selected rate. */
    public function rates(string $requestJson): array;
    public function book(string $requestJson): array;
    public function tracking(string $reference): array;
}

final class BobGo implements ShippingProvider
{
    public function __construct(private string $key, private bool $sandbox, private HttpTransport $http) {}
    private function call(string $method, string $path, ?string $json = null): array
    {
        if ($this->key === '') throw new ProviderUnavailable('Bob Go API credentials are pending.');
        $host = $this->sandbox ? 'api.sandbox.bobgo.co.za' : 'api.bobgo.co.za';
        $response = $this->http->request($method, 'https://' . $host . '/v2' . $path,
            ['Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $this->key], $json);
        if ($response['status'] < 200 || $response['status'] >= 300) throw new ProviderUnavailable('Bob Go request failed. HTTP ' . $response['status']);
        return ExactJson::decode($response['body']);
    }
    private function validateRequest(string $json, bool $booking = false): array
    {
        $request = ExactJson::decode($json);
        foreach (['collection_address', 'delivery_address'] as $key) {
            foreach (['street_address', 'local_area', 'city', 'zone', 'country', 'code'] as $field) {
                if (!is_string($request[$key][$field] ?? null) || trim($request[$key][$field]) === '') throw new InvalidArgumentException('A complete verified shipping address is required.');
            }
        }
        if (empty($request['parcels']) || !is_array($request['parcels'])) throw new InvalidArgumentException('Measured parcels are required.');
        foreach ($request['parcels'] as $parcel) foreach (['submitted_length_cm', 'submitted_width_cm', 'submitted_height_cm', 'submitted_weight_kg'] as $field) {
            $value = $parcel[$field] ?? null;
            if ((!is_int($value) && !is_string($value)) || !is_numeric($value) || $value <= 0) throw new InvalidArgumentException('Positive measured parcel dimensions and weight are required.');
        }
        if ($booking) foreach (['provider_slug', 'service_level_code', 'collection_contact_name', 'delivery_contact_name', 'custom_order_number', 'selected_rate_request_id'] as $field) {
            if (empty($request[$field])) throw new InvalidArgumentException('A selected quote and unique order reference are required to book.');
        }
        return $request;
    }
    public function rates(string $requestJson): array
    {
        $request = $this->validateRequest($requestJson);
        if (!isset($request['timeout']) || !is_int($request['timeout']) || $request['timeout'] < 5001 || $request['timeout'] > 25000) {
            throw new InvalidArgumentException('Set an explicit rate timeout from 5001 to 25000 milliseconds.');
        }
        return $this->call('POST', '/rates', $requestJson);
    }
    /** Caller takes a unique shipment lock first, persists result, and reconciles ambiguous timeouts before retry. */
    public function book(string $requestJson): array
    {
        $this->validateRequest($requestJson, true);
        return $this->call('POST', '/shipments', $requestJson);
    }
    public function tracking(string $reference): array
    {
        return $this->call('GET', '/tracking?' . http_build_query(['tracking_reference' => self::reference($reference)]));
    }
    public function waybill(string $reference): array
    {
        return $this->call('GET', '/shipments/waybill?' . http_build_query(['tracking_references' => json_encode([self::reference($reference)], JSON_THROW_ON_ERROR)]));
    }
    private static function reference(string $value): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $value)) throw new InvalidArgumentException('Invalid tracking reference.');
        return $value;
    }
}

/** An unavailable contract never produces fake rates or successful bookings. */
final class PendingShippingProvider implements ShippingProvider
{
    public function __construct(private string $provider, private string $reason) {}
    public function rates(string $requestJson): array { throw new ProviderUnavailable($this->provider . ': ' . $this->reason); }
    public function book(string $requestJson): array { throw new ProviderUnavailable($this->provider . ': ' . $this->reason); }
    public function tracking(string $reference): array { throw new ProviderUnavailable($this->provider . ': ' . $this->reason); }
}

interface InventorySource
{
    /** Normalized rows: sku, price_cents, available, source_updated_at. Empty/error is never zero stock. */
    public function inventory(): array;
}

/** The connector is injected only after the installed Fincon version's method contract is verified. */
final class FinconMappedSource implements InventorySource
{
    public function __construct(private ?\Closure $fetch, private array $fieldMap) {}
    public function inventory(): array
    {
        if (!$this->fetch) throw new ProviderUnavailable('Fincon version, API access and field mapping are pending.');
        $rows = ($this->fetch)();
        if (!is_array($rows) || count($rows) === 0) throw new ProviderUnavailable('Fincon returned no validated inventory; preserve the previous snapshot.');
        return $this->normalize($rows);
    }
    public function normalize(array $rows): array
    {
        foreach (['sku', 'price', 'available', 'updated_at'] as $field) if (empty($this->fieldMap[$field])) throw new InvalidArgumentException('Fincon field mapping is incomplete.');
        $result = []; $seen = [];
        foreach ($rows as $row) {
            $sku = $row[$this->fieldMap['sku']] ?? null;
            $price = $row[$this->fieldMap['price']] ?? null;
            $available = $row[$this->fieldMap['available']] ?? null;
            $updated = $row[$this->fieldMap['updated_at']] ?? null;
            if (!is_string($sku) || $sku === '' || strlen($sku) > 100 || isset($seen[$sku]) || (!is_string($price) && !is_int($price))
                || !is_int($available) || $available < 0 || !is_string($updated) || strtotime($updated) === false) {
                throw new ProviderUnavailable('Invalid or duplicate Fincon inventory row; the batch must not replace valid stock.');
            }
            $seen[$sku] = true;
            $result[] = ['sku' => $sku, 'price_cents' => Money::cents((string) $price), 'available' => $available, 'source_updated_at' => $updated];
        }
        return $result;
    }
}

final class BrevoEmail
{
    public function __construct(private string $apiKey, private string $sender, private HttpTransport $http) {}
    /** Exactly one recipient; caller's outbox enforces purpose, consent, suppression, dedupe and retention. */
    public function send(string $to, string $subject, string $text, string $outboxId): string
    {
        if ($this->apiKey === '' || !filter_var($this->sender, FILTER_VALIDATE_EMAIL)) throw new ProviderUnavailable('Verified email provider configuration is pending.');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || strlen($subject) > 200 || preg_match('/[\r\n]/', $subject) || $text === '' || strlen($text) > 100000
            || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D', $outboxId)) throw new InvalidArgumentException('Invalid email payload.');
        $response = $this->http->request('POST', 'https://api.brevo.com/v3/smtp/email', ['api-key' => $this->apiKey, 'Content-Type' => 'application/json'],
            json_encode(['sender' => ['email' => $this->sender], 'to' => [['email' => $to]], 'subject' => $subject, 'textContent' => $text,
                'headers' => ['IdempotencyKey' => $outboxId]], JSON_THROW_ON_ERROR));
        if ($response['status'] !== 201) throw new ProviderUnavailable('Email provider did not accept the message. HTTP ' . $response['status']);
        $data = ExactJson::decode($response['body']);
        if (empty($data['messageId'])) throw new ProviderUnavailable('Email provider omitted the delivery reference.');
        return (string) $data['messageId'];
    }
}

final class CatalogAssistant
{
    /** retrieve must enforce catalog visibility and current pricing; optional rank returns only product IDs. */
    public function __construct(private \Closure $retrieve, private ?\Closure $rank = null) {}
    public function answer(string $message): array
    {
        if ($message === '' || strlen($message) > 1000) throw new InvalidArgumentException('Enter a question up to 1000 characters.');
        $products = array_slice(($this->retrieve)($message), 0, 12);
        $mode = 'catalog_search';
        if ($this->rank && $products) {
            try {
                $ids = ($this->rank)($message, array_map(static fn($p) => ['id' => $p['id'], 'name' => $p['name'], 'specs' => $p['specs'] ?? []], $products));
                if (is_array($ids)) {
                    $ranked = [];
                    foreach (array_slice(array_unique($ids), 0, 4) as $id) foreach ($products as $p) if ((string)$p['id'] === (string)$id) $ranked[] = $p;
                    if ($ranked) { $products = $ranked; $mode = 'ai_ranked_catalog'; }
                }
            } catch (\Throwable) { /* The catalog remains useful if inference is unavailable. */ }
        }
        return ['mode' => $mode, 'message' => $products ? 'Here are matching catalog items. Check each product for its recorded specifications and availability.' : 'I could not verify a matching product. Our support team can help with your requirements.',
            'products' => array_slice($products, 0, 4), 'support_url' => '/support'];
    }
}
