<?php
declare(strict_types=1);
require_once __DIR__.'/smartthings_oauth.php';

function st_db(): PDO {
    $db=auth_db();
    $db->exec('CREATE TABLE IF NOT EXISTS smartthings_config (id INTEGER PRIMARY KEY, token TEXT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS smartthings_tvs (id TEXT PRIMARY KEY, name TEXT NOT NULL, device_id TEXT NOT NULL UNIQUE, token TEXT NOT NULL)');
    return $db;
}
function st_public_tv(array $tv): array {
    return ['id'=>$tv['id'],'name'=>$tv['name'],'deviceId'=>$tv['device_id'],'hasToken'=>$tv['token']!==''];
}
function st_validate_tv(array $input): array {
    $name=clean_text((string)($input['name']??''),100);
    $device=strtolower(trim((string)($input['deviceId']??'')));
    if($name==='')throw new InvalidArgumentException('Informe o nome da TV.');
    if(!preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/D',$device))throw new InvalidArgumentException('Informe um Device ID válido (UUID) do SmartThings.');
    return [$name,$device];
}
function st_token(string $token): string {
    $token=trim($token);
    if(strlen($token)>4096||($token!==''&&!preg_match('/^[\x21-\x7e]+$/D',$token)))throw new InvalidArgumentException('Token inválido. Cole apenas o token, sem Bearer.');
    return $token;
}
function st_request(string $token,string $path,?array $payload=null): array {
    if($token==='')throw new RuntimeException('Configure um token para esta TV ou um token padrão.');
    if(!function_exists('curl_init'))throw new RuntimeException('Ative a extensão cURL do PHP no servidor.');
    $curl=curl_init('https://api.smartthings.com/v1/devices/'.$path);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>12,CURLOPT_HTTPHEADER=>['Authorization: Bearer '.$token,'Accept: application/json','Content-Type: application/json']]);
    if($payload!==null)curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($payload)]);
    $raw=curl_exec($curl);$code=curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
    if($raw===false)throw new RuntimeException('Não foi possível consultar o SmartThings. Verifique a conexão do servidor.');
    if($code<200||$code>=300)throw new RuntimeException(match($code){401=>'Acesso inválido ou expirado. Reconecte OAuth ou atualize o token manual nas configurações.',403=>'Token sem permissão para acessar ou controlar esta TV.',404=>'TV não encontrada. Verifique o Device ID.',429=>'Limite do SmartThings atingido. Aguarde antes de tentar novamente.',default=>'O SmartThings não concluiu a solicitação (HTTP '.$code.').'},$code);
    $data=json_decode($raw,true);
    if(!is_array($data))throw new RuntimeException('Resposta inválida do SmartThings.');
    return $data;
}
function st_status(array $health,array $status): string {
    if(($health['state']??'')==='OFFLINE')return 'offline';
    if(($health['state']??'')!=='ONLINE')return 'unknown';
    $power=$status['components']['main']['switch']['switch']['value']??null;
    return in_array($power,['on','off'],true)?$power:'unknown';
}
function st_tv_request(array $tv,string $suffix,?array $payload=null): array {
    $db=st_db();
    $oauth=st_oauth_read();
    $token=$tv['token']?: (!empty($oauth['clientId'])?st_oauth_access_token():(string)$db->query('SELECT token FROM smartthings_config WHERE id=1')->fetchColumn());
    $useOAuth=$tv['token']===''&&!empty($oauth['clientId']);
    $request=static function(string $path,?array $payload=null)use(&$token,$useOAuth):array{
        try{return st_request($token,$path,$payload);}catch(RuntimeException $error){
            if(!$useOAuth||$error->getCode()!==401)throw $error;
            $token=st_oauth_access_token(null,$token);
            return st_request($token,$path,$payload);
        }
    };
    return $request(rawurlencode($tv['device_id']).$suffix,$payload);
}
function st_power(array $tv,string $command): void {
    if(!in_array($command,['on','off'],true))throw new InvalidArgumentException('Comando inválido.');
    $result=st_tv_request($tv,'/commands',['commands'=>[['component'=>'main','capability'=>'switch','command'=>$command]]]);
    $results=$result['results']??[];
    if(!$results)throw new RuntimeException('O SmartThings não confirmou o recebimento do comando.');
    foreach($results as $item)if(!in_array($item['status']??'',['ACCEPTED','COMPLETED'],true))throw new RuntimeException('O SmartThings recusou o comando da TV.');
}
function smartthings_routes(string $action): never {
    if($action==='smartthings_oauth_start')st_oauth_start();
    session_write_close();
    if(in_array($action,['smartthings_oauth_save','smartthings_oauth_disconnect'],true))st_oauth_routes($action);
    $db=st_db();
    require_once __DIR__.'/smartthings_schedule.php';
    require_once __DIR__.'/smartthings_cron.php';
    st_schedule_db();
    if($action==='smartthings_schedule_save')st_schedule_save_route();
    if($action==='smartthings_config') {
        $tvs=$db->query('SELECT * FROM smartthings_tvs ORDER BY name')->fetchAll();
        json_response(['ok'=>true,'tvs'=>array_map('st_public_tv',$tvs),'hasToken'=>(bool)$db->query('SELECT token FROM smartthings_config WHERE id=1')->fetchColumn(),'canConfigure'=>is_admin(),'schedules'=>st_schedule_list(),'scheduler'=>st_scheduler_health(),'cronKey'=>is_admin()?st_cron_key(true):null,'oauth'=>is_admin()?st_oauth_public(st_oauth_read()):null]);
    }
    if(in_array($action,['smartthings_save','smartthings_delete','smartthings_token'],true)) {
        require_admin();
        if($action==='smartthings_token') {
            $token=st_token((string)($_POST['token']??''));
            $q=$db->prepare('INSERT OR REPLACE INTO smartthings_config(id,token) VALUES(1,?)');$q->execute([$token]);
        } elseif($action==='smartthings_delete') {
            $q=$db->prepare('DELETE FROM smartthings_tvs WHERE id=?');$q->execute([(string)($_POST['id']??'')]);
            $q=$db->prepare('DELETE FROM smartthings_schedules WHERE tv_id=?');$q->execute([(string)($_POST['id']??'')]);
        } else {
            [$name,$device]=st_validate_tv($_POST);$id=(string)($_POST['id']??'');
            $q=$db->prepare('SELECT * FROM smartthings_tvs WHERE id=?');$q->execute([$id]);$old=$q->fetch();
            if($id!==''&&!$old)throw new InvalidArgumentException('TV não encontrada. Atualize a lista.');
            $token=st_token((string)($_POST['token']??''));
            if($token===''&&empty($_POST['clearToken']))$token=$old['token']??'';
            $q=$db->prepare('SELECT id FROM smartthings_tvs WHERE device_id=? AND id<>?');$q->execute([$device,$id]);
            if($q->fetch())throw new InvalidArgumentException('Esta TV já está cadastrada.');
            $q=$db->prepare('INSERT OR REPLACE INTO smartthings_tvs(id,name,device_id,token) VALUES(?,?,?,?)');$q->execute([$id?:bin2hex(random_bytes(16)),$name,$device,$token]);
        }
        audit_event($action,[],null);json_response(['ok'=>true]);
    }
    if(!in_array($action,['smartthings_status','smartthings_command'],true))json_response(['ok'=>false,'error'=>'Ação inválida.'],400);
    $q=$db->prepare('SELECT * FROM smartthings_tvs WHERE id=?');$q->execute([(string)($_POST['id']??'')]);$tv=$q->fetch();
    if(!$tv)throw new InvalidArgumentException('TV não encontrada.');
    if($action==='smartthings_command') {
        $command=(string)($_POST['command']??'');
        if(!in_array($command,['on','off'],true))throw new InvalidArgumentException('Comando inválido.');
        st_power($tv,$command);
        audit_event('smartthings.power',['command'=>$command],$tv['id']);
        json_response(['ok'=>true,'message'=>'Comando enviado. Aguardando atualização da TV.']);
    }
    $health=st_tv_request($tv,'/health');
    $status=($health['state']??'')==='OFFLINE'?[]:st_tv_request($tv,'/status');
    json_response(['ok'=>true,'status'=>st_status($health,$status),'checkedAt'=>time()]);
}
