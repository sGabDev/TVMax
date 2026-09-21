<?php
declare(strict_types=1);
require __DIR__.'/../app/smartthings_webhook.php';
function ensure(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function rejects(callable $fn):void{try{$fn();}catch(RuntimeException|InvalidArgumentException $e){return;}throw new RuntimeException('Expected rejection');}
function auth_db():PDO{static $db;return $db??=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
$temp=sys_get_temp_dir().'/tvmax-oauth-test-'.bin2hex(random_bytes(8));mkdir($temp);mkdir($temp.'/data');define('APP_ROOT',$temp);
try{
 ensure(st_oauth_base(' https://example.com/tvmax/ ')==='https://example.com/tvmax','Base normalization');
 foreach(['http://example.com','https://user:pass@example.com','https://example.com?x=1','https://example.com/#frag',"https://example.com/a\r\nb"] as $url){
  rejects(fn()=>st_oauth_base($url));
 }
 $appId='12345678-abcd-abcd-abcd-123456789abc';
 $c=['clientId'=>'client','clientSecret'=>'TOP_SECRET','baseUrl'=>'https://example.com/tvmax','appId'=>$appId,'webhookKey'=>'private-webhook','revision'=>'rev'];
 $pending=['state'=>'state','expires'=>time()+60,'fingerprint'=>st_oauth_fingerprint($c)];
 ensure(st_oauth_state_valid($pending,'state',$c),'Valid callback');
 ensure(!st_oauth_state_valid($pending,'wrong',$c),'Reject CSRF');
 ensure(!st_oauth_state_valid($pending,'state',array_merge($c,['revision'=>'changed'])),'Reject callback after credentials change/disconnect');
 ensure(!st_oauth_state_valid(array_merge($pending,['expires'=>time()-1]),'state',$c),'Reject expired callback');
 ensure(!st_oauth_state_valid(null,'state',$c),'Reject callback with no session');
 $c=st_oauth_apply_tokens($c,['access_token'=>'access','refresh_token'=>'refresh','expires_in'=>3600,'installed_app_id'=>'installed']);
 $public=st_oauth_public($c);
 ensure($public['connected']&&$public['hasSecret'],'Connection metadata');
 ensure($public['callbackUrl']==='https://example.com/tvmax/smartthings-callback.php','Callback subdirectory');
 foreach(['clientSecret','accessToken','refreshToken'] as $key)ensure(!array_key_exists($key,$public),'Secret redaction '.$key);
 rejects(fn()=>st_oauth_apply_tokens($c,['access_token'=>'access','expires_in'=>3600]));
 st_oauth_read();st_oauth_write($c);$calls=0;
 $exchange=function($config,$params)use(&$calls){$calls++;ensure($params['refresh_token']==='refresh','Use last refresh token');return ['access_token'=>'new-access','refresh_token'=>'new-refresh','expires_in'=>3600];};
 ensure(st_oauth_access_token($exchange)==='access'&&$calls===0,'Fresh access token does not refresh');
 $c['expiresAt']=time()-1;st_oauth_write($c);
 ensure(st_oauth_access_token($exchange)==='new-access'&&$calls===1,'Expired token refreshes');
 ensure(st_oauth_access_token($exchange)==='new-access'&&$calls===1,'Next request reuses refreshed token');
 ensure(st_oauth_read()['refreshToken']==='new-refresh','Persist rotated refresh token');
 $renewals=0;$forced=function($config,$params)use(&$renewals){$renewals++;ensure($params['refresh_token']==='new-refresh','Use rotated refresh on 401');return ['access_token'=>'third-access','refresh_token'=>'third-refresh','expires_in'=>3600];};
 ensure(st_oauth_access_token($forced,'new-access')==='third-access'&&$renewals===1,'401 triggers renewal');
 ensure(st_oauth_access_token($forced,'new-access')==='third-access'&&$renewals===1,'Concurrent 401 reuses renewed token');
 $confirm='https://api.smartthings.com/v1/apps/'.$appId.'/confirm-registration?token=test';
 ensure(st_confirmation_url(['appId'=>$appId,'confirmationUrl'=>$confirm],$appId)===$confirm,'Valid confirmation');
 rejects(fn()=>st_confirmation_url(['appId'=>$appId,'confirmationUrl'=>'https://evil.example/'],$appId));
 rejects(fn()=>st_confirmation_url(['appId'=>'wrong','confirmationUrl'=>$confirm],$appId));
 st_webhook_process(['messageType'=>'CONFIRMATION','confirmationData'=>['appId'=>$appId,'confirmationUrl'=>$confirm]]);
 ensure(st_oauth_read()['confirmationUrl']===$confirm,'Confirmation available to administrator');
 st_webhook_process(['lifecycle'=>'UNINSTALL','uninstallData'=>['installedApp'=>['installedAppId'=>'other']]]);
 ensure(!empty(st_oauth_read()['refreshToken']),'Do not unlink other installation');
 st_webhook_process(['messageType'=>'EVENT','eventData'=>['installedApp'=>['installedAppId'=>'installed'],'events'=>[['installedAppLifecycleEvent'=>['installedAppId'=>'installed','lifecycle'=>'DELETE']]]]]);
 ensure(empty(st_oauth_read()['refreshToken']),'Uninstall removes access');
 rejects(fn()=>st_oauth_access_token($exchange));
 $options=['private_key_bits'=>2048,'private_key_type'=>OPENSSL_KEYTYPE_RSA];
 $opensslConfig='C:/xampp/php/extras/openssl/openssl.cnf';if(is_file($opensslConfig))$options['config']=$opensslConfig;
 $private=openssl_pkey_new($options);ensure($private!==false,'Generate signature test key');$pem=openssl_pkey_get_details($private)['key'];
 $raw='{"messageType":"EVENT"}';$date=gmdate('D, d M Y H:i:s').' GMT';$digest='SHA-256='.base64_encode(hash('sha256',$raw,true));
 $uri='/tvmax/smartthings-webhook.php?key=private-webhook';
 openssl_sign('(request-target): post '.$uri."\ndigest: ".$digest."\ndate: ".$date,$signature,$private,OPENSSL_ALGO_SHA256);
 $server=['REQUEST_METHOD'=>'POST','REQUEST_URI'=>$uri,'HTTP_DATE'=>$date,'HTTP_DIGEST'=>$digest,
  'HTTP_AUTHORIZATION'=>'Signature keyId="/pl/useast1/test",headers="(request-target) digest date",algorithm="rsa-sha256",signature="'.base64_encode($signature).'"'];
 ensure(st_webhook_signature($raw,$server,fn()=>$pem),'Accept valid signature');
 ensure(!st_webhook_signature($raw.' ',$server,fn()=>$pem),'Reject altered body');
 ensure(!st_webhook_signature($raw,array_merge($server,['REQUEST_URI'=>'/other']),fn()=>$pem),'Reject altered URL');
 ensure(!st_webhook_signature($raw,array_merge($server,['HTTP_DATE'=>'Mon, 01 Jan 2001 00:00:00 GMT']),fn()=>$pem),'Reject old signature');
 ensure(!st_webhook_signature($raw,[],fn()=>$pem),'Reject unsigned events');
 echo "PASS OAuth state, URL validation, token rotation, secret redaction, webhook confirmation, unlink and RSA signatures\n";
}finally{
 if(is_file($temp.'/data/smartthings-oauth.lock'))unlink($temp.'/data/smartthings-oauth.lock');rmdir($temp.'/data');rmdir($temp);
}
