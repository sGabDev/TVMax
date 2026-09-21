<?php
// The server and assets/timeline.js use the same millisecond timeline.
function timeline_items(array $state, float $now): array {
    return array_values(array_filter($state['items'], static function ($it) use ($now) {
        return !empty($it['enabled']) && empty($it['archivedAt']) && ($it['type']??'') !== 'url'
            && (empty($it['startsAt']) || strtotime($it['startsAt'])*1000 <= $now)
            && (empty($it['endsAt']) || strtotime($it['endsAt'])*1000 > $now);
    }));
}
function page_count(array $it): int { return max(1, count($it['pages']??[])); }
function item_ms(array $it): float { return max(($it['type']??'')==='video'?.001:1,(float)($it['duration']??10))*1000*page_count($it); }
function timeline(array $state, float $now): array {
    $p=$state['playback']; $items=timeline_items($state,$now);
    $paused=!empty($p['blackout'])||(bool)($p['paused']??(($p['command']??'')==='pause'));
    $base=['paused'=>$paused,'blackout'=>(bool)($p['blackout']??false),'reloadNonce'=>$p['reloadNonce']??0];
    if (!$items) return $base+['currentId'=>null,'offsetMs'=>0,'page'=>0,'pageCount'=>0,'inDelay'=>false];
    $index=0; foreach($items as $i=>$it) if($it['id']===($p['anchorId']??$p['currentId']??null)) $index=$i;
    $elapsed=max(0,(float)($p['offsetMs']??0)+($paused?0:max(0,$now-($p['anchorAtMs']??($p['startedAt']??($now/1000))*1000))));
    $cycle=0; foreach($items as $it) $cycle+=item_ms($it)+max(0,(float)($it['delay']??0))*1000;
    $elapsed=fmod($elapsed,$cycle);
    for($n=0;$n<count($items);$n++) {
        $it=$items[$index]; $duration=item_ms($it); $span=$duration+max(0,(float)($it['delay']??0))*1000;
        if($elapsed<$span) return $base+['currentId'=>$it['id'],'offsetMs'=>$elapsed,
            'page'=>min(page_count($it)-1,(int)floor($elapsed/(max(($it['type']??'')==='video'?.001:1,(float)$it['duration'])*1000))),
            'pageCount'=>page_count($it),'inDelay'=>$elapsed >= $duration];
        $elapsed-=$span;$index=($index+1)%count($items);
    }
    return $base+['currentId'=>null,'offsetMs'=>0,'page'=>0,'pageCount'=>0,'inDelay'=>false];
}
function archive_item(array &$state,string $id,string $reason,float $now): void {
    foreach($state['items'] as &$it)if($it['id']===$id&&empty($it['archivedAt'])){
        $it['archivedAt']=$now;$it['archiveReason']=$reason;
    }
}
function settle_queue(array &$state,float $now): bool {
    $changed=false;
    $position=timeline($state,$now);
    if(($state['queue']['archivePolicy']??'')!=='validity_manual') {
        // Undo only the former automatic completion archive, never a manual removal.
        foreach($state['items'] as &$it)if(($it['archiveReason']??'')==='played'){
            unset($it['archivedAt'],$it['archiveReason']);
        }
        unset($it);
        $state['queue']=['autoArchive'=>false,'archivePolicy'=>'validity_manual'];
        $changed=true;
    }
    foreach($state['items'] as &$it){
        $end=empty($it['endsAt'])?false:strtotime($it['endsAt']);
        if(empty($it['archivedAt'])&&$end!==false&&$end*1000<=$now){
            $it['archivedAt']=$end*1000;$it['archiveReason']='expired';$changed=true;
        }
    }
    unset($it);
    $items=timeline_items($state,$now);
    if(!in_array($state['playback']['anchorId']??$state['playback']['currentId']??null,array_column($items,'id'),true)){
        $position['currentId']=$items[0]['id']??null;$position['offsetMs']=0;
        if($position['currentId']!==($state['playback']['anchorId']??null)||$changed){anchor_playback($state,$position,$now);return true;}
    } elseif($changed) anchor_playback($state,$position,$now);
    return $changed;
}
function anchor_playback(array &$state, array $position, float $now): void {
    $state['playback']=array_merge($state['playback'],$position,['anchorId'=>$position['currentId'],'anchorAtMs'=>$now]);
    unset($state['playback']['commands']);
}
function control_playback(array &$state,string $cmd,float $now,?string $id=null): void {
    $position=timeline($state,$now); $items=timeline_items($state,$now);
    $index=0;foreach($items as $i=>$it)if($it['id']===$position['currentId'])$index=$i;
    if($cmd==='select') {
        if(!in_array($id,array_column($items,'id'),true))throw new RuntimeException('Este item está fora do período de exibição ou indisponível.');
        $position['currentId']=$id;$position['offsetMs']=0;$position['paused']=false;$position['blackout']=false;
        $state['playback']['resumeAfterStandby']=false;
    } elseif($cmd==='pause') {
        $position['paused']=true;$state['playback']['resumeAfterStandby']=false;
    } elseif($cmd==='play') {
        $position['paused']=false;$position['blackout']=false;$state['playback']['resumeAfterStandby']=false;
    } elseif($cmd==='blackout') {
        if(!$position['blackout']) {
            $state['playback']['resumeAfterStandby']=!$position['paused'];
            $position['blackout']=true;$position['paused']=true;
        } else {
            $position['blackout']=false;
            $position['paused']=!($state['playback']['resumeAfterStandby']??false);
            $state['playback']['resumeAfterStandby']=false;
        }
    }
    elseif($cmd==='reload') $position['reloadNonce']++;
    elseif($items && in_array($cmd,['next','prev'],true)) {
        $it=$items[$index]; $page=$position['page']+($cmd==='next'?1:-1);
        if($page>=0 && $page<page_count($it)) $position['offsetMs']=$page*max(($it['type']??'')==='video'?.001:1,(float)$it['duration'])*1000;
        else {
            $index=($index+($cmd==='next'?1:-1)+count($items))%count($items);$it=$items[$index];
            $position['currentId']=$it['id'];
            $position['offsetMs']=$cmd==='prev'?(page_count($it)-1)*max(($it['type']??'')==='video'?.001:1,(float)$it['duration'])*1000:0;
        }
    }
    anchor_playback($state,$position,$now);
    $state['playback']['command']=$cmd;
    $state['playback']['commandNonce']=($state['playback']['commandNonce']??0)+1;
}
