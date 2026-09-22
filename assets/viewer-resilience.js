(() => {
 'use strict';
 let timer=null,memory=[],lastState=null,lastWarm='',worker=null,connectionLost=0;
 const key='tvmax-recovery:'+location.pathname;
 function recover(reason){
  if(timer||navigator.onLine===false)return;
  const now=Date.now();let attempts=memory;
  try{attempts=JSON.parse(sessionStorage.getItem(key)||'[]');}catch{}
  attempts=Array.isArray(attempts)?attempts.filter(t=>Number.isFinite(t)&&now-t<600000):[];
  if(attempts.length>=3){globalThis.StageRecovery.notice?.('Não foi possível recuperar a reprodução. Confira a mídia ou use Recarregar visualizador no painel.');return;}
  globalThis.StageRecovery.notice?.(reason+' Recuperando a transmissão…');
  timer=setTimeout(()=>{if(navigator.onLine===false){timer=null;return;}attempts.push(Date.now());memory=attempts;try{sessionStorage.setItem(key,JSON.stringify(attempts));}catch{}location.reload();},5000);
 }
 function warm(state){
  lastState=state;if(!worker)return;
  const urls=state.items.filter(it=>it.enabled&&!it.archivedAt).flatMap(it=>it.pages?.length?it.pages:it.src?[it.src]:[]).map(src=>new URL(src,location.href).href);
  const signature=JSON.stringify(urls);if(signature===lastWarm)return;lastWarm=signature;worker.postMessage({type:'WARM_MEDIA',urls});
 }
 globalThis.StageRecovery={recover,warm,notice:null,connected(){connectionLost=0;},disconnected(){if(!connectionLost)connectionLost=Date.now();if(Date.now()-connectionLost>60000)recover('A sincronização não está respondendo.');}};
 if('serviceWorker' in navigator&&globalThis.isSecureContext){
  navigator.serviceWorker.register('viewer-cache-sw.js').then(()=>navigator.serviceWorker.ready).then(reg=>{worker=reg.active;if(lastState)warm(lastState);}).catch(()=>{});
 }
 let idle;
 const active=()=>{document.body.classList.remove('cursor-idle');clearTimeout(idle);idle=setTimeout(()=>document.body.classList.add('cursor-idle'),3000);};
 document.addEventListener('pointermove',active,{passive:true});document.addEventListener('pointerdown',active,{passive:true});active();
 setInterval(()=>{if(lastState){lastWarm='';warm(lastState);}},30*60*1000);
 globalThis.addEventListener('error',event=>{if(event instanceof ErrorEvent)recover('O visualizador encontrou um erro.');});
 globalThis.addEventListener('unhandledrejection',event=>{if(!['NotAllowedError','AbortError'].includes(event.reason?.name))recover('O visualizador encontrou uma falha.');});
})();
