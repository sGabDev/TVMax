(function(root){
 'use strict';
 function activeItems(state,now){return state.items.filter(it=>it.enabled&&!it.archivedAt&&it.type!=='url'&&(!it.startsAtMs||it.startsAtMs<=now)&&(!it.endsAtMs||it.endsAtMs>now))}
 function pageCount(it){return Math.max(1,it.pages?.length||0)}
 function duration(it){return Math.max(it.type==='video'?.001:1,Number(it.duration)||10)*1000}
 function resolve(state,now){
  const p=state.playback,items=activeItems(state,now);
  if(!items.length)return {item:null,page:0,offsetMs:0,progress:0};
  let index=Math.max(0,items.findIndex(it=>it.id===(p.anchorId??p.currentId)));
  const cycle=items.reduce((sum,it)=>sum+duration(it)*pageCount(it)+Math.max(0,it.delay||0)*1000,0);
  let elapsed=Math.max(0,(p.offsetMs||0)+(p.paused?0:Math.max(0,now-p.anchorAtMs)));
  elapsed%=cycle;
  for(let n=0;n<items.length;n++){
   const it=items[index],dur=duration(it),total=dur*pageCount(it),span=total+Math.max(0,it.delay||0)*1000;
   if(elapsed<span){const page=Math.min(pageCount(it)-1,Math.floor(elapsed/dur));return {item:it,page,offsetMs:elapsed,pageOffsetMs:Math.min(dur,elapsed-page*dur),inDelay:elapsed>=total,progress:Math.min(1,(elapsed-page*dur)/dur)}}
   elapsed-=span;index=(index+1)%items.length;
  }
  return {item:null,page:0,offsetMs:0,progress:0};
 }
 root.StageTimeline={resolve,activeItems,pageCount};
 if(typeof module!=='undefined')module.exports=root.StageTimeline;
})(typeof globalThis!=='undefined'?globalThis:this);
