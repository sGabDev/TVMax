'use strict';
const $=s=>document.querySelector(s),layers=[$('#layerA'),$('#layerB')];
let state=null,layer=0,visibleKey='',pendingKey='',generation=0,failedKey='',retryAt=0,cleanup=null,reloadNonce=null,lastMediaSync=-Infinity,position=null,connectionError=false;
const sync=new StageSync(s=>{
 const wasPaused=state?.playback.paused;state=s;
 if(wasPaused!==s.playback.paused)lastMediaSync=-Infinity;
 if(reloadNonce!==null&&s.playback.reloadNonce!==reloadNonce){location.reload();return}
 reloadNonce=s.playback.reloadNonce;
 if(connectionError){notice('');connectionError=false}
 update();
},()=>{connectionError=true;notice('Sem conexão. A reprodução continua; tentando sincronizar novamente…')});
function notice(text){$('#viewerNotice').textContent=text;$('#viewerNotice').hidden=!text}
function clearLayer(el){el.getAnimations?.().forEach(a=>a.cancel());el.querySelectorAll('video,audio').forEach(media=>{media.pause();media.removeAttribute('src');media.load()});el.replaceChildren()}
function createMedia(it,page){
 let el;
 if(it.pages?.length||it.type==='image') {el=document.createElement('img');el.src=it.pages?.[page]||it.src;el.alt=it.title||''}
 else if(['video','audio'].includes(it.type)){
  el=document.createElement(it.type);el.src=it.src;el.playsInline=true;el.preload='auto';el.loop=!!it.loop;
  el.addEventListener('loadedmetadata',()=>{lastMediaSync=-Infinity;update()});
 } else {
  el=document.createElement('div');el.className='text-slide';
  const title=document.createElement('h1'),body=document.createElement('p');title.textContent=it.title||'';
  body.textContent=it.type==='pdf'?'Prepare este PDF em modo apresentação no painel.':it.text||'';
  el.append(title,body);
 }
 el.addEventListener('error',()=>notice('Não foi possível carregar '+it.title+'. Verifique o arquivo no apresentador.'));
 return el;
}
async function show(it,page,key){
 const request=++generation;pendingKey=key;
 const media=createMedia(it,page);
 if(['IMG','VIDEO'].includes(media.tagName)){
  try{await new Promise((resolve,reject)=>{
   const timeout=setTimeout(()=>reject(new Error('Tempo de carregamento excedido')),10000);
   const done=()=>{clearTimeout(timeout);resolve()};
   const fail=()=>{clearTimeout(timeout);reject(new Error('Falha ao carregar mídia'))};
   if(media.tagName==='IMG'&&media.decode)media.decode().then(done,fail);
   else if(media.readyState>=2||media.complete&&media.naturalWidth)done();
   else{media.addEventListener(media.tagName==='IMG'?'load':'loadeddata',done,{once:true});media.addEventListener('error',fail,{once:true})}
  })}catch{if(request===generation){pendingKey='';failedKey=key;retryAt=performance.now()+5000;notice('Não foi possível carregar '+it.title+'. Tentando novamente…')}return}
 }
 if(request!==generation)return;
 pendingKey='';failedKey='';
 clearTimeout(cleanup);$('#enableMedia').hidden=true;
 if(!connectionError)notice('');
 const old=layers[layer],next=1-layer,target=layers[next];
 old.querySelectorAll('video,audio').forEach(el=>el.pause());
 clearLayer(target);target.appendChild(media);StageMediaLayout.apply(target,state.screen.fit);
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
 $('#blackout').classList.toggle('on',!!p.blackout);
 $('#blackout').setAttribute('aria-hidden',String(!p.blackout));
 if(p.blackout){$('#enableMedia').hidden=true;layers.forEach(el=>el.querySelectorAll('video,audio').forEach(media=>media.pause()))}
 $('#stage').style.background=screen.background||'#000';
 $('#clock').style.display=screen.showClock&&!p.blackout?'block':'none';
 $('#clock').textContent=new Date(sync.now()).toLocaleTimeString('pt-BR',{hour:'2-digit',minute:'2-digit',timeZone:'America/Sao_Paulo'});
 $('#progressWrap').style.display=screen.showProgress&&it&&!p.blackout?'block':'none';
 $('#progress').style.width=(position.progress*100)+'%';
 $('#empty').style.display=it&&visibleKey?'none':'flex';
 if(!it){if(visibleKey||pendingKey){generation++;pendingKey='';clearTimeout(cleanup);layers.forEach(clearLayer);visibleKey=''}return}
 const key=it.id+':'+position.page+':'+(it.pages?.[position.page]||it.src||it.text)+':'+it.title;
 if(pendingKey&&pendingKey!==key){generation++;pendingKey=''}
 if(key!==visibleKey&&key!==pendingKey&&(key!==failedKey||performance.now()>=retryAt))show(it,position.page,key);
 layers.forEach(el=>StageMediaLayout.apply(el,screen.fit));
 if(key!==visibleKey){layers[layer].querySelectorAll('video,audio').forEach(el=>el.pause());return}
 if(performance.now()-lastMediaSync<200)return;lastMediaSync=performance.now();
 layers[layer].querySelectorAll('video,audio').forEach(el=>{
  el.volume=Math.max(0,Math.min(1,(screen.volume??70)/100*(it.volume??100)/100));el.muted=!!screen.muted;
  if(el.readyState<1)return;
  let seconds=position.offsetMs/1000;
  const finished=!it.loop&&Number.isFinite(el.duration)&&seconds>=el.duration;
  if(Number.isFinite(el.duration)&&el.duration>0)seconds=it.loop?seconds%el.duration:Math.min(seconds,Math.max(0,el.duration-.03));
  if(Math.abs(el.currentTime-seconds)>(p.paused ? .03 : .25))el.currentTime=seconds;
  if(p.paused||p.blackout||position.inDelay||finished)el.pause();
  else if(el.paused&&!el.dataset.starting){el.dataset.starting='1';el.play().then(()=>{if(state.playback.paused||state.playback.blackout)el.pause();$('#enableMedia').hidden=true}).catch(()=>{$('#enableMedia').hidden=!!(state.playback.paused||state.playback.blackout)}).finally(()=>{delete el.dataset.starting})}
 });
}
$('#enableMedia').onclick=()=>{viewerEvent('media.enable');layers[layer].querySelectorAll('video,audio').forEach(el=>{if(!state?.playback.paused&&!state?.playback.blackout)el.play().catch(()=>{})});lastMediaSync=-Infinity;update()};
$('#fsBtn').onclick=async()=>{try{if(document.fullscreenElement)await document.exitFullscreen();else await document.documentElement.requestFullscreen()}catch{notice('Tela cheia indisponível neste navegador.')}};
document.addEventListener('keydown',e=>{if(e.key.toLowerCase()==='f')$('#fsBtn').click()});
setInterval(update,50);sync.start();
document.addEventListener('fullscreenchange',()=>viewerEvent(document.fullscreenElement?'fullscreen.enter':'fullscreen.exit'));
function viewerEvent(event,id='',page=0){if(!globalThis.StageAuth?.csrf)return;const body=new FormData();body.append('event',event);body.append('id',id);body.append('page',page);fetch('api.php?action=viewer_event',{method:'POST',headers:{'X-CSRF-Token':StageAuth.csrf},body,keepalive:true}).catch(()=>{})}
