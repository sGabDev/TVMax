<?php
function delete_uploaded_media(array $item,array $items): void {
    if(empty($item['src']))return;
    $original=original_media_path($item);
    $key=pathinfo($original,PATHINFO_FILENAME);
    $root=realpath(UPLOAD_DIR);
    if($root===false)throw new RuntimeException('Pasta de uploads indisponível.');
    $paths=[$original];$directories=[];
    // Include older renders left behind by the quality upgrade action.
    foreach(glob(UPLOAD_DIR.$key.'*-pages',GLOB_ONLYDIR)?:[] as $dir){
        if(!preg_match('/^'.preg_quote($key,'/').'(?:-hd-[a-f0-9]+)?-pages$/D',basename($dir)))continue;
        if(is_link($dir)||dirname(realpath($dir)?:'')!==$root)throw new RuntimeException('Pasta de páginas inválida.');
        $directories[]=$dir;
        foreach(new DirectoryIterator($dir) as $file)if(!$file->isDot())$paths[]=$file->getPathname();
    }
    foreach($item['pages']??[] as $page)$paths[]=APP_ROOT.'/'.$page;
    $paths=array_unique($paths);$files=[];
    foreach($paths as $path){
        if(!file_exists($path)&&!is_link($path))continue;
        $resolved=realpath($path);
        if(is_link($path)||!is_file($path)||!$resolved||!str_starts_with($resolved,$root.DIRECTORY_SEPARATOR))throw new RuntimeException('Caminho de mídia inválido.');
        foreach($items as $other){
            if($other['id']===$item['id'])continue;
            foreach(array_merge([$other['src']??''],$other['pages']??[]) as $src){
                if($src!==''&&realpath(APP_ROOT.'/'.$src)===$resolved)throw new RuntimeException('Este arquivo é utilizado por outro item.');
            }
        }
        if(!is_writable($path)||!is_writable(dirname($path)))throw new RuntimeException('Sem permissão para apagar a mídia.');
        $files[$resolved]=$resolved;
    }
    foreach($files as $file)if(!unlink($file))throw new RuntimeException('Não foi possível apagar a mídia. Tente novamente.');
    foreach($directories as $dir)if(!rmdir($dir))throw new RuntimeException('Não foi possível apagar a pasta de páginas. Tente novamente.');
}

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
