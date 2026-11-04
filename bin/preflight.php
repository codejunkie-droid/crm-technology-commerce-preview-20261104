<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root=dirname(__DIR__); $failures=0;
function check(string $label,bool $ok,string $detail=''): void { global $failures; if(!$ok)$failures++; echo ($ok?'PASS':'FAIL').'  '.$label.($detail!==''?' — '.$detail:'').PHP_EOL; }
check('PHP >= 8.2',version_compare(PHP_VERSION,'8.2.0','>='),PHP_VERSION);
foreach(['pdo','pdo_mysql','curl','mbstring','openssl','fileinfo','json','session'] as $extension)check('Extension '.$extension,extension_loaded($extension));
check('Private storage writable',is_dir($root.'/storage')&&is_writable($root.'/storage'));
check('Application entry point',is_file($root.'/public/index.php'));
check('Apache rewrite file',is_file($root.'/public/.htaccess'));
check('Production environment file exists',is_file($root.'/.env'),'Copy .env.example and supply your own settings');
if(is_file($root.'/.env')) {
 $cfg=parse_ini_file($root.'/.env',false,INI_SCANNER_RAW) ?: [];
 check('Explicit production mode',($cfg['APP_ENV']??'')==='production');
 check('MySQL production DSN',str_starts_with($cfg['DB_DSN']??'','mysql:'));
 check('HTTPS application URL',str_starts_with($cfg['APP_URL']??'','https://'));
 check('Environment file not world-readable',(fileperms($root.'/.env')&0004)===0,'Use chmod 600 .env when allowed by hosting PHP ownership');
}
echo PHP_EOL.'This checks local prerequisites only. It does not verify provider accounts, DNS, public HTTPS, banking details, legal policies or live stock.'.PHP_EOL;
exit($failures?1:0);
