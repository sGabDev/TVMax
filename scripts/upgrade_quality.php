<?php
require __DIR__.'/../app/bootstrap.php';
require __DIR__.'/../app/documents.php';
session_write_close();
foreach(read_state()['items'] as $source){
    if(!in_array($source['type'],['pdf','presentation'],true)||($source['renderQuality']??'')==='fullhd')continue;
    if(!is_file(original_media_path($source))){fwrite(STDERR,"Original ausente: ".$source['id']."\n");continue;}
    $key=pathinfo($source['src'],PATHINFO_FILENAME).'-hd-'.bin2hex(random_bytes(4));
    $pages=prepare_document(original_media_path($source),$key);
    mutate_state(static function(&$s)use($source,$pages){foreach($s['items'] as &$it)if($it['id']===$source['id']){
        $it['pages']=$pages;$it['type']='presentation';$it['renderQuality']='fullhd';
    }});
    echo 'Full HD: '.$source['id'].' ('.count($pages)." paginas)\n";
}
