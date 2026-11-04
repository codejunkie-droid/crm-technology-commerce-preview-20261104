<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
$email=strtolower(trim(getenv('ADMIN_EMAIL')?:''));$password=getenv('ADMIN_PASSWORD')?:'';$name=trim(getenv('ADMIN_NAME')?:'Administrator');
if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password)<16||strlen($password)>128)throw new RuntimeException('Set ADMIN_EMAIL, ADMIN_NAME and a unique ADMIN_PASSWORD of 16–128 characters through environment variables. Do not put credentials in command history.');
if($store->db->one('SELECT id FROM users WHERE email=?',[$email]))throw new RuntimeException('Account exists; use a controlled role-change procedure instead.');
$id=$store->db->insert('users',['name'=>$name,'email'=>$email,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'super_admin','created_at'=>gmdate('c')]);
$store->audit('admin.created.cli','user:'.$id);
echo "Administrator account created. No password has been printed.\n";
