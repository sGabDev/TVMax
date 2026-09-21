<?php
require __DIR__.'/../app/smartthings.php';
function clean_text(string $text,int $max):string{return mb_substr(trim(strip_tags($text)),0,$max);}
function ensure(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function rejects(callable $fn):void{try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Expected invalid input to be rejected');}
$on=['components'=>['main'=>['switch'=>['switch'=>['value'=>'on']]]]];
$off=['components'=>['main'=>['switch'=>['switch'=>['value'=>'off']]]]];
ensure(st_status(['state'=>'ONLINE'],$on)==='on','Online and on');
ensure(st_status(['state'=>'ONLINE'],$off)==='off','Online and off');
ensure(st_status(['state'=>'OFFLINE'],$on)==='offline','Offline overrides stale power');
ensure(st_status(['state'=>'UNHEALTHY'],$on)==='unknown','Unhealthy is not confirmed offline');
ensure(st_status([],$off)==='unknown','Missing health is not off');
ensure(st_status(['state'=>'ONLINE'],[])==='unknown','Missing switch is not off');
$id='12345678-abcd-abcd-abcd-123456789abc';
ensure(st_validate_tv(['name'=>' Sala ','deviceId'=>strtoupper($id)])===['Sala',$id],'Normalize input');
rejects(fn()=>st_validate_tv(['name'=>'','deviceId'=>$id]));
rejects(fn()=>st_validate_tv(['name'=>'TV','deviceId'=>'../commands']));
rejects(fn()=>st_token("secret\r\nInjected: value"));
rejects(fn()=>st_token('Bearer secret'));
ensure(st_token(' secret ')==='secret','Trim pasted token');
$public=st_public_tv(['id'=>'local','name'=>'TV','device_id'=>$id,'token'=>'SECRET']);
ensure(!str_contains(json_encode($public),'SECRET')&&!array_key_exists('token',$public)&&$public['hasToken'],'Never expose saved tokens');
echo "PASS SmartThings status, input validation and secret redaction\n";
