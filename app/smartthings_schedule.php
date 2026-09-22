<?php
declare(strict_types=1);
function st_schedule_db(): PDO {
    $db=st_db();
    $db->exec('CREATE TABLE IF NOT EXISTS smartthings_schedules (tv_id TEXT PRIMARY KEY, enabled INTEGER NOT NULL, days TEXT NOT NULL, on_time TEXT NOT NULL, off_time TEXT NOT NULL, revision TEXT NOT NULL, updated_at INTEGER NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS smartthings_schedule_runs (event_key TEXT PRIMARY KEY, tv_id TEXT NOT NULL, command TEXT NOT NULL, due_at INTEGER NOT NULL, attempted_at INTEGER NOT NULL, attempts INTEGER NOT NULL, outcome TEXT NOT NULL, message TEXT NOT NULL)');
    $db->exec('CREATE TABLE IF NOT EXISTS smartthings_scheduler (id INTEGER PRIMARY KEY, heartbeat INTEGER NOT NULL)');
    return $db;
}
function st_schedule_validate(array $input): array {
    $days=$input['days']??[];
    if(!is_array($days))throw new InvalidArgumentException('Selecione os dias da semana.');
    foreach($days as $day)if(!in_array((string)$day,['1','2','3','4','5','6','7'],true))throw new InvalidArgumentException('Dia inválido.');
    $days=array_values(array_unique(array_map('intval',$days)));sort($days);
    $on=(string)($input['onTime']??'');$off=(string)($input['offTime']??'');
    foreach([$on,$off] as $time)if($time!==''&&!preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D',$time))throw new InvalidArgumentException('Horário inválido. Use HH:MM.');
    if(!$days||($on===''&&$off===''))throw new InvalidArgumentException('Escolha os dias e pelo menos um horário.');
    if($on!==''&&$on===$off)throw new InvalidArgumentException('Os horários de ligar e desligar precisam ser diferentes.');
    return ['enabled'=>!empty($input['enabled'])?1:0,'days'=>json_encode($days),'on_time'=>$on,'off_time'=>$off];
}
function st_schedule_list(): array {
    $db=st_schedule_db();$rows=$db->query('SELECT s.* FROM smartthings_schedules s JOIN smartthings_tvs t ON t.id=s.tv_id')->fetchAll();
    foreach($rows as &$row){$row['days']=json_decode($row['days'],true);$q=$db->prepare('SELECT command,attempted_at,outcome,message FROM smartthings_schedule_runs WHERE tv_id=? ORDER BY attempted_at DESC LIMIT 1');$q->execute([$row['tv_id']]);$row['lastRun']=$q->fetch()?:null;}
    return $rows;
}
function st_scheduler_health(): array {
    $last=(int)st_schedule_db()->query('SELECT heartbeat FROM smartthings_scheduler WHERE id=1')->fetchColumn();
    return ['lastSeen'=>$last,'active'=>$last>time()-180,'timezone'=>'America/Sao_Paulo'];
}
function st_schedule_save_route(): never {
    require_admin();$db=st_schedule_db();$id=(string)($_POST['id']??'');
    $q=$db->prepare('SELECT id FROM smartthings_tvs WHERE id=?');$q->execute([$id]);if(!$q->fetch())throw new InvalidArgumentException('TV não encontrada.');
    $input=$_POST;$input['days']=explode(',',(string)($_POST['days']??''));$s=st_schedule_validate($input);
    $q=$db->prepare('INSERT OR REPLACE INTO smartthings_schedules(tv_id,enabled,days,on_time,off_time,revision,updated_at) VALUES(?,?,?,?,?,?,?)');
    $q->execute([$id,$s['enabled'],$s['days'],$s['on_time'],$s['off_time'],bin2hex(random_bytes(8)),time()]);
    audit_event('smartthings.schedule_saved',['enabled'=>$s['enabled'],'days'=>$s['days'],'on'=>$s['on_time'],'off'=>$s['off_time']],$id);json_response(['ok'=>true]);
}
// Only the most recent event in a five-minute grace window is eligible. Never replay a day's old commands.
function st_schedule_due(array $s,int $now): ?array {
    if(empty($s['enabled']))return null;
    $days=json_decode($s['days'],true);$today=(new DateTimeImmutable('@'.$now))->setTimezone(new DateTimeZone('America/Sao_Paulo'));$events=[];
    foreach([$today->modify('-1 day'),$today] as $date){
        if(!in_array((int)$date->format('N'),$days,true))continue;
        foreach(['on'=>'on_time','off'=>'off_time'] as $command=>$field){
            if($s[$field]==='')continue;
            [$hour,$minute]=array_map('intval',explode(':',$s[$field]));$due=$date->setTime($hour,$minute)->getTimestamp();
            if($due<=$now&&$due>$now-300&&$due>=($s['updated_at']??0))$events[]=['command'=>$command,'due'=>$due,'key'=>$s['tv_id'].':'.$s['revision'].':'.$due.':'.$command];
        }
    }
    usort($events,fn($a,$b)=>$b['due']<=>$a['due']);return $events[0]??null;
}
function st_scheduler_run(?callable $send=null,?int $now=null,?float $deadline=null): array {
    $db=st_schedule_db();$lock=fopen(APP_ROOT.'/data/smartthings-scheduler.lock','c');
    if(!$lock)throw new RuntimeException('Não foi possível abrir o bloqueio do agendador.');
    if(!flock($lock,LOCK_EX|LOCK_NB)){fclose($lock);return ['sent'=>0,'failed'=>0,'busy'=>true];}
    $counts=['sent'=>0,'failed'=>0,'busy'=>false];
    try{
        $stamp=$now??time();$q=$db->prepare('INSERT OR REPLACE INTO smartthings_scheduler(id,heartbeat) VALUES(1,?)');$q->execute([$stamp]);
        $rows=$db->query('SELECT s.*,t.name,t.device_id,t.token FROM smartthings_schedules s JOIN smartthings_tvs t ON t.id=s.tv_id WHERE s.enabled=1')->fetchAll();
        foreach($rows as $s){
            if($deadline!==null&&microtime(true)>$deadline)break;
            $tick=$now??time();$event=st_schedule_due($s,$tick);if(!$event)continue;
            $q=$db->prepare('SELECT * FROM smartthings_schedule_runs WHERE event_key=?');$q->execute([$event['key']]);$run=$q->fetch();
            if($run&&($run['outcome']==='sent'||$run['attempts']>=3||$tick-$run['attempted_at']<60))continue;
            $check=$db->prepare('SELECT revision FROM smartthings_schedules WHERE tv_id=? AND enabled=1');$check->execute([$s['tv_id']]);if($check->fetchColumn()!==$s['revision'])continue;
            $q=$db->prepare('INSERT OR REPLACE INTO smartthings_schedule_runs(event_key,tv_id,command,due_at,attempted_at,attempts,outcome,message) VALUES(?,?,?,?,?,?,?,?)');
            $q->execute([$event['key'],$s['tv_id'],$event['command'],$event['due'],$tick,($run['attempts']??0)+1,'sending','Enviando comando']);
            try{($send??'st_power')($s,$event['command']);$outcome='sent';$message='Comando aceito pelo SmartThings.';$counts['sent']++;}
            catch(Throwable $error){$outcome='failed';$message=$error instanceof RuntimeException?$error->getMessage():'Falha interna ao enviar o comando.';$counts['failed']++;}
            $q=$db->prepare('UPDATE smartthings_schedule_runs SET outcome=?,message=? WHERE event_key=?');$q->execute([$outcome,$message,$event['key']]);
            audit_event('smartthings.scheduled_power',['command'=>$event['command'],'outcome'=>$outcome,'message'=>$message],$s['tv_id'],$outcome==='sent'?'success':'failure');
        }
        $q=$db->prepare('DELETE FROM smartthings_schedule_runs WHERE attempted_at<?');$q->execute([$stamp-30*86400]);
        return $counts;
    }finally{flock($lock,LOCK_UN);fclose($lock);}
}
