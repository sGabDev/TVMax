<?php
declare(strict_types=1);
require_once __DIR__.'/smartthings_oauth.php';

function st_confirmation_url(array $data,string $appId): string {
    $url=(string)($data['confirmationUrl']??'');$p=parse_url($url);
    if($appId===''||($data['appId']??'')!==$appId||($p['scheme']??'')!=='https'||($p['host']??'')!=='api.smartthings.com'||isset($p['user'])||isset($p['pass'])||isset($p['port'])||isset($p['fragment'])||($p['path']??'')!=='/v1/apps/'.$appId.'/confirm-registration'||strlen($url)>4096||preg_match('/[\x00-\x20]/',$url))throw new InvalidArgumentException('Confirmação de webhook inválida. Verifique o App ID.');
    parse_str($p['query']??'',$query);
    if(!is_string($query['token']??null)||$query['token']==='')throw new InvalidArgumentException('Token de confirmação ausente.');
    return $url;
}
function st_webhook_signature(string $raw,array $server,callable $getKey): bool {
    $authorization=$server['HTTP_AUTHORIZATION']??$server['REDIRECT_HTTP_AUTHORIZATION']??'';
    if(!str_starts_with($authorization,'Signature '))return false;
    preg_match_all('/(keyId|signature|headers|algorithm)="([^"]*)"/',$authorization,$matches,PREG_SET_ORDER);
    $parts=[];foreach($matches as $match){if(isset($parts[$match[1]]))return false;$parts[$match[1]]=$match[2];}
    if(($parts['algorithm']??'')!=='rsa-sha256'||!preg_match('~^/pl/[a-zA-Z0-9/_-]{1,200}$~D',$parts['keyId']??''))return false;
    $date=$server['HTTP_DATE']??'';$timestamp=strtotime($date);
    if(!$timestamp||abs(time()-$timestamp)>300)return false;
    $digest=$server['HTTP_DIGEST']??'';
    if(!preg_match('/^SHA-?256=(.+)$/iD',$digest,$d)||!hash_equals(base64_encode(hash('sha256',$raw,true)),$d[1]))return false;
    $names=explode(' ',strtolower($parts['headers']??''));
    if(array_diff(['(request-target)','digest','date'],$names))return false;
    $lines=[];
    foreach($names as $name){
        if($name==='(request-target)')$value=strtolower($server['REQUEST_METHOD']??'').' '.($server['REQUEST_URI']??'');
        elseif(preg_match('/^[a-z0-9-]+$/D',$name))$value=$server['HTTP_'.strtoupper(str_replace('-','_',$name))]??null;
        else return false;
        if($value===null)return false;$lines[]=$name.': '.$value;
    }
    $signature=base64_decode($parts['signature']??'',true);
    if($signature===false||$signature==='')return false;
    return openssl_verify(implode("\n",$lines),$signature,$getKey($parts['keyId']),OPENSSL_ALGO_SHA256)===1;
}
function st_webhook_key(string $keyId): string {
    $db=auth_db();$db->exec('CREATE TABLE IF NOT EXISTS smartthings_keys (id TEXT PRIMARY KEY, pem TEXT NOT NULL, expires INTEGER NOT NULL)');
    $q=$db->prepare('SELECT pem FROM smartthings_keys WHERE id=? AND expires>?');$q->execute([$keyId,time()]);$pem=$q->fetchColumn();if($pem)return $pem;
    if(!function_exists('curl_init'))throw new RuntimeException('Extensão cURL indisponível.');
    $ch=curl_init('https://key.smartthings.com/key/'.$keyId);
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>2,CURLOPT_TIMEOUT=>4]);
    $raw=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($status!==200||!is_string($raw)||strlen($raw)>16384)throw new RuntimeException('Não foi possível verificar a assinatura.');
    $decoded=json_decode($raw,true);$pem=is_array($decoded)?($decoded['publicKey']??''):$raw;
    if(!openssl_pkey_get_public($pem))throw new RuntimeException('Chave pública inválida.');
    $q=$db->prepare('INSERT OR REPLACE INTO smartthings_keys(id,pem,expires) VALUES(?,?,?)');$q->execute([$keyId,$pem,time()+3600]);return $pem;
}
function st_webhook_process(array $body): array {
    return st_oauth_lock(static function()use($body){
        $c=st_oauth_read();$type=$body['messageType']??$body['lifecycle']??'';
        if($type==='CONFIRMATION'){
            $c['confirmationUrl']=st_confirmation_url($body['confirmationData']??[],$c['appId']??'');
            $c['lastWebhookAt']=time();st_oauth_write($c);return ['targetUrl'=>$c['baseUrl'].'/smartthings-webhook.php?key='.$c['webhookKey']];
        }
        if($type==='PING')return ['pingData'=>['challenge'=>(string)($body['pingData']['challenge']??'')]];
        $event=$body['eventData']??[];
        $installed=$event['installedApp']['installedAppId']??$body['uninstallData']['installedApp']['installedAppId']??'';
        // Ignore other installations and duplicate uninstalls without changing the linked account.
        if($installed===''||$installed!==($c['installedAppId']??''))return [];
        $remove=$type==='UNINSTALL';
        foreach($event['events']??[] as $item){
            $lifecycle=$item['installedAppLifecycleEvent']??[];
            if(($lifecycle['installedAppId']??'')===$installed&&($lifecycle['lifecycle']??'')==='DELETE')$remove=true;
        }
        if($remove){st_oauth_forget($c);$c['revision']=bin2hex(random_bytes(16));}
        $c['lastWebhookAt']=time();st_oauth_write($c);return [];
    });
}
