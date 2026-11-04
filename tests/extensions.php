<?php
declare(strict_types=1);
putenv('APP_ENV=demo');putenv('DB_DSN='.(getenv('TEST_DB_DSN')?:'sqlite::memory:'));if(getenv('TEST_DB_USER'))putenv('DB_USER='.getenv('TEST_DB_USER'));
require __DIR__.'/../src/bootstrap.php';
$passed=0;
function assertCheck(bool $test,string $label):void {global $passed;if(!$test)throw new RuntimeException($label);$passed++;echo "PASS $label\n";}
function rejected(callable $fn,int $status,string $label):void {try{$fn();}catch(HttpError $e){assertCheck($e->status===$status,$label);return;}throw new RuntimeException('Expected rejection: '.$label);}
function asUser(Store $s,int $id):void {$_SESSION['user_id']=$id;$hash=$s->db->one('SELECT password_hash FROM users WHERE id=?',[$id])['password_hash'];$_SESSION['auth_fingerprint']=hash('sha256',$hash);$_SESSION['auth_hash']=hash('sha256',$hash);}
$nonce=bin2hex(random_bytes(5));$store->register(['name'=>'Extension tests','email'=>'extensions-'.$nonce.'@example.invalid','password'=>'Extension-test-password-42']);$customerId=(int)$store->user()['id'];
$store->updateProfile(['name'=>'Updated customer']);assertCheck($store->user()['name']==='Updated customer','Profile name persists');
$address=$store->saveAddress(['label'=>'Office','address'=>'Synthetic address','city'=>'Test town','postal_code'=>'0000']);assertCheck(count($address['addresses'])===1,'Address persists');
$store->saveAddress(['id'=>$address['addresses'][0]['id'],'label'=>'Updated office','address'=>'Changed test address','city'=>'Test town','postal_code'=>'0000']);assertCheck($store->account()['addresses'][0]['label']==='Updated office','Address edits persist');
rejected(fn()=>$store->updateProfile(['name'=>'Test','email'=>'unverified@example.invalid']),409,'Unverified email change blocked');
$adminId=$store->db->insert('users',['name'=>'Test admin','email'=>'extensions-admin-'.$nonce.'@example.invalid','password_hash'=>password_hash('Extension-test-admin-42',PASSWORD_DEFAULT),'role'=>'admin','created_at'=>gmdate('c')]);asUser($store,$adminId);
$store->adminCategory(['name'=>'Test category','slug'=>'test-'.$nonce,'description'=>'Synthetic test department','position'=>99]);assertCheck(count(array_filter($store->categories(),fn($x)=>$x['slug']==='test-'.$nonce))===1,'Admin category persists');
$product=$store->adminProduct(['name'=>'Test configurable product','slug'=>'test-product-'.$nonce,'brand'=>'QA fixture','category_slug'=>'test-'.$nonce,'description'=>'Synthetic test fixture','image'=>'/assets/product-placeholder.svg','specs'=>['kind'=>'fixture'],'sku'=>'TEST-'.$nonce,'price_cents'=>10001,'stock'=>5]);
$variant=$store->adminVariant(['product_id'=>$product['id'],'sku'=>'TEST-V-'.$nonce,'label'=>'Alternative configuration','price_cents'=>15001,'dealer_price_cents'=>12001,'stock'=>2,'attributes'=>['color'=>'Test']]);
assertCheck(count($store->product($product['slug'])['variants'])===2,'Separate variant identities persist');
asUser($store,$customerId);$cart=$store->updateCart(['variant_id'=>$variant['variant']['variant_id'],'quantity'=>2]);assertCheck($cart['subtotal_cents']===30002,'Variant price determines totals');
rejected(fn()=>$store->updateCart(['variant_id'=>$variant['variant']['variant_id'],'quantity'=>3]),409,'Variant-specific stock enforced');
asUser($store,$adminId);$code='TEST'.strtoupper($nonce);$store->adminCoupon(['code'=>$code,'percent'=>17,'expires_at'=>gmdate('c',time()+3600),'active'=>true]);asUser($store,$customerId);
$cart=$store->applyCoupon(['code'=>$code]);assertCheck($cart['discount_cents']===5100&&$cart['total_cents']===24902,'Coupon rounds down exact integer cents');
$quote=$store->createQuote(['source'=>'cart']);assertCheck($quote['total_cents']===$cart['total_cents'],'Quote captures coupon-adjusted total');
$pdf=$store->quotePdf($quote['id']);assertCheck(str_contains($pdf,'Promotion discount')&&str_contains($pdf,'AFTER DISCOUNT'),'Quote PDF shows promotion discount');
$store->db->run('UPDATE coupons SET active=0 WHERE code=?',[$code]);assertCheck($store->cart()['discount_cents']===0,'Disabled coupon stops affecting totals');
$store->db->run('UPDATE coupons SET active=1,expires_at=? WHERE code=?',[gmdate('c',time()-10),$code]);rejected(fn()=>$store->applyCoupon(['code'=>$code]),400,'Expired coupon rejected');
$store->db->run('UPDATE coupons SET expires_at=? WHERE code=?',[gmdate('c',time()+3600),$code]);$store->db->run("UPDATE users SET role='dealer' WHERE id=?",[$customerId]);
assertCheck($store->variant($variant['variant']['variant_id'])['price_cents']===12001,'Dealer variant price applied server-side');
rejected(fn()=>$store->applyCoupon(['code'=>$code]),400,'Retail coupon cannot stack with dealer price');
$store->db->run('UPDATE product_variants SET dealer_price_cents=? WHERE id=?',[5001,$product['variant_id']]);$filtered=$store->catalog(['category'=>'test-'.$nonce,'max'=>60]);assertCheck($filtered['total']===1,'Dealer catalog filter uses displayed effective price');
echo "\n$passed extension checks passed.\n";
