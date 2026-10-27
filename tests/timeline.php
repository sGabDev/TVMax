<?php
require __DIR__.'/../app/timeline.php';
date_default_timezone_set('America/Sao_Paulo');
function check($value,$message){if(!$value)throw new RuntimeException($message);}
$state=['items'=>[
    ['id'=>'a','type'=>'presentation','enabled'=>true,'duration'=>2,'delay'=>1,'pages'=>['a','b','c']],
    ['id'=>'b','type'=>'video','enabled'=>true,'duration'=>5,'delay'=>0],
    ['id'=>'disabled','type'=>'text','enabled'=>false,'duration'=>1],
    ['id'=>'url','type'=>'url','enabled'=>true,'duration'=>1]
], 'playback'=>['anchorId'=>'a','anchorAtMs'=>100000,'offsetMs'=>0,'paused'=>false]];
$snapshots=[];
foreach([100000,102000,104500,106500,107000,112000,100000000] as $now){
    $p=timeline($state,$now);$snapshots[]=['state'=>$state,'now'=>$now,'position'=>$p];
}
check(timeline($state,104500)['page']===2,'Page progression');
check(timeline($state,106500)['inDelay'],'Delay follows final page');
check(timeline($state,107000)['currentId']==='b','Advance to next item');
control_playback($state,'pause',104500);
check(timeline($state,999999)['offsetMs']===4500.0,'Pause must freeze the timeline');
control_playback($state,'next',999999);
check(timeline($state,999999)['currentId']==='b','Next after final page');
check(timeline($state,999999)['paused'],'Next preserves pause');
control_playback($state,'prev',999999);
check(timeline($state,999999)['page']===2,'Previous enters last page');
control_playback($state,'play',1000000);
check(timeline($state,1000100)['offsetMs']===4100.0,'Resume starts at the frozen page');
control_playback($state,'blackout',1000100);
check(timeline($state,1000200)['blackout'],'Blackout is persistent');
check(timeline($state,1000200)['paused'],'Standby pauses playback');
check(timeline($state,2000000)['offsetMs']===4100.0,'Standby freezes the media position');
control_playback($state,'blackout',2000000);
check(!timeline($state,2000000)['paused'],'Leaving standby restores running playback');
check(timeline($state,2000100)['offsetMs']===4200.0,'Leaving standby resumes from the frozen instant');
control_playback($state,'pause',2000100);
control_playback($state,'blackout',2000200);
control_playback($state,'blackout',2000300);
check(timeline($state,2000400)['paused'],'Leaving standby must preserve an earlier manual pause');
control_playback($state,'blackout',2000500);
control_playback($state,'play',2000600);
check(!timeline($state,2000600)['blackout']&&!timeline($state,2000600)['paused'],'Play exits standby');
control_playback($state,'select',1000300,'a');
check(timeline($state,1000300)['offsetMs']===0.0,'Selection restarts the presentation');
$state['items']=[];check(timeline($state,10000000)['currentId']===null,'Empty playlist');
$periodState=['items'=>array_map(static fn($period)=>['id'=>$period,'type'=>'text','enabled'=>true,'duration'=>10,'dayPeriod'=>$period],['all','morning','afternoon','night']),'playback'=>['anchorId'=>'morning','anchorAtMs'=>0,'offsetMs'=>0,'paused'=>false]];
foreach(['05:59'=>'night','06:00'=>'morning','11:59'=>'morning','12:00'=>'afternoon','17:59'=>'afternoon','18:00'=>'night','23:59'=>'night','00:00'=>'night'] as $clock=>$expected){
    $now=strtotime('2026-10-27 '.$clock.':00')*1000;
    check(array_column(timeline_items($periodState,$now),'id')===['all',$expected],'Daily period boundary '.$clock);
    $snapshots[]=['state'=>$periodState,'now'=>$now,'position'=>timeline($periodState,$now)];
}
echo json_encode($snapshots);
