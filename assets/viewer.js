'use strict';
const $=s=>document.querySelector(s),layers=[$('#layerA'),$('#layerB')];
let audioEnabled=false,activatingAudio=false;
const mediaPool=[{},{}];
function pooledMedia(slot,type){
 if(!mediaPool[slot][type]){
  const el=document.createElement(type);el.playsInline=true;el.preload='auto';
  el.addEventListener('loadedmetadata',()=>{lastMediaSync=-Infinity;update()});
  el.addEventListener('waiting',()=>{el.dataset.waitingAt ||= String(performance.now());});
  el.addEventListener('stalled',()=>{el.dataset.waitingAt ||= String(performance.now());});
  el.addEventListener('playing',()=>{delete el.dataset.waitingAt;});
  mediaPool[slot][type]=el;
 }
 return mediaPool[slot][type];
}
function audioPrompt(){const button=$('#enableMedia');button.hidden=audioEnabled;$('#audioHelp').hidden=audioEnabled;button.disabled=activatingAudio;button.textContent=activatingAudio?'Ativando som…':'♪ Ativar som'}
function playbackError(error,el,source){
 if(el.src!==source||error.name==='AbortError')return;
 if(error.name==='NotAllowedError'){audioEnabled=false;audioPrompt();$('#audioHelp').textContent='Toque em Ativar som para permitir o áudio.'}
 else{el.dataset.playFailed='1';notice('Não foi possível reproduzir esta mídia.');globalThis.StageRecovery?.recover('Falha ao reproduzir a mídia.')}
}
let state=null,layer=0,visibleKey='',pendingKey='',generation=0,failedKey='',retryAt=0,cleanup=null,reloadNonce=null,lastMediaSync=-Infinity,position=null,connectionError=false;
const sync=new StageSync(s=>{
 const wasPaused=state?.playback.paused;state=s;
 globalThis.StageRecovery?.connected();globalThis.StageRecovery?.warm(s);
 if(wasPaused!==s.playback.paused)lastMediaSync=-Infinity;
 if(reloadNonce!==null&&s.playback.reloadNonce!==reloadNonce){location.reload();return}
 reloadNonce=s.playback.reloadNonce;
 if(connectionError){notice('');connectionError=false}
 update();
},()=>{connectionError=true;notice('Sem conexão. A reprodução continua; tentando sincronizar novamente…');globalThis.StageRecovery?.disconnected()});
function notice(text){$('#viewerNotice').textContent=text;$('#viewerNotice').hidden=!text}
if(globalThis.StageRecovery)StageRecovery.notice=notice;
function clearLayer(el,keep=null){el.getAnimations?.().forEach(a=>a.cancel());el.querySelectorAll('video,audio').forEach(media=>{if(media===keep)return;media.pause();media.removeAttribute('src');media.load()});el.replaceChildren()}
function createMedia(it,page){
 let el;
 if(it.pages?.length||it.type==='image') {el=document.createElement('img');el.src=it.pages?.[page]||it.src;el.alt=it.title||''}
 else if(['video','audio'].includes(it.type)){
  el=pooledMedia(1-layer,it.type);el.pause();delete el.dataset.playFailed;delete el.dataset.starting;delete el.dataset.waitingAt;
  el.src=it.src;el.loop=!!it.loop;el.muted=!audioEnabled||!!state.screen.muted;
 } else {
  el=document.createElement('div');el.className='text-slide';
  const title=document.createElement('h1'),body=document.createElement('p');title.textContent=it.title||'';
  body.textContent=it.type==='pdf'?'Prepare este PDF em modo apresentação no painel.':it.text||'';
  el.append(title,body);
 }
 el.onerror=()=>{if(!el.getAttribute('src'))return;notice('Não foi possível carregar '+it.title+'.');globalThis.StageRecovery?.recover('Falha ao carregar a mídia.')};
 return el;
}
async function show(it,page,key){
 clearTimeout(cleanup);
 const request=++generation;pendingKey=key;
 const media=createMedia(it,page);
 if(['IMG','VIDEO','AUDIO'].includes(media.tagName)){
  try{await new Promise((resolve,reject)=>{
   const timeout=setTimeout(()=>reject(new Error('Tempo de carregamento excedido')),30000);
   const done=()=>{clearTimeout(timeout);resolve()};
   const fail=()=>{clearTimeout(timeout);reject(new Error('Falha ao carregar mídia'))};
   if(media.tagName==='IMG'&&media.decode)media.decode().then(done,fail);
   else if(media.readyState>=2||media.complete&&media.naturalWidth)done();
   else{media.addEventListener(media.tagName==='IMG'?'load':'loadeddata',done,{once:true});media.addEventListener('error',fail,{once:true})}
  })}catch{if(request===generation){pendingKey='';failedKey=key;retryAt=performance.now()+5000;notice('Não foi possível carregar '+it.title+'. Tentando novamente…');globalThis.StageRecovery?.recover('O carregamento da mídia falhou.')}return}
 }
 if(request!==generation)return;
 pendingKey='';failedKey='';
 clearTimeout(cleanup);audioPrompt();
 if(!connectionError)notice('');
 const old=layers[layer],next=1-layer,target=layers[next];
 old.querySelectorAll('video,audio').forEach(el=>el.pause());
 clearLayer(target,media);target.appendChild(media);StageMediaLayout.apply(target,state.screen.fit);
 const transition=state.screen.transition,reduced=typeof matchMedia==='function'&&matchMedia('(prefers-reduced-motion: reduce)').matches;
 const ms=transition==='none'||reduced?0:Math.min(Math.max(0,state.screen.transitionMs??650),Math.max(0,it.duration*1000-(position?.pageOffsetMs||0)));
 old.getAnimations?.().forEach(a=>a.cancel());target.style.transform='';old.style.transform='';
 target.style.transitionDuration=ms+'ms';target.classList.add('active');old.classList.remove('active');
 if(ms&&target.animate){
  target.style.transitionDuration='0ms';old.style.transitionDuration='0ms';
  const from=transition==='slide'?'translateX(6%)':transition==='zoom'?'scale(.96)':'none';
  const to=transition==='slide'?'translateX(-3%)':transition==='zoom'?'scale(1.025)':'none';
  const options={duration:ms,easing:'cubic-bezier(.22,.61,.36,1)'};
  target.animate([{opacity:0,transform:from},{opacity:1,transform:'none'}],options);
  old.animate([{opacity:1,transform:'none'},{opacity:0,transform:to}],options);
 }
 layer=next;visibleKey=key;lastMediaSync=-Infinity;cleanup=setTimeout(()=>clearLayer(old),ms);
 $('#empty').style.display='none';
 viewerEvent('content.display',it.id,page+1);
 if(it.pages?.[page+1]){const preload=new Image();preload.src=it.pages[page+1]}
}
function update(){
 if(!state)return;
 position=StageTimeline.resolve(state,sync.now());const it=position.item,p=state.playback,screen=state.screen;
 const paused=!!(p.paused||p.blackout);
 $('#blackout').classList.toggle('on',paused);
 $('#blackout').setAttribute('aria-hidden',String(!paused));
 audioPrompt();
 if(paused){layers.forEach(el=>el.querySelectorAll('video,audio').forEach(media=>media.pause()))}
 $('#stage').style.background=screen.background||'#000';
 $('#clock').style.display=screen.showClock&&!paused?'block':'none';
 $('#clock').textContent=new Date(sync.now()).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit',timeZone:'America/Sao_Paulo'});
 $('#progressWrap').style.display=screen.showProgress&&it&&!paused?'block':'none';
 $('#progress').style.width=(position.progress*100)+'%';
 $('#empty').style.display=it&&visibleKey?'none':'flex';
 if(!it){if(visibleKey||pendingKey){generation++;pendingKey='';clearTimeout(cleanup);layers.forEach(clearLayer);visibleKey=''}return}
 const key=it.id+':'+position.page+':'+(it.pages?.[position.page]||it.src||it.text)+':'+it.title;
 if(pendingKey&&pendingKey!==key){generation++;pendingKey=''}
 if(key!==visibleKey&&key!==pendingKey&&(key!==failedKey||performance.now()>=retryAt))show(it,position.page,key);
 layers.forEach(el=>StageMediaLayout.apply(el,screen.fit));
 if(key!==visibleKey){layers[layer].querySelectorAll('video,audio').forEach(el=>el.pause());return}
 if(activatingAudio||performance.now()-lastMediaSync<200)return;lastMediaSync=performance.now();
 layers[layer].querySelectorAll('video,audio').forEach(el=>{
  el.volume=Math.max(0,Math.min(1,(screen.volume??70)/100*(it.volume??100)/100));el.muted=!audioEnabled||!!screen.muted;
  if(!paused&&!position.inDelay&&el.dataset.waitingAt&&performance.now()-Number(el.dataset.waitingAt)>25000)globalThis.StageRecovery?.recover('A mídia ficou sem responder.');
  if(paused)delete el.dataset.waitingAt;
  if(el.readyState<1)return;
  let seconds=position.offsetMs/1000;
  const finished=!it.loop&&Number.isFinite(el.duration)&&seconds>=el.duration;
  if(Number.isFinite(el.duration)&&el.duration>0)seconds=it.loop?seconds%el.duration:Math.min(seconds,Math.max(0,el.duration-.03));
  if(!el.seeking&&el.readyState>=2&&Math.abs(el.currentTime-seconds)>(p.paused ? .03 : .75))el.currentTime=seconds;
  if(p.paused||p.blackout||position.inDelay||finished)el.pause();
  else if(el.paused&&!el.dataset.starting&&!el.dataset.playFailed){const source=el.src;el.dataset.starting='1';el.play().then(()=>{if(el.src===source&&(state.playback.paused||state.playback.blackout))el.pause()}).catch(error=>playbackError(error,el,source)).finally(()=>{if(el.src===source)delete el.dataset.starting})}
 });
}
$('#enableMedia').onclick=async()=>{
 if(activatingAudio)return;activatingAudio=true;audioPrompt();
 // All play calls happen in the click gesture, including players for future items.
 const attempts=[];
 for(let slot=0;slot<2;slot++)for(const type of ['video','audio']){
  const el=pooledMedia(slot,type),hasMedia=!!el.getAttribute('src');
  if(!hasMedia)el.src='assets/audio-ready.wav';
  const source=el.src;delete el.dataset.playFailed;el.muted=false;
  const current=hasMedia&&layers[layer].contains(el),shouldPlay=current&&state&&!state.playback.paused&&!state.playback.blackout&&!position?.inDelay;
  el.volume=shouldPlay?Math.max(0,Math.min(1,(state.screen.volume??70)/100*(position?.item?.volume??100)/100)):0;
  if(shouldPlay)el.muted=!!state.screen.muted;
  try{attempts.push(Promise.resolve(el.play()).then(()=>{if(el.src===source&&(!shouldPlay||state?.playback.paused||state?.playback.blackout))el.pause()}).catch(error=>{if(error.name!=='AbortError')throw error}))}catch(error){attempts.push(Promise.reject(error))}
 }
 let activationTimeout;
 try{await Promise.race([Promise.all(attempts),new Promise((_,reject)=>{activationTimeout=setTimeout(()=>reject(new Error('Tempo de ativação excedido')),10000)})]);audioEnabled=true;notice(state?.screen.muted?'Áudio habilitado. O apresentador está com o som silenciado.':'');viewerEvent('media.enable')}
 catch{audioEnabled=false;$('#audioHelp').textContent='Toque novamente ou confira a permissão de som.'}
 finally{clearTimeout(activationTimeout);activatingAudio=false;audioPrompt();lastMediaSync=-Infinity;update()}
};
audioPrompt();
$('#fsBtn').onclick=async()=>{try{if(document.fullscreenElement)await document.exitFullscreen();else await document.documentElement.requestFullscreen()}catch{notice('Tela cheia indisponível neste navegador.')}};
document.addEventListener('keydown',e=>{if(e.key.toLowerCase()==='f')$('#fsBtn').click()});
setInterval(update,50);sync.start();
document.addEventListener('fullscreenchange',()=>viewerEvent(document.fullscreenElement?'fullscreen.enter':'fullscreen.exit'));
function viewerEvent(event,id='',page=0){if(!globalThis.StageAuth?.csrf)return;const body=new FormData();body.append('event',event);body.append('id',id);body.append('page',page);fetch('api.php?action=viewer_event',{method:'POST',headers:{'X-CSRF-Token':StageAuth.csrf},body,keepalive:true}).catch(()=>{})}
