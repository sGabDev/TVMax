<?php
function auth_db(): PDO {
    static $db=null;if($db)return $db;
    if(!is_dir(APP_ROOT.'/data'))mkdir(APP_ROOT.'/data',0775,true);
    $db=new PDO('sqlite:'.APP_ROOT.'/data/accounts.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec('PRAGMA busy_timeout=5000');
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id TEXT PRIMARY KEY, name TEXT NOT NULL, email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL, role TEXT NOT NULL DEFAULT 'operator',
        status TEXT NOT NULL DEFAULT 'pending', created_at INTEGER NOT NULL,
        reviewed_at INTEGER, reviewed_by TEXT, last_login INTEGER)");
    $db->exec("CREATE TABLE IF NOT EXISTS audit (
        id INTEGER PRIMARY KEY AUTOINCREMENT, at INTEGER NOT NULL,
        actor_id TEXT, actor_name TEXT NOT NULL, action TEXT NOT NULL,
        outcome TEXT NOT NULL, target TEXT, details TEXT NOT NULL, ip TEXT NOT NULL,
        source TEXT NOT NULL DEFAULT 'server')");
    $db->exec('CREATE INDEX IF NOT EXISTS audit_at ON audit(at)');
    $db->exec('CREATE INDEX IF NOT EXISTS audit_actor ON audit(actor_id,id)');
    $db->exec('CREATE TABLE IF NOT EXISTS attempts (bucket TEXT NOT NULL, at INTEGER NOT NULL)');
    $db->exec('CREATE INDEX IF NOT EXISTS attempts_bucket ON attempts(bucket,at)');
    return $db;
}
function safe_user(array $user): array {
    unset($user['password_hash']);return $user;
}
function session_actor(): ?array {
    if(empty($_SESSION['user_id']))return null;
    $q=auth_db()->prepare('SELECT * FROM users WHERE id=?');$q->execute([$_SESSION['user_id']]);$u=$q->fetch();
    return $u?safe_user($u):null;
}
function current_user(): ?array {
    $u=session_actor();return $u&&$u['status']==='active'?$u:null;
}
function is_admin(): bool {return (current_user()['role']??'')==='admin';}
function require_user(): array {
    $user=current_user();if(!$user)json_response(['ok'=>false,'error'=>'Entre com uma conta aprovada para continuar.'],401);
    $GLOBALS['auditActor']=$user;return $user;
}
function require_admin(): array {
    $user=require_user();if($user['role']!=='admin')json_response(['ok'=>false,'error'=>'Esta ação é exclusiva de administradores.'],403);
    return $user;
}
function csrf_token(): string {if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function require_csrf(): void {
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST')json_response(['ok'=>false,'error'=>'Use POST para esta ação.'],405);
    $token=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
    if(!$token||!hash_equals(csrf_token(),$token))json_response(['ok'=>false,'error'=>'Sessão inválida. Recarregue a página e tente novamente.'],403);
}
function first_admin_needed(): bool {return (int)auth_db()->query("SELECT count(*) FROM users WHERE role='admin'")->fetchColumn()===0;}
function audit_event(string $action,array $details=[],?string $target=null,string $outcome='success',?array $actor=null,string $source='server'): void {
    $actor=$actor??($GLOBALS['auditActor']??session_actor());
    $q=auth_db()->prepare('INSERT INTO audit(at,actor_id,actor_name,action,outcome,target,details,ip,source) VALUES(?,?,?,?,?,?,?,?,?)');
    $q->execute([(int)round(microtime(true)*1000),$actor['id']??null,$actor['name']??(PHP_SAPI==='cli'?'Sistema':'Visitante'),
        $action,$outcome,$target,json_encode($details,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE),
        $_SERVER['REMOTE_ADDR']??'local',$source]);
}
function rate_limit(string $scope,int $limit,int $seconds): void {
    $db=auth_db();$bucket=$scope.':'.($_SERVER['REMOTE_ADDR']??'local');$now=time();
    $db->exec('BEGIN IMMEDIATE');
    try {
        $q=$db->prepare('DELETE FROM attempts WHERE at<?');$q->execute([$now-3600]);
        $q=$db->prepare('SELECT count(*) FROM attempts WHERE bucket=? AND at>?');$q->execute([$bucket,$now-$seconds]);
        $blocked=(int)$q->fetchColumn()>=$limit;
        if(!$blocked){$q=$db->prepare('INSERT INTO attempts(bucket,at) VALUES(?,?)');$q->execute([$bucket,$now]);}
        $db->exec('COMMIT');
    } catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
    if($blocked)json_response(['ok'=>false,'error'=>'Muitas tentativas. Aguarde alguns minutos e tente novamente.'],429);
}
function validate_account(): array {
    $name=clean_text((string)($_POST['name']??''),100);
    $email=strtolower(trim((string)($_POST['email']??'')));
    $password=(string)($_POST['password']??'');
    if(mb_strlen($name)<2)throw new InvalidArgumentException('Informe seu nome completo.');
    if(strlen($email)>254||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('Informe um e-mail válido.');
    if(mb_strlen($password)<8||strlen($password)>72)throw new InvalidArgumentException('Use uma senha com pelo menos 8 caracteres. A senha informada é curta ou longa demais.');
    if(isset($_POST['passwordConfirm'])&&!hash_equals($password,(string)$_POST['passwordConfirm']))throw new InvalidArgumentException('As senhas não coincidem.');
    return [$name,$email,$password];
}
function establish_session(array $user): void {
    session_regenerate_id(true);$_SESSION=[];$_SESSION['user_id']=$user['id'];csrf_token();
    $GLOBALS['auditActor']=safe_user($user);
    $q=auth_db()->prepare('UPDATE users SET last_login=? WHERE id=?');$q->execute([time(),$user['id']]);
}
function auth_routes(string $action): void {
    if($action==='session')json_response(['ok'=>true,'user'=>current_user(),'csrf'=>csrf_token(),'setupRequired'=>first_admin_needed()]);
    if(in_array($action,['register','setup'],true)){
        require_csrf();rate_limit('register',15,900);
        [$name,$email,$password]=validate_account();
        if($action==='setup'&&!hash_equals(ADMIN_PASSWORD,(string)($_POST['setupKey']??'')))json_response(['ok'=>false,'error'=>'Chave de configuração incorreta.'],403);
        $db=auth_db();$db->exec('BEGIN IMMEDIATE');
        try {
            if($action==='setup'&&!first_admin_needed())throw new InvalidArgumentException('O administrador inicial já foi configurado.');
            $q=$db->prepare('SELECT id FROM users WHERE email=?');$q->execute([$email]);
            if($q->fetch())throw new InvalidArgumentException('Este e-mail já está cadastrado.');
            $user=['id'=>bin2hex(random_bytes(16)),'name'=>$name,'email'=>$email,'role'=>$action==='setup'?'admin':'operator','status'=>$action==='setup'?'active':'pending'];
            $q=$db->prepare('INSERT INTO users(id,name,email,password_hash,role,status,created_at) VALUES(?,?,?,?,?,?,?)');
            $q->execute([$user['id'],$name,$email,password_hash($password,PASSWORD_DEFAULT),$user['role'],$user['status'],time()]);
            audit_event($action==='setup'?'account.setup':'account.register',['email'=>$email,'status'=>$user['status']],$user['id'],'success',$user);
            $db->exec('COMMIT');
        } catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
        if($action==='setup')establish_session($user);
        json_response(['ok'=>true,'message'=>$action==='setup'?'Administrador criado.':'Cadastro enviado. Aguarde a aprovação de um administrador.','csrf'=>csrf_token()]);
    }
    if($action==='login'){
        require_csrf();rate_limit('login',20,900);
        $email=strtolower(trim((string)($_POST['email']??'')));$password=(string)($_POST['password']??'');
        $q=auth_db()->prepare('SELECT * FROM users WHERE email=?');$q->execute([$email]);$u=$q->fetch();
        if(!$u||!password_verify($password,$u['password_hash']))json_response(['ok'=>false,'error'=>'E-mail ou senha incorretos.'],401);
        if($u['status']!=='active')json_response(['ok'=>false,'error'=>$u['status']==='pending'?'Seu cadastro aguarda aprovação de um administrador.':'Seu acesso não está autorizado. Procure um administrador.'],403);
        establish_session($u);audit_event('account.login',[],$u['id']);
        json_response(['ok'=>true,'user'=>safe_user($u),'csrf'=>csrf_token()]);
    }
    if($action==='logout'){
        require_csrf();$u=require_user();audit_event('account.logout',[],$u['id']);
        $_SESSION=[];session_destroy();json_response(['ok'=>true]);
    }
    if($action==='users'){
        require_admin();audit_event('users.view');
        $users=auth_db()->query('SELECT id,name,email,role,status,created_at,reviewed_at,reviewed_by,last_login FROM users ORDER BY CASE status WHEN \'pending\' THEN 0 ELSE 1 END,created_at DESC')->fetchAll();
        json_response(['ok'=>true,'users'=>$users]);
    }
    if($action==='user_update'){
        $admin=require_admin();require_csrf();$id=(string)($_POST['id']??'');
        $db=auth_db();$db->exec('BEGIN IMMEDIATE');
        try {
            $q=$db->prepare('SELECT * FROM users WHERE id=?');$q->execute([$id]);$old=$q->fetch();
            if(!$old)throw new InvalidArgumentException('Usuário não encontrado.');
            $role=(string)($_POST['role']??$old['role']);$status=(string)($_POST['status']??$old['status']);
            if(!in_array($role,['operator','admin'],true)||!in_array($status,['active','pending','rejected','suspended'],true))throw new InvalidArgumentException('Cargo ou situação inválidos.');
            if($old['role']==='admin'&&$old['status']==='active'&&($role!=='admin'||$status!=='active')&&(int)$db->query("SELECT count(*) FROM users WHERE role='admin' AND status='active'")->fetchColumn()<=1)throw new InvalidArgumentException('É necessário manter pelo menos um administrador ativo.');
            $q=$db->prepare('UPDATE users SET role=?,status=?,reviewed_at=?,reviewed_by=? WHERE id=?');$q->execute([$role,$status,time(),$admin['id'],$id]);
            audit_event('users.update',['name'=>$old['name'],'email'=>$old['email'],'before'=>['role'=>$old['role'],'status'=>$old['status']],'after'=>['role'=>$role,'status'=>$status]],$id);
            $db->exec('COMMIT');
        } catch(Throwable $e){$db->exec('ROLLBACK');throw $e;}
        json_response(['ok'=>true]);
    }
    if($action==='logs'){
        require_admin();
        $before=max(0,(int)($_GET['before']??0));$actor=substr((string)($_GET['actor']??''),0,64);$search=mb_substr(trim((string)($_GET['q']??'')),0,120);
        $where=['1=1'];$params=[];
        if($before){$where[]='id<?';$params[]=$before;}
        if($actor){$where[]='actor_id=?';$params[]=$actor;}
        if($search){$where[]="(action LIKE ? ESCAPE '\\' OR actor_name LIKE ? ESCAPE '\\' OR details LIKE ? ESCAPE '\\')";$like='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';$params[]= $like;$params[]=$like;$params[]=$like;}
        $q=auth_db()->prepare('SELECT * FROM audit WHERE '.implode(' AND ',$where).' ORDER BY id DESC LIMIT 51');$q->execute($params);$rows=$q->fetchAll();$more=count($rows)>50;$rows=array_slice($rows,0,50);
        foreach($rows as &$row)$row['details']=json_decode($row['details'],true);
        audit_event('audit.view',['filter'=>$search,'actor'=>$actor,'before'=>$before]);
        json_response(['ok'=>true,'logs'=>$rows,'next'=>$more?end($rows)['id']:null]);
    }
    if($action==='viewer_event'){
        require_csrf();rate_limit('viewer',360,60);
        $event=(string)($_POST['event']??'');
        if(!in_array($event,['fullscreen.enter','fullscreen.exit','media.enable','content.display'],true))throw new InvalidArgumentException('Evento inválido.');
        audit_event('viewer.'.$event,['page'=>max(0,min(300,(int)($_POST['page']??0)))],clean_text((string)($_POST['id']??''),64),'success',null,'client');
        json_response(['ok'=>true]);
    }
    if($action==='ui_event'){
        require_user();require_csrf();rate_limit('ui',180,60);
        $event=(string)($_POST['event']??'');
        if(!in_array($event,['queue.search','queue.active','queue.archived','text.open','upload.open','viewer.open','settings.preview'],true))throw new InvalidArgumentException('Evento inválido.');
        audit_event('ui.'.$event,['value'=>clean_text((string)($_POST['value']??''),120)],null,'success',null,'client');json_response(['ok'=>true]);
    }
}

function audit_state_change(array $before,array $after,bool $system=false): void {
    $changes=[];
    foreach($after['screen'] as $key=>$value)if(($before['screen'][$key]??null)!==$value)$changes['screen'][$key]=['before'=>$before['screen'][$key]??null,'after'=>$value];
    $oldItems=array_column($before['items'],null,'id');
    foreach($after['items'] as $it){
        $old=$oldItems[$it['id']]??[];$diff=[];
        foreach(['title','type','duration','delay','startsAt','endsAt','enabled','loop','volume','text','archivedAt','archiveReason','renderQuality','uploadedBy'] as $key)if(($old[$key]??null)!==($it[$key]??null))$diff[$key]=['before'=>$old[$key]??null,'after'=>$it[$key]??null];
        if(($old['pages']??[])!==($it['pages']??[]))$diff['pages']=['before'=>count($old['pages']??[]),'after'=>count($it['pages']??[])];
        if($diff)$changes['items'][]=['id'=>$it['id'],'title'=>$it['title'],'changes'=>$diff];
    }
    foreach($before['items'] as $it)if(!in_array($it['id'],array_column($after['items'],'id'),true))$changes['removed'][]=['id'=>$it['id'],'title'=>$it['title']];
    if(array_column($before['items'],'id')!==array_column($after['items'],'id'))$changes['order']=['before'=>array_column($before['items'],'id'),'after'=>array_column($after['items'],'id')];
    $action=$system?'queue.automatic':($GLOBALS['auditAction']??'system.maintenance');
    if(str_starts_with($action,'playback.')){
        $keys=array_flip(['currentId','offsetMs','page','paused','blackout']);
        $changes['playback']=['before'=>array_intersect_key(timeline($before,microtime(true)*1000),$keys),'after'=>array_intersect_key(timeline($after,microtime(true)*1000),$keys)];
    }
    if($changes||!$system)audit_event($action,$changes,null,'success',$system?['name'=>'Sistema']:null);
}
