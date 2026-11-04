<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
$lock=fopen(CRM_ROOT.'/storage/cron.lock','c');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){fwrite(STDERR,"A maintenance job is already running.\n");exit(0);}
$cutoff=gmdate('c',time()-86400);
$result=$store->db->transaction(function()use($store,$cutoff){$rows=$store->db->all('SELECT id FROM carts WHERE updated_at<? LIMIT 500',[$cutoff]);foreach($rows as $r){$store->db->run('DELETE FROM cart_items WHERE cart_id=?',[$r['id']]);$store->db->run('DELETE FROM carts WHERE id=?',[$r['id']]);}$store->db->run('DELETE FROM password_resets WHERE expires_at<?',[gmdate('c')]);$store->db->run('DELETE FROM rate_limits WHERE expires_at<?',[time()]);return count($rows);});
echo json_encode(['expired_carts_deleted'=>$result,'orders_and_payment_records_preserved'=>true,'email_delivery'=>'not_configured','inventory_sync'=>'not_configured'],JSON_PRETTY_PRINT)."\n";
flock($lock,LOCK_UN);fclose($lock);
