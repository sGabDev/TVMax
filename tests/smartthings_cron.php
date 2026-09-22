<?php
declare(strict_types=1);
require __DIR__.'/../app/smartthings_cron.php';
function auth_db():PDO{static $db;return $db??=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
function ensure(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
ensure(st_cron_key()==='','Public requests must not provision a secret');
ensure(!st_cron_authorized('',''),'Unconfigured cron refuses empty credentials');
$key=st_cron_key(true);
ensure(strlen($key)===64&&ctype_xdigit($key),'256-bit installation secret');
ensure(st_cron_key(true)===$key&&st_cron_key()===$key,'Secret persists across panel refreshes');
ensure(st_cron_authorized($key,$key),'Accept valid secret');
ensure(!st_cron_authorized($key,str_repeat('x',64)),'Reject different key');
ensure(!st_cron_authorized($key,''),'Reject missing key');
echo "PASS HTTP cron authentication, missing key refusal and persistent installation secret\n";
