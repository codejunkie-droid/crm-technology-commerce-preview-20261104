<?php
declare(strict_types=1);
putenv('APP_ENV=demo');
$dsn=getenv('TEST_DB_DSN')?:'sqlite::memory:';putenv('DB_DSN='.$dsn);
if(getenv('TEST_DB_USER'))putenv('DB_USER='.getenv('TEST_DB_USER'));
require __DIR__.'/../src/bootstrap.php';
$tests=[];$failures=0;
function verify(string $name, callable $fn):void{global $tests,$failures;try{$fn();$tests[]=['test'=>$name,'status'=>'pass'];}catch(Throwable $e){$tests[]=['test'=>$name,'status'=>'fail','error'=>$e->getMessage()];$failures++;}}
function expect(bool $value):void{if(!$value)throw new RuntimeException('Expectation failed');}
function denied(callable $fn, int $status):void{try{$fn();}catch(HttpError $e){expect($e->status===$status);return;}throw new RuntimeException('Operation unexpectedly succeeded');}
$nonce=bin2hex(random_bytes(7));$_SERVER['REMOTE_ADDR']='security-'.$nonce;
$credentials=['name'=>'Security fixture A','email'=>'audit-a-'.$nonce.'@example.test','password'=>'Fixture-password-A-123'];
$store->register($credentials);$userA=(int)$store->user()['id'];$tokenA=$_SESSION['guest_token'];
$p=$store->catalog(['stock'=>1,'limit'=>1])['products'][0];
$store->updateCart(['variant_id'=>$p['variant_id'],'quantity'=>1]);
$quote=$store->createQuote(['source'=>'cart']);
$store->toggleWishlist(['product_id'=>$p['id']]);
$checkout=['idempotency_key'=>'security-'.$nonce.'-first','name'=>'Fixture','email'=>$credentials['email'],'phone'=>'000','address'=>'Fixture address','city'=>'Fixture city','postal_code'=>'0000','payment_method'=>'eft','delivery'=>'quote'];
$order=$store->checkout($checkout)['order'];
$store->updateCart(['variant_id'=>$p['variant_id'],'quantity'=>1]);

verify('Direct registration into another account isolates previous cart and wishlist',function()use($store,$nonce,$tokenA){
    $store->register(['name'=>'Security fixture B','email'=>'audit-b-'.$nonce.'@example.test','password'=>'Fixture-password-B-123']);
    expect($_SESSION['guest_token']!==$tokenA);expect($store->cart()['count']===0);expect(count($store->wishlist()['products'])===0);
});
verify('Account-owned order and quote reject even a matching old guest token',function()use($store,$order,$quote,$tokenA){
    $current=$_SESSION['guest_token'];$_SESSION['guest_token']=$tokenA;
    denied(fn()=>$store->order((int)$order['id']),404);denied(fn()=>$store->quotePdf((int)$quote['id']),404);$_SESSION['guest_token']=$current;
});
verify('Direct login across accounts changes guest capability',function()use($store,$credentials){
    $prior=$_SESSION['guest_token'];$store->login($credentials);expect($_SESSION['guest_token']!==$prior);
});
verify('Cart increment enforces the final quantity bound',function()use($store,$p){
    $store->db->run('UPDATE product_variants SET stock=1000 WHERE id=?',[$p['variant_id']]);
    $store->updateCart(['variant_id'=>$p['variant_id'],'quantity'=>100]);
    denied(fn()=>$store->updateCart(['variant_id'=>$p['variant_id'],'quantity'=>1],true),400);
    expect($store->cart()['count']===100);$store->updateCart(['variant_id'=>$p['variant_id'],'quantity'=>0]);
});

$store->db->run("UPDATE orders SET payment_method='payfast' WHERE id=?",[$order['id']]);
$event=['provider_id'=>'AUDIT-'.$nonce,'order_id'=>(int)$order['id'],'amount_cents'=>(int)$order['total_cents'],'status'=>'complete'];
verify('Payment duplicate does not duplicate money records',function()use($store,$event){
    expect($store->recordVerifiedPayment($event)['duplicate']===false);
    expect($store->recordVerifiedPayment($event)['duplicate']===true);
    expect((int)$store->db->run('SELECT COUNT(*) FROM payments WHERE provider_reference=?',['payfast:'.$event['provider_id']])->fetchColumn()===1);
});
verify('Payment identifier replay cannot be attributed to a different order',function()use($store,$event,$checkout,$p){
    $store->updateCart(['variant_id'=>$p['variant_id'],'quantity'=>1]);$new=$checkout;$new['idempotency_key'].='-second';$second=$store->checkout($new)['order'];
    $store->db->run("UPDATE orders SET payment_method='payfast' WHERE id=?",[$second['id']]);$replay=$event;$replay['order_id']=(int)$second['id'];
    denied(fn()=>$store->recordVerifiedPayment($replay),409);expect($store->order((int)$second['id'])['status']==='pending_payment');
});
verify('A malformed or fractional payment event cannot be persisted',function()use($store,$event){
    $bad=$event;$bad['amount_cents']='1.5';denied(fn()=>$store->recordVerifiedPayment($bad),400);
    $bad=$event;$bad['provider_id']='';denied(fn()=>$store->recordVerifiedPayment($bad),400);
});
verify('Rate limiter reset and cap are enforced by conditional writes',function()use($store,$nonce){
    $key='audit-limit-'.$nonce;$store->limit($key,1,60);denied(fn()=>$store->limit($key,1,60),429);
    $store->db->run('UPDATE rate_limits SET expires_at=? WHERE bucket=?',[time()-1,hash('sha256',$key)]);$store->limit($key,1,60);denied(fn()=>$store->limit($key,1,60),429);
});
verify('Changing an address owned by another user is denied',function()use($store,$nonce){
    $own=$store->saveAddress(['label'=>'Fixture','address'=>'Fixture','city'=>'Fixture','postal_code'=>'0000']);$id=$own['addresses'][0]['id'];
    $store->register(['name'=>'Security fixture C','email'=>'audit-c-'.$nonce.'@example.test','password'=>'Fixture-password-C-123']);
    denied(fn()=>$store->saveAddress(['id'=>$id,'label'=>'Changed','address'=>'Changed','city'=>'Changed','postal_code'=>'0000']),404);
});
verify('Signed-in cart survives logout and merges guest items only for its owner',function()use($store,$credentials,$p){
    $store->login($credentials);$store->updateCart(['variant_id'=>$p['variant_id'],'quantity'=>2]);
    $store->logout();expect($store->cart()['count']===0);$store->updateCart(['variant_id'=>$p['variant_id'],'quantity'=>1]);
    $store->login($credentials);expect($store->cart()['count']===3);
    $owner=(int)$store->user()['id'];expect((int)$store->db->run('SELECT COUNT(*) FROM carts WHERE user_id=?',[$owner])->fetchColumn()===1);
});
verify('Expired saved cart is not restored during login',function()use($store,$credentials){
    $owner=(int)$store->user()['id'];$store->db->run('UPDATE carts SET updated_at=? WHERE user_id=?',[gmdate('c',time()-90000),$owner]);
    $store->logout();$store->login($credentials);expect($store->cart()['count']===0);
});
verify('Password reset revokes all previously authenticated sessions',function()use($store,$credentials){
    $previousSession=$_SESSION;$store->forgot(['email'=>$credentials['email']]);
    $row=$store->db->one("SELECT payload FROM email_outbox WHERE recipient=? AND kind='password_reset' ORDER BY id DESC LIMIT 1",[$credentials['email']]);
    $token=json_decode($row['payload'],true,512,JSON_THROW_ON_ERROR)['token'];
    $store->resetPassword(['token'=>$token,'password'=>'Replacement-fixture-password-123']);
    expect($store->user()===null);$_SESSION=$previousSession;expect($store->user()===null);
    denied(fn()=>$store->resetPassword(['token'=>$token,'password'=>'Replacement-fixture-password-456']),400);
    $store->login(['email'=>$credentials['email'],'password'=>'Replacement-fixture-password-123']);expect($store->user()!==null);
});
$admin=$store->db->insert('users',['name'=>'Audit administrator','email'=>'audit-admin-'.$nonce.'@example.test','password_hash'=>password_hash('Fixture-admin-password-123',PASSWORD_DEFAULT),'role'=>'admin','created_at'=>gmdate('c')]);
$_SESSION['user_id']=$admin;$_SESSION['auth_fingerprint']=hash('sha256',$store->db->one('SELECT password_hash FROM users WHERE id=?',[$admin] )['password_hash']);
verify('Oversized financial input is rejected before arithmetic',function()use($store,$nonce){
    denied(fn()=>$store->adminProduct(['name'=>'Fixture','slug'=>'audit-'.$nonce,'brand'=>'Fixture','category_slug'=>'laptops','price_cents'=>PHP_INT_MAX,'stock'=>1,'sku'=>'AUDIT-'.$nonce]),400);
});
echo json_encode(['database'=>str_starts_with($dsn,'mysql:')?'mysql':'sqlite','passed'=>count($tests)-$failures,'failed'=>$failures,'cases'=>$tests],JSON_PRETTY_PRINT)."\n";
exit($failures?1:0);
