<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/smartthings_webhook.php';
session_write_close();
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
try {
    if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){http_response_code(405);header('Allow: POST');echo '{"error":"Use POST"}';exit;}
    $c=st_oauth_read();$key=(string)($_GET['key']??'');
    if(empty($c['webhookKey'])||!hash_equals($c['webhookKey'],$key)){http_response_code(403);echo '{"error":"Webhook inválido"}';exit;}
    $raw=file_get_contents('php://input',false,null,0,262145);
    if($raw===false||strlen($raw)>262144)throw new InvalidArgumentException('Payload muito grande.');
    $body=json_decode($raw,true,32,JSON_THROW_ON_ERROR);
    if(!is_array($body))throw new InvalidArgumentException('Payload inválido.');
    $type=$body['messageType']??$body['lifecycle']??'';
    // Registration uses the unguessable Target URL and requires an administrator to open the validated confirmation link.
    if(!in_array($type,['CONFIRMATION','PING'],true)&&!st_webhook_signature($raw,$_SERVER,'st_webhook_key')){http_response_code(401);echo '{"error":"Assinatura inválida"}';exit;}
    $result=st_webhook_process($body);
    echo json_encode($result?:new stdClass(),JSON_UNESCAPED_SLASHES);
}catch(Throwable $error){http_response_code(400);echo '{"error":"Não foi possível processar o webhook. Verifique o App ID, o payload e a assinatura."}';}
