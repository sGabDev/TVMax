<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../app/bootstrap.php';
session_write_close();
require __DIR__.'/../app/smartthings.php';
require __DIR__.'/../app/smartthings_schedule.php';
try{
    if(in_array('--check',$argv,true)){
        foreach(['curl','pdo_sqlite','openssl'] as $extension)if(!extension_loaded($extension))throw new RuntimeException('Extensão PHP ausente: '.$extension);
        st_schedule_db();echo "Agendador pronto. Fuso: America/Sao_Paulo. Nenhum comando enviado.\n";exit;
    }
    $result=st_scheduler_run();echo json_encode($result,JSON_UNESCAPED_UNICODE).PHP_EOL;exit($result['failed']?1:0);
}catch(Throwable $error){fwrite(STDERR,"Falha no agendador: ".$error->getMessage().PHP_EOL);exit(1);}
