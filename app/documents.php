<?php
function original_media_path(array $item): string {
    $src=(string)($item['src']??'');
    if(!preg_match('~^uploads/([a-zA-Z0-9_-]+\.[a-zA-Z0-9]+)$~D',$src,$match))throw new RuntimeException('Caminho do arquivo original inválido.');
    return UPLOAD_DIR.$match[1];
}
function prepare_document(string $source, string $key): array {
    if(!function_exists('proc_open'))throw new RuntimeException('Esta hospedagem não permite converter documentos: proc_open indisponível.');
    set_time_limit(240);
    $output=UPLOAD_DIR.$key.'-pages';
    if(!is_dir($output)) mkdir($output,0775,true);
    $environment=getenv();
    if(CONVERTER_SOFFICE!=='')$environment['TVMAX_SOFFICE']=CONVERTER_SOFFICE;
    $process=proc_open([CONVERTER_PYTHON,APP_ROOT.'/scripts/convert_document.py',$source,$output],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['file',APP_ROOT.'/data/conversion.log','a']],$pipes,APP_ROOT,$environment,['bypass_shell'=>true]);
    if(!is_resource($process)) throw new RuntimeException('Não foi possível iniciar o conversor.');
    fclose($pipes[0]);$raw=stream_get_contents($pipes[1]);fclose($pipes[1]);$code=proc_close($process);
    $result=json_decode($raw,true);
    if($code!==0 || empty($result['ok'])) throw new RuntimeException($result['error']??'Falha ao preparar as páginas. Verifique o conversor no servidor.');
    return array_map(static fn($name)=>'uploads/'.$key.'-pages/'.$name,$result['pages']);
}
