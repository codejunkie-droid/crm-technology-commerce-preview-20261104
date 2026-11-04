<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli') { http_response_code(404); exit; }
if($argc!==2) {fwrite(STDERR,"Usage: php bin/backup.php /absolute/private/location/backup.sql.gz\n");exit(1);}
$root=dirname(__DIR__);$destination=$argv[1];$directory=realpath(dirname($destination));
if(!$directory || !str_starts_with($destination,'/') || str_starts_with($directory,realpath($root.'/public')) || file_exists($destination)) {fwrite(STDERR,"Choose a new absolute path outside public/, in an existing private directory.\n");exit(1);}
require $root.'/src/bootstrap.php';
$db=new Database(); $pdo=$db->pdo;
$tables=[];preg_match_all('/CREATE TABLE IF NOT EXISTS ([a-z_]+)/',file_get_contents($root.'/database/schema.sql'),$m);$tables=$m[1];
$previousMask=umask(0077);$fp=gzopen($destination,'wb9');umask($previousMask);
if(!$fp)throw new RuntimeException('Cannot create backup.');
try {
 if($db->mysql)$pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
 $pdo->beginTransaction();
 gzwrite($fp,"-- CRM Technology application backup ".gmdate('c')."\n-- Restore into a NEW isolated database and verify before switching.\n");
 gzwrite($fp,$db->mysql?"SET FOREIGN_KEY_CHECKS=0;\n":"PRAGMA foreign_keys=OFF;\n");
 foreach($tables as $table) {
  if($db->mysql){$row=$pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM);$schema=$row[1];}
  else {$stmt=$pdo->prepare("SELECT sql FROM sqlite_master WHERE type='table' AND name=?");$stmt->execute([$table]);$schema=$stmt->fetchColumn();}
  if(!$schema)continue;
  gzwrite($fp,'DROP TABLE IF EXISTS `'.$table."`;\n".$schema.";\n");
  $rows=$pdo->query('SELECT * FROM `'.$table.'`');
  while($row=$rows->fetch(PDO::FETCH_ASSOC)) {
   $cols=array_map(fn($x)=>'`'.str_replace('`','``',$x).'`',array_keys($row));
   $values=array_map(fn($v)=>$v===null?'NULL':$pdo->quote((string)$v),array_values($row));
   gzwrite($fp,'INSERT INTO `'.$table.'` ('.implode(',',$cols).') VALUES ('.implode(',',$values).");\n");
  }
 }
 if(!$db->mysql)foreach($pdo->query("SELECT sql FROM sqlite_master WHERE type='index' AND sql IS NOT NULL") as $index)gzwrite($fp,$index['sql'].";\n");
 gzwrite($fp,$db->mysql?"SET FOREIGN_KEY_CHECKS=1;\n":"PRAGMA foreign_keys=ON;\n");
 $pdo->commit();gzclose($fp);chmod($destination,0600);
 echo "Created private database snapshot. Encrypt it and transfer it to approved offsite storage.\n";
} catch(Throwable $e) {if($pdo->inTransaction())$pdo->rollBack();gzclose($fp);unlink($destination);throw $e;}
