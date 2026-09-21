<?php
declare(strict_types=1);

function st_oauth_read(): array {
    $db=auth_db();
    $db->exec('CREATE TABLE IF NOT EXISTS smartthings_oauth (id INTEGER PRIMARY KEY, data TEXT NOT NULL)');
    $data=json_decode((string)$db->query('SELECT data FROM smartthings_oauth WHERE id=1')->fetchColumn(),true);
    return is_array($data)?$data:[];
}
function st_oauth_write(array $data): void {
    $q=auth_db()->prepare('INSERT OR REPLACE INTO smartthings_oauth(id,data) VALUES(1,?)');
    $q->execute([json_encode($data,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES)]);
}
function st_oauth_lock(callable $fn): mixed {
    $fp=fopen(APP_ROOT.'/data/smartthings-oauth.lock','c');
    if(!$fp||!flock($fp,LOCK_EX))throw new RuntimeException('Não foi possível bloquear a configuração OAuth.');
    try{return $fn();}finally{flock($fp,LOCK_UN);fclose($fp);}
}
function st_oauth_base(string $url): string {
    $url=rtrim(trim($url),'/');$parts=parse_url($url);
    if(strlen($url)>1000||!filter_var($url,FILTER_VALIDATE_URL)||($parts['scheme']??'')!=='https'||isset($parts['user'])||isset($parts['pass'])||isset($parts['query'])||isset($parts['fragment'])||preg_match('/[\x00-\x20\\\\]/',$url))throw new InvalidArgumentException('Informe a URL pública HTTPS da pasta do TVMax, sem parâmetros.');
    return $url;
}
function st_oauth_public(array $c): array {
    $base=$c['baseUrl']??'';
    return ['clientId'=>$c['clientId']??'','appId'=>$c['appId']??'','baseUrl'=>$base,'hasSecret'=>!empty($c['clientSecret']),
        'connected'=>!empty($c['refreshToken']),'expiresAt'=>$c['expiresAt']??0,
        'callbackUrl'=>$base?$base.'/smartthings-callback.php':'',
        'webhookUrl'=>$base&&!empty($c['webhookKey'])?$base.'/smartthings-webhook.php?key='.$c['webhookKey']:'',
        'confirmationUrl'=>$c['confirmationUrl']??'','lastWebhookAt'=>$c['lastWebhookAt']??0];
}
function st_oauth_fingerprint(array $c): string {
    return hash('sha256',json_encode([$c['clientId']??'',$c['clientSecret']??'',$c['baseUrl']??'',$c['revision']??'']));
}
function st_oauth_forget(array &$c): void {
    foreach(['accessToken','refreshToken','expiresAt','installedAppId'] as $key)unset($c[$key]);
}
function st_oauth_routes(string $action): never {
    require_admin();
    st_oauth_read();
    st_oauth_lock(static function()use($action){
        $c=st_oauth_read();
        if($action==='smartthings_oauth_disconnect'){
            st_oauth_forget($c);$c['revision']=bin2hex(random_bytes(16));
        }else{
            $id=trim((string)($_POST['clientId']??''));$secret=trim((string)($_POST['clientSecret']??''));
            if(!preg_match('/^[a-zA-Z0-9._-]{1,200}$/D',$id))throw new InvalidArgumentException('Client ID inválido.');
            if($secret===''){
                if($id!==($c['clientId']??''))throw new InvalidArgumentException('Informe o Client Secret para este Client ID.');
                $secret=$c['clientSecret']??'';
            }
            if(!preg_match('/^[\x21-\x7e]{1,4096}$/D',$secret))throw new InvalidArgumentException('Informe um Client Secret válido.');
            $base=st_oauth_base((string)($_POST['baseUrl']??''));
            $appId=strtolower(trim((string)($_POST['appId']??'')));
            if($appId!==''&&!preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/D',$appId))throw new InvalidArgumentException('App ID inválido.');
            if($id!==($c['clientId']??'')||$secret!==($c['clientSecret']??'')||$base!==($c['baseUrl']??'')||$appId!==($c['appId']??'')){
                st_oauth_forget($c);unset($c['confirmationUrl'],$c['lastWebhookAt']);$c['revision']=bin2hex(random_bytes(16));
            }
            $c=array_merge($c,['clientId'=>$id,'clientSecret'=>$secret,'baseUrl'=>$base,'appId'=>$appId,'webhookKey'=>$c['webhookKey']??bin2hex(random_bytes(32))]);
        }
        st_oauth_write($c);
    });
    audit_event($action);json_response(['ok'=>true]);
}
function st_oauth_start(): never {
    require_admin();$c=st_oauth_read();
    if(empty($c['clientId'])||empty($c['clientSecret'])||empty($c['baseUrl']))throw new InvalidArgumentException('Salve Client ID, Client Secret e URL pública antes de conectar.');
    $state=bin2hex(random_bytes(32));
    $_SESSION['smartthingsOAuth']=['state'=>$state,'expires'=>time()+600,'fingerprint'=>st_oauth_fingerprint($c)];
    session_write_close();
    $url='https://api.smartthings.com/oauth/authorize?'.http_build_query(['client_id'=>$c['clientId'],'response_type'=>'code','redirect_uri'=>$c['baseUrl'].'/smartthings-callback.php','scope'=>'r:devices:* x:devices:*','state'=>$state],'','&',PHP_QUERY_RFC3986);
    json_response(['ok'=>true,'url'=>$url]);
}
function st_oauth_state_valid(?array $pending,string $state,array $config): bool {
    return $pending!==null&&$state!==''&&($pending['expires']??0)>=time()&&hash_equals($pending['state']??'',$state)&&hash_equals($pending['fingerprint']??'',st_oauth_fingerprint($config));
}
function st_oauth_exchange(array $c,array $params): array {
    if(!function_exists('curl_init'))throw new RuntimeException('Ative a extensão cURL do PHP no servidor.');
    $ch=curl_init('https://api.smartthings.com/oauth/token');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>15,
        CURLOPT_HTTPAUTH=>CURLAUTH_BASIC,CURLOPT_USERPWD=>$c['clientId'].':'.$c['clientSecret'],
        CURLOPT_HTTPHEADER=>['Accept: application/json','Content-Type: application/x-www-form-urlencoded'],
        CURLOPT_POSTFIELDS=>http_build_query($params+['client_id'=>$c['clientId']],'','&',PHP_QUERY_RFC3986)]);
    $raw=curl_exec($ch);$status=curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
    if($raw===false)throw new RuntimeException('Falha de conexão ao obter o token OAuth. Tente novamente.');
    if($status<200||$status>=300)throw new RuntimeException('O SmartThings recusou a autorização OAuth (HTTP '.$status.'). Verifique as credenciais e conecte a conta novamente.');
    $data=json_decode($raw,true);
    if(!is_array($data))throw new RuntimeException('Resposta OAuth inválida.');
    return $data;
}
function st_oauth_apply_tokens(array $c,array $data): array {
    foreach(['access_token','refresh_token'] as $key)if(!is_string($data[$key]??null)||!preg_match('/^[\x21-\x7e]{1,4096}$/D',$data[$key]))throw new RuntimeException('O SmartThings não retornou os tokens necessários.');
    $seconds=filter_var($data['expires_in']??null,FILTER_VALIDATE_INT);
    if(!$seconds||$seconds<1||$seconds>31536000)throw new RuntimeException('Validade do token OAuth inválida.');
    $c['accessToken']=$data['access_token'];$c['refreshToken']=$data['refresh_token'];$c['expiresAt']=time()+$seconds;
    $c['installedAppId']=$data['installed_app_id']??($c['installedAppId']??'');
    return $c;
}
function st_oauth_access_token(?callable $exchange=null,?string $rejectedToken=null): string {
    // All writers share this lock: refresh tokens are single-use, including across parallel TV requests.
    return st_oauth_lock(static function()use($exchange,$rejectedToken){
        $c=st_oauth_read();
        if(empty($c['refreshToken']))throw new RuntimeException('Conecte a conta Samsung nas configurações OAuth.');
        if(($c['expiresAt']??0)<=time()+120||($rejectedToken!==null&&hash_equals($c['accessToken']??'',$rejectedToken))){
            $c=st_oauth_apply_tokens($c,($exchange??'st_oauth_exchange')($c,['grant_type'=>'refresh_token','refresh_token'=>$c['refreshToken']]));
            st_oauth_write($c);
        }
        return $c['accessToken'];
    });
}
