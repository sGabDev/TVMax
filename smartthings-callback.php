<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/smartthings_oauth.php';
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
$success=false;
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='GET')throw new RuntimeException('O callback aceita apenas GET.');
    if(!is_admin())throw new RuntimeException('Entre como administrador no TVMax e inicie a conexão novamente.');
    $pending=$_SESSION['smartthingsOAuth']??null;unset($_SESSION['smartthingsOAuth']);session_write_close();
    st_oauth_lock(static function()use($pending){
        $c=st_oauth_read();
        if(!st_oauth_state_valid($pending,(string)($_GET['state']??''),$c))throw new RuntimeException('Autorização expirada ou inválida. Inicie a conexão novamente no TVMax.');
        if(isset($_GET['error']))throw new RuntimeException('A autorização foi cancelada ou recusada pela Samsung.');
        $code=(string)($_GET['code']??'');
        if($code===''||strlen($code)>4096)throw new RuntimeException('Código de autorização ausente ou inválido.');
        $c=st_oauth_apply_tokens($c,st_oauth_exchange($c,['grant_type'=>'authorization_code','code'=>$code,'redirect_uri'=>$c['baseUrl'].'/smartthings-callback.php']));
        st_oauth_write($c);
    });
    audit_event('smartthings.oauth_connected');$success=true;$message='Conta Samsung conectada. As TVs sem token exclusivo passam a usar OAuth.';
}catch(Throwable $error){http_response_code(400);$message=$error instanceof RuntimeException?$error->getMessage():'Não foi possível concluir a conexão OAuth.';}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>SmartThings · TVMax</title><link rel="stylesheet" href="assets/app.css"><link rel="stylesheet" href="assets/theme.css"></head><body class="admin-page"><main class="admin-shell"><section class="panel"><h1><?=$success?'Conta conectada':'Conexão não concluída'?></h1><p><?=htmlspecialchars($message,ENT_QUOTES,'UTF-8')?></p><a class="btn primary" href="apresentador.php#smartthings">Voltar às TVs</a></section></main></body></html>
