<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
session_write_close();
require __DIR__.'/app/smartthings_cron.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
try{
    if(!in_array($_SERVER['REQUEST_METHOD']??'',['GET','POST'],true)){http_response_code(405);header('Allow: GET, POST');echo '{"ok":false}';exit;}
    $supplied=$_SERVER['HTTP_X_TVMAX_CRON_KEY']??$_GET['key']??'';
    if(!is_string($supplied)||!st_cron_authorized(st_cron_key(),$supplied)){http_response_code(403);echo '{"ok":false,"error":"Chave do cron ausente ou inválida."}';exit;}
    require __DIR__.'/app/smartthings.php';require __DIR__.'/app/smartthings_schedule.php';
    set_time_limit(60);
    $result=st_scheduler_run(null,null,microtime(true)+40);
    echo json_encode(['ok'=>true]+$result,JSON_UNESCAPED_UNICODE);
}catch(Throwable $error){http_response_code(500);echo '{"ok":false,"error":"Falha ao executar o agendador. Consulte os logs do servidor."}';}
