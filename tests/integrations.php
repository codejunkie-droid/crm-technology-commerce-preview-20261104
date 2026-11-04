<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/Integrations.php';

use CRM\Integration\{HttpTransport, PayFast, Money, ExactJson, BobGo, ProviderUnavailable, InvalidNotification, PendingShippingProvider, FinconMappedSource, BrevoEmail, CatalogAssistant, CurlTransport};

final class FixtureTransport implements HttpTransport {
    public array $requests = [];
    public function __construct(public array $response = ['status' => 200, 'body' => 'VALID']) {}
    public function request(string $method, string $url, array $headers = [], ?string $body = null): array {
        $this->requests[] = compact('method', 'url', 'headers', 'body'); return $this->response;
    }
}
$passed = 0;
function check(bool $condition, string $name): void { global $passed; if (!$condition) throw new RuntimeException('FAIL ' . $name); ++$passed; echo "PASS $name\n"; }
function rejects(callable $call, string $class, string $name): void {
    try { $call(); } catch (Throwable $error) { check($error instanceof $class, $name); return; }
    throw new RuntimeException('FAIL did not reject: ' . $name);
}

check(Money::cents('19999.99') === 1999999 && Money::decimal(1) === '0.01', 'Exact cents conversion');
rejects(fn() => Money::cents('1.999'), InvalidArgumentException::class, 'Sub-cent price rejected');
rejects(fn() => Money::cents('1e4'), InvalidArgumentException::class, 'Exponential money rejected');
check(ExactJson::decode('{"amount":123.45,"value":"123.45","id":3,"nested":[0.01]}') === ['amount'=>'123.45','value'=>'123.45','id'=>3,'nested'=>['0.01']], 'Provider decimals preserved as strings');
rejects(fn() => ExactJson::decode('{"amount": 1.2.3}'), JsonException::class, 'Malformed provider JSON rejected');

$http = new FixtureTransport();
$payfast = new PayFast('merchant-fixture', 'key-fixture', 'phrase-fixture', true, $http);
$order = ['id' => 7, 'total_cents' => 1999999, 'payment_method' => 'payfast', 'role' => 'customer', 'email' => 'test@example.invalid'];
$fields = ['m_payment_id'=>'7','pf_payment_id'=>'PF-FIXTURE-1','payment_status'=>'COMPLETE','amount_gross'=>'19999.99','merchant_id'=>'merchant-fixture'];
$body = static function(array $payload): string { $payload['signature'] = PayFast::signature($payload, 'phrase-fixture', true); return http_build_query($payload); };
$good = $body($fields);
$event = $payfast->validateItn($good, '192.0.2.1', $order, ['192.0.2.1']);
check($event['amount_cents'] === 1999999 && $event['provider_id'] === 'PF-FIXTURE-1' && $event['order_id'] === 7, 'Valid fixture ITN yields verified event');
check($http->requests[0]['url'] === 'https://sandbox.payfast.co.za/eng/query/validate', 'ITN validates with official sandbox endpoint');
check($payfast->validateItn($good, '192.0.2.1', $order, ['192.0.2.1']) === $event, 'Repeated verified event retains same deduplication key');
rejects(fn() => $payfast->validateItn($good, '192.0.2.2', $order, ['192.0.2.1']), InvalidNotification::class, 'Invalid ITN source rejected');
rejects(fn() => $payfast->validateItn($good, '192.0.2.1', $order, []), InvalidNotification::class, 'DNS failure fails closed');
rejects(fn() => $payfast->validateItn(str_replace('PF-FIXTURE-1','PF-FIXTURE-2',$good),'192.0.2.1',$order,['192.0.2.1']), InvalidNotification::class,'Tampered signature rejected');
rejects(fn() => $payfast->validateItn($body(array_replace($fields,['amount_gross'=>'1.00'])),'192.0.2.1',$order,['192.0.2.1']), InvalidNotification::class,'Amount mismatch rejected');
rejects(fn() => $payfast->validateItn($body(array_replace($fields,['payment_status'=>'CANCELLED'])),'192.0.2.1',$order,['192.0.2.1']), InvalidNotification::class,'Cancelled payment cannot mark paid');
rejects(fn() => $payfast->validateItn($body(array_replace($fields,['m_payment_id'=>'8'])),'192.0.2.1',$order,['192.0.2.1']), InvalidNotification::class,'Wrong order rejected');
rejects(fn() => $payfast->validateItn($good,'192.0.2.1',array_replace($order,['role'=>'approved_dealer']),['192.0.2.1']), InvalidNotification::class,'Dealer card callback denied');
rejects(fn() => $payfast->validateItn($good,'192.0.2.1',array_replace($order,['payment_method'=>'eft']),['192.0.2.1']), InvalidNotification::class,'EFT order cannot accept PayFast event');
rejects(fn() => PayFast::parseItn($good . '&m_payment_id=8'), InvalidNotification::class, 'Duplicate form field rejected');
check($payfast->validateItn($body(array_replace($fields,['name_last'=>''])),'192.0.2.1',$order,['192.0.2.1'])['order_id'] === 7, 'Empty ITN values preserved in signature and server validation');
$http->response = ['status' => 200, 'body' => 'INVALID'];
rejects(fn() => $payfast->validateItn($good,'192.0.2.1',$order,['192.0.2.1']), InvalidNotification::class,'Provider validation failure rejected');
$checkout = $payfast->checkout($order,['return_url'=>'https://example.invalid/return','cancel_url'=>'https://example.invalid/cancel','notify_url'=>'https://example.invalid/notify']);
check($checkout['fields']['amount'] === '19999.99' && $checkout['action'] === 'https://sandbox.payfast.co.za/eng/process', 'Hosted checkout uses exact database amount');
rejects(fn() => $payfast->checkout(array_replace($order,['role'=>'approved_dealer']),[]), InvalidArgumentException::class,'Dealer hosted checkout denied');
rejects(fn() => (new PayFast('','','',true,$http))->checkout($order,[]), ProviderUnavailable::class,'Missing payment credentials unavailable');

$shipmentHttp = new FixtureTransport(['status'=>200,'body'=>'{"id":9,"provider_rate_requests":[{"status":"success","responses":[{"rate_amount":89.99}]}]}']);
$bobgo = new BobGo('fixture-only', true, $shipmentHttp);
$address = ['street_address'=>'Fixture street','local_area'=>'Fixture suburb','city'=>'Fixture city','zone'=>'Fixture province','country'=>'ZA','code'=>'0000'];
$rateRequest = ['collection_address'=>$address,'delivery_address'=>$address,'parcels'=>[['submitted_length_cm'=>30,'submitted_width_cm'=>20,'submitted_height_cm'=>10,'submitted_weight_kg'=>2]],'timeout'=>25000];
$rate = $bobgo->rates(json_encode($rateRequest, JSON_THROW_ON_ERROR));
check($rate['provider_rate_requests'][0]['responses'][0]['rate_amount'] === '89.99', 'Shipping prices never pass through float arithmetic');
rejects(fn() => $bobgo->rates('{}'), InvalidArgumentException::class,'Incomplete shipment is rejected');
$shipmentHttp->response = ['status'=>503,'body'=>'{}'];
rejects(fn() => $bobgo->rates(json_encode($rateRequest, JSON_THROW_ON_ERROR)), ProviderUnavailable::class,'Courier outage is not a zero-price quote');
rejects(fn() => (new PendingShippingProvider('Pargo','Account and contract verification pending'))->rates('{}'), ProviderUnavailable::class,'Pending carrier fails honestly');

$map = ['sku'=>'Item','price'=>'Retail','available'=>'Available','updated_at'=>'Updated'];
$fincon = new FinconMappedSource(fn() => [['Item'=>'FIXTURE-1','Retail'=>'99.99','Available'=>2,'Updated'=>'2026-11-04T10:00:00Z']], $map);
check($fincon->inventory()[0]['price_cents'] === 9999, 'Fincon mapped snapshot uses exact decimal');
rejects(fn() => (new FinconMappedSource(fn() => [],$map))->inventory(), ProviderUnavailable::class,'Empty Fincon sync preserves existing snapshot');
rejects(fn() => (new FinconMappedSource(null,$map))->inventory(), ProviderUnavailable::class,'Fincon credentials pending fails closed');
rejects(fn() => $fincon->normalize([['Item'=>'FIXTURE-1','Retail'=>1.5,'Available'=>2,'Updated'=>'2026-11-04']]), ProviderUnavailable::class,'Float-valued Fincon prices rejected');

$mailHttp = new FixtureTransport(['status'=>201,'body'=>'{"messageId":"fixture-delivery"}']);
$mail = new BrevoEmail('fixture-only','sender@example.invalid',$mailHttp);
check($mail->send('buyer@example.invalid','Fixture subject','Fixture text','outbox-1') === 'fixture-delivery','Email acceptance returns reference');
$payload = json_decode($mailHttp->requests[0]['body'],true,512,JSON_THROW_ON_ERROR);
check(count($payload['to']) === 1 && !isset($payload['cc']) && !isset($payload['bcc']), 'Email sends to one recipient only');
$mailHttp->response = ['status'=>503,'body'=>'{}'];
rejects(fn() => $mail->send('buyer@example.invalid','Fixture','Text','outbox-2'),ProviderUnavailable::class,'Email failure not reported as delivery');

$helper = new CatalogAssistant(fn($q) => [['id'=>1,'name'=>'Verified fixture','price_cents'=>9000]], fn() => [999999]);
$answer = $helper->answer('Show me a laptop');
check($answer['products'][0]['id']===1 && $answer['products'][0]['price_cents']===9000, 'Assistant cannot introduce model-generated product IDs/prices');
rejects(fn() => (new CurlTransport(['api.bobgo.co.za']))->request('GET','https://127.0.0.1/private'), InvalidArgumentException::class,'Transport blocks unapproved hosts before network');

echo "\n$passed integration checks passed. Fixtures only; no provider account or network was used.\n";
