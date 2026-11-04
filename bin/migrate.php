<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../src/bootstrap.php';
$store->db->migrate();
if(in_array('--seed-demo',$argv,true)){if(!$store->demo)throw new RuntimeException('Demo seed is forbidden in production.');require_once __DIR__.'/../src/Seed.php';seedCatalog($store->db);}
echo "Database schema is ready.\n";
