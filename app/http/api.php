<?php
require dirname(__DIR__).'/bootstrap.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

set_exception_handler(static function(Throwable $error) { json_response(['ok'=>false,'error'=>$error instanceof InvalidArgumentException?$error->getMessage():($error instanceof RuntimeException?$error->getMessage():'Falha interna ao concluir a operacao.')],400); });
if ($action === 'state') {
    if(!empty($_SERVER['HTTP_X_STAGE_PANEL']))require_user();
    session_write_close();state_response();
}
auth_routes($action);
$actor=require_user();require_csrf();
session_write_close();
if(str_starts_with($action,'smartthings_')) {
    require_once APP_ROOT.'/app/smartthings.php';
    smartthings_routes($action);
}
require_once APP_ROOT.'/app/documents.php';
$GLOBALS['auditAction']=['save'=>'presentation.edit','upload'=>'presentation.upload','add_text'=>'presentation.text','archive'=>'presentation.archive','delete'=>'presentation.archive','restore'=>'presentation.restore','prepare'=>'presentation.quality','select'=>'playback.select'][$action]??('presentation.'.$action);

if ($action === 'upload') {
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        json_response(['ok'=>false,'error'=>'Falha no upload'], 400);
    }
    $f = $_FILES['file'];
    audit_event('upload.started',['filename'=>clean_text($f['name'],180),'bytes'=>$f['size']]);
    if ($f['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
        json_response(['ok'=>false,'error'=>'Arquivo acima do limite de '.MAX_UPLOAD_MB.' MB'], 413);
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $extension=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    $office=in_array($extension,['xlsx','xls','pptx','ppt','ppsx','pps','odp','ods'],true);
    if($office) {
        $zipTypes=['xlsx','pptx','ppsx','odp','ods'];
        if(in_array($extension,$zipTypes,true)) {
            $zip=new ZipArchive();
            if($zip->open($f['tmp_name'])!==true) throw new RuntimeException('Documento inválido.');
            $entry=$extension==='xlsx'?'xl/workbook.xml':(in_array($extension,['pptx','ppsx'],true)?'ppt/presentation.xml':'content.xml');
            $valid=$zip->locateName($entry)!==false;$zip->close();
            if(!$valid) throw new RuntimeException('O arquivo não corresponde ao formato informado.');
        } elseif(file_get_contents($f['tmp_name'],false,null,0,8)!==hex2bin('d0cf11e0a1b11ae1')) throw new RuntimeException('Documento Office inválido.');
    }
    $allowed = [
        'image/jpeg'=>'image','image/png'=>'image','image/gif'=>'image','image/webp'=>'image',
        'video/mp4'=>'video','video/webm'=>'video','video/ogg'=>'video',
        'audio/mpeg'=>'audio','audio/ogg'=>'audio','audio/wav'=>'audio',
        'application/pdf'=>'pdf'
    ];
    if (!$office && !isset($allowed[$mime])) {
        json_response(['ok'=>false,'error'=>'Formato não permitido: '.$mime], 415);
    }
    $extMap = ['image/jpeg'=>'jpg','image/png'=>'png','image/gif'=>'gif','image/webp'=>'webp',
      'video/mp4'=>'mp4','video/webm'=>'webm','video/ogg'=>'ogv','audio/mpeg'=>'mp3',
      'audio/ogg'=>'ogg','audio/wav'=>'wav','application/pdf'=>'pdf'];
    $key=bin2hex(random_bytes(10));
    $name = $key.'.'.($office?$extension:$extMap[$mime]);
    $dest = UPLOAD_DIR.$name;
    $videoDuration=null;
    if(($allowed[$mime]??null)==='video'){
        $videoDuration=filter_var($_POST['videoDuration']??null,FILTER_VALIDATE_FLOAT);
        if(!$videoDuration||!is_finite($videoDuration)||$videoDuration<=0||$videoDuration>86400)throw new InvalidArgumentException('Não foi possível identificar a duração do vídeo. Envie novamente pelo apresentador.');
    }
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        json_response(['ok'=>false,'error'=>'Não foi possível salvar o arquivo'],500);
    }

    $id = bin2hex(random_bytes(8));
    $pages=($office||$mime==='application/pdf')?prepare_document($dest,$key):[];
    $item = [
      'uploadedBy'=>['id'=>$actor['id'],'name'=>$actor['name']],
      'id'=>$id,'type'=>$pages?'presentation':$allowed[$mime],'title'=>clean_text(pathinfo($f['name'], PATHINFO_FILENAME),120),
      'pages'=>$pages,'renderQuality'=>$pages?'fullhd':null,'originalType'=>$extension,
      'src'=>'uploads/'.$name,'enabled'=>true,'duration'=>$videoDuration??10,'mediaDuration'=>$videoDuration,'delay'=>0,
      'startsAt'=>'','endsAt'=>'','loop'=>false,'volume'=>100,'createdAt'=>time()
    ];
    $s=mutate_state(static function(&$s)use($item){
        $now=microtime(true)*1000;$position=timeline($s,$now);$s['items'][]=$item;
        if(!$position['currentId']){$position['currentId']=$item['id'];$position['offsetMs']=0;}
        anchor_playback($s,$position,$now);
    });
    state_response($s);
}

if ($action === 'add_text') {
    $id = bin2hex(random_bytes(8));
    $item = [
      'uploadedBy'=>['id'=>$actor['id'],'name'=>$actor['name']],
      'id'=>$id,'type'=>'text','title'=>clean_text($_POST['title'] ?? 'Texto',120),
      'text'=>clean_text($_POST['text'] ?? '',5000),'enabled'=>true,
      'duration'=>max(1,(int)($_POST['duration'] ?? 10)),'delay'=>0,'startsAt'=>'','endsAt'=>'',
      'loop'=>false,'volume'=>100,'createdAt'=>time()
    ];
    $s=mutate_state(static function(&$s)use($item){
        $now=microtime(true)*1000;$position=timeline($s,$now);$s['items'][]=$item;
        if(!$position['currentId']){$position['currentId']=$item['id'];$position['offsetMs']=0;}
        anchor_playback($s,$position,$now);
    });
    state_response($s);
}

if ($action === 'video_duration') {
    $id=(string)($_POST['id']??'');$duration=filter_var($_POST['duration']??null,FILTER_VALIDATE_FLOAT);
    if(!$duration||!is_finite($duration)||$duration<=0||$duration>86400)throw new InvalidArgumentException('Duração de vídeo inválida.');
    $s=mutate_state(static function(&$s)use($id,$duration){
        $now=microtime(true)*1000;$position=timeline($s,$now);
        $index=array_search($id,array_column($s['items'],'id'),true);
        if($index===false||$s['items'][$index]['type']!=='video')throw new InvalidArgumentException('Vídeo não encontrado.');
        $s['items'][$index]['duration']=$duration;$s['items'][$index]['mediaDuration']=$duration;
        anchor_playback($s,$position,$now);
    });state_response($s);
}

if ($action === 'schedule') {
    $id=(string)($_POST['id']??'');$dates=[];
    foreach(['startsAt','endsAt'] as $key) {
        $value=(string)($_POST[$key]??'');
        $date=$value===''?false:DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$value);
        if($value!==''&&(!$date||$date->format('Y-m-d\TH:i')!==$value))throw new InvalidArgumentException('Data inválida. Informe dia e horário completos.');
        $dates[$key]=$value;
    }
    if($dates['startsAt']&&$dates['endsAt']&&$dates['endsAt']<=$dates['startsAt'])throw new InvalidArgumentException('O fim deve ser posterior ao início.');
    $s=mutate_state(static function(&$s)use($id,$dates){
        $index=array_search($id,array_column($s['items'],'id'),true);
        if($index===false||!empty($s['items'][$index]['archivedAt']))throw new InvalidArgumentException('Item indisponível na fila.');
        $now=microtime(true)*1000;$position=timeline($s,$now);
        foreach($dates as $key=>$value)$s['items'][$index][$key]=$value;
        anchor_playback($s,$position,$now);
    });
    state_response($s);
}

if ($action === 'save') {
    $payload=json_decode(file_get_contents('php://input'),true);
    if (!is_array($payload)) json_response(['ok'=>false,'error'=>'JSON inválido'],400);
    $s=mutate_state(static function(&$s)use($payload){
    $now=microtime(true)*1000;$position=timeline($s,$now);
    if (isset($payload['screen']) && is_array($payload['screen'])) {
        $allowed=['enabled','background','fit','showClock','showProgress','transition','transitionMs','volume','muted'];
        foreach($allowed as $k) if(array_key_exists($k,$payload['screen'])) $s['screen'][$k]=$payload['screen'][$k];
        if(!in_array($s['screen']['fit'],['width','height','contain'],true))$s['screen']['fit']='contain';
        if(!in_array($s['screen']['transition'],['fade','slide','zoom','none'],true))$s['screen']['transition']='fade';
        $s['screen']['transitionMs']=max(0,min(2000,(int)$s['screen']['transitionMs']));
    }
    if (isset($payload['items']) && is_array($payload['items'])) {
        $existing=[]; foreach($s['items'] as $it) $existing[$it['id']]=$it;
        $new=[];
        foreach($payload['items'] as $p) {
            $id=(string)($p['id']??'');
            if(!$id || !isset($existing[$id]) || !empty($existing[$id]['archivedAt'])) continue;
            $it=$existing[$id];
            foreach(['title','enabled','duration','delay','startsAt','endsAt','loop','volume','text'] as $k) {
                if(array_key_exists($k,$p)) $it[$k]=$p[$k];
            }
            $it['duration']=$it['type']==='video'&&!empty($it['mediaDuration'])?(float)$it['mediaDuration']:max(1,min(86400,(float)$it['duration']));
            $it['delay']=max(0,min(3600,(int)$it['delay']));
            $it['volume']=max(0,min(100,(int)$it['volume']));
            $new[]=$it;
            unset($existing[$id]);
        }
        $s['items']=array_merge($new,array_values($existing));
    }
    anchor_playback($s,$position,$now);
    });
    state_response($s);
}

if ($action === 'command') {
    $cmd=(string)($_POST['command']??'');
    $allowed=['play','pause','next','prev','reload','blackout'];
    if(!in_array($cmd,$allowed,true)) json_response(['ok'=>false,'error'=>'Comando inválido'],400);
    $GLOBALS['auditAction']='playback.'.$cmd;
    $s=mutate_state(static function(&$s)use($cmd){control_playback($s,$cmd,microtime(true)*1000);});
    state_response($s);
}

if ($action === 'select') {
    $id=(string)($_POST['id']??'');
    $s=mutate_state(static function(&$s)use($id){control_playback($s,'select',microtime(true)*1000,$id);});
    state_response($s);
}

if (in_array($action,['archive','delete','restore'],true)) {
    $id=(string)($_POST['id']??'');
    $s=mutate_state(static function(&$s)use($id,$action){
        $now=microtime(true)*1000;$position=timeline($s,$now);
        $index=array_search($id,array_column($s['items'],'id'),true);
        if($index===false)throw new RuntimeException('Item nao encontrado.');
        if($action==='restore'){
            $it=$s['items'][$index];unset($it['archivedAt'],$it['archiveReason']);$it['enabled']=true;
            if(!empty($it['endsAt'])&&strtotime($it['endsAt'])*1000<=$now)$it['endsAt']='';
            array_splice($s['items'],$index,1);$s['items'][]=$it;
            if(!$position['currentId']){$position['currentId']=$id;$position['offsetMs']=0;}
        } else {
            archive_item($s,$id,'manual',$now);
            if($position['currentId']===$id){$available=timeline_items($s,$now);$position['currentId']=$available[0]['id']??null;$position['offsetMs']=0;}
        }
        anchor_playback($s,$position,$now);
    });
    state_response($s);
}

if($action==='prepare') {
    $id=(string)($_POST['id']??'');$source=null;
    foreach(read_state()['items'] as $it)if($it['id']===$id&&in_array($it['type'],['pdf','presentation'],true))$source=$it;
    if(!$source)throw new RuntimeException('PDF não encontrado.');
    $pages=prepare_document(original_media_path($source),pathinfo($source['src'],PATHINFO_FILENAME).'-hd-'.bin2hex(random_bytes(4)));
    $s=mutate_state(static function(&$s)use($id,$pages){
        $now=microtime(true)*1000;$position=timeline($s,$now);
        foreach($s['items'] as &$it)if($it['id']===$id){$it['pages']=$pages;$it['type']='presentation';$it['renderQuality']='fullhd';}
        anchor_playback($s,$position,$now);
    });
    state_response($s);
}

json_response(['ok'=>false,'error'=>'Ação desconhecida'],404);
