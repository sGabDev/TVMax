<?php
declare(strict_types=1);
require __DIR__.'/../app/smartthings.php';
require __DIR__.'/../app/smartthings_schedule.php';
function auth_db():PDO{static $db;return $db??=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);}
function audit_event(...$args):void{}
function ensure(bool $value,string $message):void{if(!$value)throw new RuntimeException($message);}
function rejects(callable $fn):void{try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Expected invalid schedule');}
function at(string $date):int{return (new DateTimeImmutable($date,new DateTimeZone('America/Sao_Paulo')))->getTimestamp();}
$temp=sys_get_temp_dir().'/tvmax-schedule-'.bin2hex(random_bytes(8));mkdir($temp);mkdir($temp.'/data');define('APP_ROOT',$temp);
try{
 $v=st_schedule_validate(['enabled'=>1,'days'=>['2','2','3'],'onTime'=>'08:00','offTime'=>'18:00']);ensure($v['days']==='[2,3]','Deduplicate weekdays');
 rejects(fn()=>st_schedule_validate(['days'=>[1],'onTime'=>'24:00']));rejects(fn()=>st_schedule_validate(['days'=>[8],'onTime'=>'08:00']));rejects(fn()=>st_schedule_validate(['days'=>[1],'onTime'=>'08:00','offTime'=>'08:00']));
 $s=['tv_id'=>'tv1','revision'=>'r1','enabled'=>1,'days'=>'[2]','on_time'=>'08:00','off_time'=>'18:00','updated_at'=>0];
 ensure(st_schedule_due($s,at('2026-09-22 08:00:30'))['command']==='on','Brasilia on time');ensure(st_schedule_due($s,at('2026-09-22 18:01:00'))['command']==='off','Off time');
 ensure(st_schedule_due($s,at('2026-09-22 07:59:59'))===null,'Never early');ensure(st_schedule_due($s,at('2026-09-22 08:05:00'))===null,'No stale catch-up');ensure(st_schedule_due($s,at('2026-09-23 08:00:00'))===null,'Selected weekdays only');
 $midnight=array_merge($s,['off_time'=>'23:59']);ensure(st_schedule_due($midnight,at('2026-09-23 00:01:00'))['command']==='off','Grace window across midnight');
 $close=array_merge($s,['off_time'=>'08:02']);ensure(st_schedule_due($close,at('2026-09-22 08:03:00'))['command']==='off','Latest command wins within grace');
 ensure(st_schedule_due(array_merge($s,['updated_at'=>at('2026-09-22 08:00:20')]),at('2026-09-22 08:00:30'))===null,'Saving does not replay an earlier event');
 $db=st_schedule_db();$db->exec("INSERT INTO smartthings_tvs VALUES('tv1','TV','device','token')");
 $insert=$db->prepare('INSERT OR REPLACE INTO smartthings_schedules VALUES(?,?,?,?,?,?,?)');$insert->execute(['tv1',1,'[2]','08:00','18:00','r1',0]);
 $sent=[];$send=function($tv,$command)use(&$sent){$sent[]=$tv['tv_id'].':'.$command;};
 st_scheduler_run($send,at('2026-09-22 08:00:05'));st_scheduler_run($send,at('2026-09-22 08:01:05'));ensure($sent===['tv1:on'],'Persistent deduplication');
 st_scheduler_run($send,at('2026-09-22 18:00:05'));ensure($sent===['tv1:on','tv1:off'],'Both daily commands');
 $failures=0;$fail=function()use(&$failures){$failures++;throw new RuntimeException('simulated failure');};
 for($minute=0;$minute<5;$minute++)st_scheduler_run($fail,at('2026-09-29 08:00:05')+$minute*60);
 ensure($failures===3,'Retry cap');
 $insert->execute(['tv1',0,'[2]','08:00','18:00','r2',0]);st_scheduler_run($send,at('2026-10-06 08:00:05'));ensure(count($sent)===2,'Disabled schedules do not run');
 $db->exec("DELETE FROM smartthings_tvs WHERE id='tv1'");ensure(st_schedule_list()===[],'Deleted TVs excluded');
 echo "PASS schedule weekdays, timezone, midnight, grace, deduplication, retries, disabling and deleted TVs\n";
}finally{if(is_file($temp.'/data/smartthings-scheduler.lock'))unlink($temp.'/data/smartthings-scheduler.lock');rmdir($temp.'/data');rmdir($temp);}
