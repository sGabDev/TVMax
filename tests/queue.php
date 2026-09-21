<?php
require __DIR__.'/../app/timeline.php';
function ensure($condition,$message){if(!$condition)throw new RuntimeException($message);}
date_default_timezone_set('UTC');
$s=['queue'=>['autoArchive'=>true],'items'=>[
 ['id'=>'a','enabled'=>true,'type'=>'text','duration'=>2,'text'=>'preserve me'],
 ['id'=>'b','enabled'=>true,'type'=>'presentation','duration'=>2,'pages'=>['p1','p2']],
 ['id'=>'c','enabled'=>true,'type'=>'text','duration'=>2,'archivedAt'=>90000,'archiveReason'=>'played'],
 ['id'=>'manual','enabled'=>true,'type'=>'text','duration'=>2,'archivedAt'=>90000,'archiveReason'=>'manual']
], 'playback'=>['anchorId'=>'a','currentId'=>'a','anchorAtMs'=>100000,'offsetMs'=>0,'paused'=>false]];
settle_queue($s,100000);
ensure(empty($s['items'][2]['archivedAt']),'Recover the earlier completion archives');
ensure(!empty($s['items'][3]['archivedAt']),'Preserve manual archives');
ensure(!settle_queue($s,102500),'Completion must not archive or write state');
ensure(timeline($s,102500)['currentId']==='b','Progress normally without archiving');
ensure(timeline($s,108000)['currentId']==='a','Queue repeats');
control_playback($s,'next',108000);control_playback($s,'select',108000,'c');
ensure(count(timeline_items($s,108000))===3,'Navigation and selection preserve the queue');
$s['items'][2]['endsAt']='1970-01-01T00:01:50';
control_playback($s,'pause',108000);ensure(settle_queue($s,110000),'Expiry applies even while paused');
ensure($s['items'][2]['archiveReason']==='expired','Expiry reason');
ensure($s['playback']['paused'],'Expiry preserves pause');
ensure($s['items'][0]['text']==='preserve me'&&count($s['items'])===4,'All content is preserved');
ensure(!settle_queue($s,110001),'No repeated writes for expired items');
echo "PASS recurring queue, navigation, expiry while paused and safe migration\n";
