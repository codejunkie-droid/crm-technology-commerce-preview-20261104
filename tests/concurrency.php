<?php
declare(strict_types=1);
// Uses only a dedicated disposable MySQL/MariaDB test database. No external payment request is sent.
$dsn=getenv('TEST_DB_DSN')?:'';
if(!str_starts_with($dsn,'mysql:')){fwrite(STDERR,"Set TEST_DB_DSN and TEST_DB_USER for a disposable MySQL/MariaDB database.\n");exit(2);}
putenv('APP_ENV=production');putenv('DB_DSN='.$dsn);putenv('DB_USER='.(getenv('TEST_DB_USER')?:'root'));
define('CRM_ROOT',dirname(__DIR__));
require CRM_ROOT.'/src/Database.php';require CRM_ROOT.'/src/Builder.php';require CRM_ROOT.'/src/Store.php';
$_SESSION=[];$store=new Store();
if(($argv[1]??'')==='--worker'){
    $input=json_decode($argv[2],true,512,JSON_THROW_ON_ERROR);$_SESSION=$input['session']??[];$_SESSION['guest_token']??=bin2hex(random_bytes(32));
    $delay=(float)$input['start']-microtime(true);if($delay>0)usleep((int)($delay*1000000));
    try{
        if($input['kind']==='payment')$result=$store->recordVerifiedPayment($input['event']);
        elseif($input['kind']==='checkout'){$store->demo=true;$result=$store->checkout($input['checkout']);}
        else{$store->limit($input['key'],1,60);$result=['accepted'=>true];}
        echo json_encode(['ok'=>true,'result'=>$result]);
    }catch(HttpError $error){echo json_encode(['ok'=>false,'status'=>$error->status]);}
    exit;
}
$store->db->migrate();$tag=bin2hex(random_bytes(6));$now=gmdate('c');
function race(array $inputs):array{
    $processes=[];$start=microtime(true)+1.0;
    foreach($inputs as $input){$input['start']=$start;$pipes=[];$proc=proc_open([PHP_BINARY,__FILE__,'--worker',json_encode($input,JSON_THROW_ON_ERROR)],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);if(!is_resource($proc))throw new RuntimeException('Cannot launch test worker');fclose($pipes[0]);$processes[]=[$proc,$pipes];}
    $results=[];
    foreach($processes as [$proc,$pipes]){$stdout=stream_get_contents($pipes[1]);$stderr=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($proc);if($code!==0)throw new RuntimeException('Worker failed: '.$stderr);$results[]=json_decode($stdout,true,512,JSON_THROW_ON_ERROR);}
    return $results;
}
function assertRace(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
$orderId=$store->db->insert('orders',['number'=>'RACE-PAY-'.$tag,'guest_token'=>str_repeat('a',64),'idempotency_key'=>'race-pay-'.$tag,'customer_snapshot'=>'{}','subtotal_cents'=>500,'total_cents'=>500,'status'=>'pending_payment','payment_method'=>'payfast','delivery'=>'collection','is_demo'=>1,'created_at'=>$now,'updated_at'=>$now]);
$event=['provider_id'=>'RACE-'.$tag,'order_id'=>$orderId,'amount_cents'=>500,'status'=>'complete'];
$results=race(array_fill(0,4,['kind'=>'payment','event'=>$event]));
assertRace(count(array_filter($results,fn($r)=>$r['ok']))===4,'All valid replay callbacks should succeed');
assertRace(count(array_filter($results,fn($r)=>!$r['result']['duplicate']))===1,'Exactly one callback should apply payment');
assertRace((int)$store->db->run('SELECT COUNT(*) FROM payments WHERE order_id=?',[$orderId])->fetchColumn()===1,'Payment records duplicated');
$cases=[['test'=>'Four concurrent ITNs produce one payment and three idempotent acknowledgements','status'=>'pass']];
$results=race(array_fill(0,4,['kind'=>'limit','key'=>'rate-race-'.$tag]));
assertRace(count(array_filter($results,fn($r)=>$r['ok']))===1,'Concurrent rate limit allowed more than one request');
$cases[]=['test'=>'Concurrent rate-limit checks admit exactly the configured maximum','status'=>'pass'];
$brand=$store->db->insert('brands',['name'=>'Race fixture '.$tag]);$category=$store->db->insert('categories',['slug'=>'race-'.$tag,'name'=>'Race fixture','description'=>'Test only','position'=>999]);
$product=$store->db->insert('products',['category_id'=>$category,'brand_id'=>$brand,'slug'=>'race-product-'.$tag,'name'=>'Race test product','description'=>'Test fixture','image'=>'','specs'=>'{}','is_demo'=>1,'active'=>1,'created_at'=>$now]);
$variant=$store->db->insert('product_variants',['product_id'=>$product,'sku'=>'RACE-'.$tag,'price_cents'=>500,'stock'=>1,'attributes'=>'{}']);
$inputs=[];
for($i=0;$i<4;$i++){$token=bin2hex(random_bytes(32));$cart=$store->db->insert('carts',['token'=>$token,'updated_at'=>$now]);$store->db->insert('cart_items',['cart_id'=>$cart,'variant_id'=>$variant,'quantity'=>1]);$inputs[]=['kind'=>'checkout','session'=>['guest_token'=>$token],'checkout'=>['idempotency_key'=>'race-checkout-'.$tag.'-'.$i,'name'=>'Fixture','email'=>'fixture@example.invalid','phone'=>'000','address'=>'Fixture address','city'=>'Fixture city','postal_code'=>'0000','payment_method'=>'eft','delivery'=>'quote']];}
$results=race($inputs);
assertRace(count(array_filter($results,fn($r)=>$r['ok']))===1,'Only one customer may buy the last unit');
assertRace(count(array_filter($results,fn($r)=>!$r['ok']&&$r['status']===409))===3,'Losing checkouts should return stock conflict');
assertRace((int)$store->db->run('SELECT stock FROM product_variants WHERE id=?',[$variant])->fetchColumn()===0,'Stock must not become negative');
assertRace((int)$store->db->run('SELECT COUNT(*) FROM order_items WHERE variant_id=?',[$variant])->fetchColumn()===1,'Only one order item should be committed');
$cases[]=['test'=>'Four concurrent checkouts for the last unit commit one order and never oversell','status'=>'pass'];
echo json_encode(['database'=>'mysql','passed'=>3,'failed'=>0,'cases'=>$cases],JSON_PRETTY_PRINT)."\n";
