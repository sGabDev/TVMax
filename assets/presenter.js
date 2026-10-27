let state=null, saveTimer=null,dirty=false,busy=false,renderedVersion=-1;
const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
const toast=m=>{const t=$('#toast');t.textContent=m;t.classList.add('show');setTimeout(()=>t.classList.remove('show'),1800)};
const sync=new StageSync(s=>{
 const changed=s.version!==renderedVersion;state=s;
 if(changed&&!busy&&!dirty&&(!document.activeElement?.matches('input,textarea,select')||document.activeElement.id==='queueSearch'))render();
 renderLive();$('#connectionLabel').textContent='Servidor sincronizado';
},()=>{$('#connectionLabel').textContent='Sem conex\u00e3o - tentando novamente'});
async function api(action,opts={}){return sync.request(action,opts)}
async function load(){await api('state')}
function renderLive(){
 if(!state)return;
 const pos=StageTimeline.resolve(state,sync.now()),it=pos.item,p=state.playback;
 $('#nowLabel').textContent=it?((p.paused||p.blackout)?'Pausado em: ':'No ar: ')+it.title+(it.pages?.length?' - P\u00e1gina '+(pos.page+1)+'/'+it.pages.length:''):'Adicione um arquivo para começar';
 $('#playbackStatus').textContent=p.blackout?'Aguardando • apresentação pausada':!it?'Aguardando conte\u00fado':p.paused?'Pausado':pos.inDelay?'Intervalo entre itens':'Reproduzindo';
 $('#playbackStatus').dataset.mode=p.blackout?'blackout':p.paused?'paused':'playing';
 const action=p.paused||p.blackout||!it?'play':'pause',label=action==='play'?'Reproduzir':'Pausar';
 $('#playPause').dataset.cmd=action;$('#playPause').title=label;$('#playPause').setAttribute('aria-label',label);
 $('#playPauseIcon').textContent=action==='play'?'▶':'Ⅱ';$('#playPauseLabel').textContent=label;
 if($('#standbyButton')){
  $('#standbyLabel').textContent=p.blackout?'Voltar':'Pausar';
  $('#standbyButton').title=p.blackout?'Retomar transmissão':'Pausar transmissão';
  $('#standbyButton').setAttribute('aria-label',$('#standbyButton').title);
 }
 $$('[data-cmd]').forEach(b=>{const cmd=b.dataset.cmd;b.disabled=busy||(!it&&['play','pause','next','prev'].includes(cmd));if(cmd==='blackout')b.setAttribute('aria-pressed',String(!!p.blackout));else b.removeAttribute('aria-pressed')});
 $$('.item').forEach(el=>{const current=el.dataset.id===it?.id;el.classList.toggle('playing',current);const item=state.items.find(x=>x.id===el.dataset.id),status=el.querySelector('.item-status');if(status)status.textContent=current?'No ar':item?.startsAtMs>sync.now()?'Agendado':item?.endsAtMs&&item.endsAtMs<=sync.now()?'Validade encerrada':item&&!StageTimeline.periodActive(item,sync.now())?'Aguardando período':item?.enabled===false?'Desativado':'Na fila'});
}
function esc(s=''){return String(s).replace(/[&<>"']/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]))}
function periodName(value){return ({all:'Todos os horários',morning:'Manhã · 06h às 12h',afternoon:'Tarde · 12h às 18h',night:'Noite · 18h às 06h'})[value]||'Todos os horários'}
let previewItem=null,previewIndex=0;
async function openPreview(id){
 if(dirty)await save();
 let item=state.items.find(it=>it.id===id);
 if(item.type==='pdf'&&!item.pages?.length){const fd=new FormData();fd.append('id',id);toast('Preparando páginas para visualizar…');await api('prepare',{method:'POST',body:fd});item=state.items.find(it=>it.id===id)}
 previewItem=item;previewIndex=0;renderPreview();modal('#mediaModal');$('#mediaModal .modal-x').focus();
}
function renderPreview(){
 const it=previewItem,box=$('#mediaPreview');box.replaceChildren();$('#mediaTitle').textContent=it.title;
 let node;
 if(it.pages?.length||it.type==='image'){node=document.createElement('img');node.src=it.pages?.[previewIndex]||it.src;node.alt=it.title+' — página '+(previewIndex+1)}
 else if(['video','audio'].includes(it.type)){node=document.createElement(it.type);node.src=it.src;node.controls=true;node.preload='metadata'}
 else{node=document.createElement('p');node.className='preview-text';node.textContent=it.text||'A prévia deste arquivo ainda não está disponível.'}
 box.appendChild(node);const count=Math.max(1,it.pages?.length||0);$('#previewPage').textContent=`Página ${previewIndex+1} de ${count}`;$('#previewPrev').disabled=previewIndex===0;$('#previewNext').disabled=previewIndex>=count-1;
}
$('#previewPrev').onclick=()=>{if(previewIndex>0){previewIndex--;renderPreview()}};
$('#previewNext').onclick=()=>{if(previewIndex+1<(previewItem?.pages?.length||0)){previewIndex++;renderPreview()}};
$('#cancelRename').onclick=()=>modal('#renameModal',false);
$('#renameTitle').oninput=()=>{$('#renameError').textContent='';$('#renameTitle').removeAttribute('aria-invalid');$('#renameCount').textContent=$('#renameTitle').value.length+'/120'};
$('#renameForm').onsubmit=event=>{event.preventDefault();run(async()=>{
 const title=$('#renameTitle').value.trim(),input=$('#renameTitle'),button=$('#saveRename');
 if(!title||title.length>120){$('#renameError').textContent='Digite um nome de 1 a 120 caracteres.';input.setAttribute('aria-invalid','true');input.focus();return}
 button.disabled=true;button.textContent='Salvando…';
 try{if(dirty)await save();const fd=new FormData();fd.append('id',$('#renameForm').dataset.id);fd.append('title',title);await api('rename',{method:'POST',body:fd});modal('#renameModal',false);toast('Nome atualizado.');}
 catch(error){$('#renameError').textContent=error.message||'Não foi possível salvar. Tente novamente.';input.focus()}
 finally{button.disabled=false;button.textContent='Salvar nome'}
})};
function typeName(t){return({image:'Imagem',video:'Vídeo',audio:'Áudio',pdf:'PDF',presentation:'Apresenta\u00e7\u00e3o',text:'Texto'})[t]||t}
function render(){
 renderedVersion=state.version;
 const s=state.screen;
 $('#volume').value=s.volume; $('#volLabel').textContent=s.volume+'%'; $('#muted').checked=!!s.muted;
 $('#showClock').checked=!!s.showClock;$('#showProgress').checked=!!s.showProgress;$('#fit').value=['width','height'].includes(s.fit)?s.fit:'contain';fitHint();
 $('#transitionMs').value=s.transitionMs??650;$('#transitionMsLabel').textContent=(s.transitionMs??650)+' ms';
 $('#transition').value=s.transition;$('#background').value=s.background;
 renderQueue();renderLive();repairVideoDurations();
}
let queueView='active',archivePage=1,archivePageSize=10;
const scheduleDrafts=new Map();
const checkedVideos=new Set();let checkingVideo=false;
function readVideoDuration(src){return new Promise((resolve,reject)=>{
 const video=document.createElement('video');let timer;
 const finish=(error,duration)=>{clearTimeout(timer);video.onloadedmetadata=null;video.ondurationchange=null;video.onerror=null;video.removeAttribute('src');video.load();error?reject(error):resolve(duration)};
 const check=()=>{if(Number.isFinite(video.duration)&&video.duration>0)finish(null,video.duration)};
 video.preload='metadata';video.muted=true;video.onloadedmetadata=check;video.ondurationchange=check;
 video.onerror=()=>finish(new Error('Não foi possível ler a duração do vídeo. Verifique o formato do arquivo.'));
 timer=setTimeout(()=>finish(new Error('Tempo esgotado ao ler a duração do vídeo.')),20000);video.src=src;
})}
async function repairVideoDurations(){
 if(checkingVideo||busy)return;
 const item=state.items.find(it=>it.type==='video'&&!it.mediaDuration&&!checkedVideos.has(it.id));if(!item)return;
 checkingVideo=true;checkedVideos.add(item.id);
 try{const duration=await readVideoDuration(item.src);const fd=new FormData();fd.append('id',item.id);fd.append('duration',duration);await api('video_duration',{method:'POST',body:fd})}
 catch(error){toast(item.title+': '+error.message)}finally{checkingVideo=false;repairVideoDurations()}
}
function mediaActions(archived=false){return `<button class="btn small preview-item">Visualizar arquivo</button>${archived?purgeAction():'<button class="btn small archive-item">Arquivar arquivo</button>'}`}
function purgeAction(){return globalThis.StageAuth?.user?.role==='admin'?'<button class="btn small danger purge-item">Apagar definitivamente</button>':''}
function bindMediaActions(){
 $$('.preview-item').forEach(button=>button.onclick=()=>run(()=>openPreview(button.closest('[data-id]').dataset.id)));
 $$('.rename-item').forEach(button=>button.onclick=()=>{
  const id=button.closest('[data-id]').dataset.id,item=state.items.find(it=>it.id===id);
  $('#renameForm').dataset.id=id;$('#renameTitle').value=item.title;$('#renameCurrent').textContent=item.title;$('#renameError').textContent='';$('#renameTitle').removeAttribute('aria-invalid');$('#renameCount').textContent=item.title.length+'/120';modal('#renameModal');$('#renameTitle').focus();$('#renameTitle').select();
 });
 $$('.purge-item').forEach(button=>button.onclick=()=>run(async()=>{
  const id=button.closest('[data-id]').dataset.id,item=state.items.find(it=>it.id===id);
  if(!confirm(`Apagar definitivamente “${item.title}”? O arquivo e suas páginas serão excluídos e o item sairá da programação. Esta ação não pode ser desfeita.`))return;
  if(dirty)await save();
  const fd=new FormData();fd.append('id',id);await api('purge',{method:'POST',body:fd});
  scheduleDrafts.delete(id);toast('Item apagado definitivamente.');
 }));
}
function renderQueue(){
 const list=$('#playlist'),archive=$('#archiveList'),top=list.scrollTop,archiveTop=archive.scrollTop;
 const opened=new Set($$('.item details[open]').map(el=>el.closest('.item').dataset.id));
 const active=state.items.filter(it=>!it.archivedAt),archived=state.items.filter(it=>it.archivedAt).sort((a,b)=>b.archivedAt-a.archivedAt);
 const thumbnail=it=>{const src=it.pages?.[0]||(it.type==='image'?it.src:null);return src?`<img class="queue-thumb" src="${esc(src)}" alt="" loading="lazy">`:`<span class="queue-thumb file-icon" aria-hidden="true">${it.type==='video'?'\u25b6':it.type==='audio'?'\u266b':'T'}</span>`};
 list.innerHTML=active.length?active.map((it,i)=>`
 <article class="item queue-card" draggable="true" data-id="${esc(it.id)}" data-title="${esc(it.title.toLowerCase())}">
  <div class="queue-card-top"><span class="queue-index">${i+1}</span>${thumbnail(it)}<div class="item-title"><button class="title-rename rename-item" title="Clique para editar o nome" aria-label="Renomear ${esc(it.title)}">${esc(it.title)}<span aria-hidden="true"> ✎</span></button><small>${typeName(it.type)}${it.pages?.length?' / '+it.pages.length+' p\u00e1ginas':''}${it.renderQuality==='fullhd'?' / Full HD+':''}</small><small class="upload-author">Enviado por ${esc(it.uploadedBy?.name||'Autor n\u00e3o registrado (conte\u00fado antigo)')}</small><span class="item-status"></span></div></div>
  <div class="queue-card-actions"><button class="btn small select-item">\u25b6 Exibir agora</button>${mediaActions()}</div>
  <details ${opened.has(it.id)?'open':''}><summary>Horários e opções <span class="schedule-badge">${periodName((scheduleDrafts.get(it.id)||it).dayPeriod)}</span></summary><div class="queue-card-actions"><button class="btn small move-up" ${i===0?'disabled':''} aria-label="Mover para cima">\u2191 Subir</button><button class="btn small move-down" ${i===active.length-1?'disabled':''} aria-label="Mover para baixo">\u2193 Descer</button>${it.type==='presentation'&&it.renderQuality!=='fullhd'?'<button class="btn small quality">Melhorar qualidade</button>':''}${purgeAction()}</div><h3 class="option-heading">Tempo de exibição</h3><p class="hint option-help">Defina por quanto tempo o conteúdo aparece na TV. Os tempos são salvos automaticamente.</p><div class="queue-timing"><label>${it.type==='video'?'Dura\u00e7\u00e3o do v\u00eddeo (s)':'Tempo por p\u00e1gina (s)'}<input ${it.type==='video'?'readonly':''} step="any" class="input duration" type="number" min="1" max="86400" value="${Number(it.duration)||10}"></label><label>Intervalo ao terminar (s)<input class="input delay" type="number" min="0" max="3600" value="${Number(it.delay)||0}"></label><span class="queue-total">Total: ${formatDuration((it.duration||10)*Math.max(1,it.pages?.length||0)+(it.delay||0))}</span></div>
  <div class="schedule-heading"><b>Agendamento diário</b><span>Horário de Brasília</span></div><p class="hint option-help">Escolha um período. Deixe início e fim em branco para repetir todos os dias.</p><label class="field period-field">Período<select class="input day-period">${['all','morning','afternoon','night'].map(value=>`<option value="${value}" ${((scheduleDrafts.get(it.id)||it).dayPeriod||'all')===value?'selected':''}>${periodName(value)}</option>`).join('')}</select></label><div class="queue-schedule"><label>Início (opcional)<input class="input start" type="datetime-local" value="${esc((scheduleDrafts.get(it.id)||it).startsAt||'')}"></label><label>Fim (opcional)<input class="input end" type="datetime-local" value="${esc((scheduleDrafts.get(it.id)||it).endsAt||'')}"></label></div><p class="schedule-status hint" role="status">${scheduleDrafts.has(it.id)?'Alterações ainda não salvas.':''}</p><div class="schedule-actions"><button class="btn small primary save-schedule">Salvar agendamento</button><button class="btn small cancel-schedule">Cancelar alterações</button></div></details>
 </article>`).join(''):'<div class="dropzone"><b>A fila est\u00e1 vazia</b><span>Adicione conte\u00fado ou recupere um item dos arquivados.</span></div>';
 archive.innerHTML=archived.length?archived.map(it=>`<article class="archived-card" data-id="${esc(it.id)}" data-title="${esc(it.title.toLowerCase())}"><div class="queue-card-top">${thumbnail(it)}<div class="item-title"><button class="title-rename rename-item" title="Clique para editar o nome" aria-label="Renomear ${esc(it.title)}">${esc(it.title)}<span aria-hidden="true"> ✎</span></button><small>${it.archiveReason==='expired'?'Validade encerrada':'Arquivado manualmente'} / ${new Date(it.archivedAt).toLocaleString('pt-BR')}</small><small>Enviado por ${esc(it.uploadedBy?.name||'Autor n\u00e3o registrado (conte\u00fado antigo)')}</small></div></div><div class="queue-card-actions"><button class="btn small restore-item">Recuperar para a fila</button>${mediaActions(true)}</div></article>`).join(''):'<div class="dropzone"><b>Nenhum item arquivado</b><span>Os itens removidos ou com validade encerrada aparecer\u00e3o aqui.</span></div>';
 $('#queueCount').textContent=active.length;$('#archiveCount').textContent=archived.length;
 $('#queueSummary').textContent=active.length+' item(ns) na fila / '+formatDuration(active.reduce((sum,it)=>sum+(it.duration||10)*Math.max(1,it.pages?.length||0)+(it.delay||0),0));
 bindMediaActions();bindItems();$$('.restore-item').forEach(b=>b.onclick=()=>run(()=>itemAction('restore',b.closest('.archived-card').dataset.id)));
 filterQueue();list.scrollTop=top;archive.scrollTop=archiveTop;
}
function formatDuration(seconds){seconds=Math.round(seconds);return seconds>=3600?Math.floor(seconds/3600)+'h '+Math.floor(seconds%3600/60)+'min':seconds>=60?Math.floor(seconds/60)+'min '+seconds%60+'s':seconds+'s'}
function filterQueue(){
 const query=$('#queueSearch').value.trim().toLocaleLowerCase('pt-BR');
 $$('.queue-card').forEach(el=>el.hidden=!el.dataset.title.includes(query));
 const matches=$$('.archived-card').filter(el=>el.dataset.title.includes(query));
 const pages=Math.max(1,Math.ceil(matches.length/archivePageSize));archivePage=Math.min(archivePage,pages);
 const visible=new Set(matches.slice((archivePage-1)*archivePageSize,archivePage*archivePageSize));
 $$('.archived-card').forEach(el=>el.hidden=!visible.has(el));
 $('#playlist').hidden=queueView!=='active';$('#archiveList').hidden=queueView!=='archive';
 $('#archivePagination').hidden=queueView!=='archive';
 $('#archivePageLabel').textContent=`${matches.length} item(ns) / Página ${archivePage} de ${pages}`;
 $('#archivePrev').disabled=archivePage<=1;$('#archiveNext').disabled=archivePage>=pages;
 $('#queueTab').setAttribute('aria-pressed',String(queueView==='active'));$('#archiveTab').setAttribute('aria-pressed',String(queueView==='archive'));
}
$('#archivePageSize').onchange=()=>{archivePageSize=Number($('#archivePageSize').value)||10;archivePage=1;filterQueue()};
$('#archivePrev').onclick=()=>{archivePage=Math.max(1,archivePage-1);filterQueue()};
$('#archiveNext').onclick=()=>{archivePage++;filterQueue()};

async function itemAction(action,id){if(dirty)await save();const fd=new FormData();fd.append('id',id);await api(action,{method:'POST',body:fd});toast(action==='restore'?'Item recuperado para a fila.':'Item arquivado. O arquivo foi preservado.')}
function scheduleSave(){
 dirty=true;clearTimeout(saveTimer);saveTimer=setTimeout(()=>run(save),450);
}
async function save(){
 clearTimeout(saveTimer);if(!state)return;
 const screen={volume:+$('#volume').value,muted:$('#muted').checked,showClock:$('#showClock').checked,
 showProgress:$('#showProgress').checked,fit:$('#fit').value,transition:$('#transition').value,transitionMs:+$('#transitionMs').value,background:$('#background').value};
 const items=$$('.item').map(el=>({id:el.dataset.id,
 duration:+el.querySelector('.duration').value,delay:+el.querySelector('.delay').value}));
 await api('save',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({screen,items})});
 dirty=false;
}
function bindItems(){
 $$('.item').forEach(el=>{
  el.querySelector('.select-item').onclick=()=>run(()=>select(el.dataset.id));
  el.ondblclick=async e=>{if(e.target.closest('input,button,details,label'))return;await run(()=>select(el.dataset.id))};
  el.querySelectorAll('.duration,.delay').forEach(x=>x.onchange=scheduleSave);
  const captureSchedule=()=>{scheduleDrafts.set(el.dataset.id,{startsAt:el.querySelector('.start').value,endsAt:el.querySelector('.end').value,dayPeriod:el.querySelector('.day-period').value});el.querySelector('.schedule-status').textContent='Alterações ainda não salvas.'};
  el.querySelectorAll('.start,.end,.day-period').forEach(x=>x.oninput=captureSchedule);
  el.querySelector('.cancel-schedule').onclick=()=>{scheduleDrafts.delete(el.dataset.id);renderQueue()};
  el.querySelector('.save-schedule').onclick=()=>run(async()=>{
   captureSchedule();const draft={...scheduleDrafts.get(el.dataset.id)};
   if(draft.startsAt&&draft.endsAt&&draft.endsAt<=draft.startsAt)throw new Error('O fim deve ser posterior ao início.');
   if(dirty)await save();
   const fd=new FormData();fd.append('id',el.dataset.id);fd.append('startsAt',draft.startsAt);fd.append('endsAt',draft.endsAt);fd.append('dayPeriod',draft.dayPeriod);
   await api('schedule',{method:'POST',body:fd});scheduleDrafts.delete(el.dataset.id);toast('Agendamento salvo.');
  });
  el.querySelector('.archive-item').onclick=()=>run(()=>itemAction('archive',el.dataset.id));
  const move=direction=>run(async()=>{if(dirty)await save();const active=state.items.filter(it=>!it.archivedAt),index=active.findIndex(it=>it.id===el.dataset.id),target=index+direction;if(target<0||target>=active.length)return;[active[index],active[target]]=[active[target],active[index]];state.items=[...active,...state.items.filter(it=>it.archivedAt)];renderQueue();await save()});
  el.querySelector('.move-up').onclick=()=>move(-1);el.querySelector('.move-down').onclick=()=>move(1);
  const quality=el.querySelector('.quality');if(quality)quality.onclick=()=>run(async()=>{const fd=new FormData();fd.append('id',el.dataset.id);$('#commandStatus').textContent='Preparando alta resolu\u00e7\u00e3o...';await api('prepare',{method:'POST',body:fd});toast('Qualidade atualizada.')});
  el.ondragstart=e=>{if(e.target.closest('input,button')){e.preventDefault();return}e.dataTransfer.setData('text/plain',el.dataset.id);el.classList.add('dragging')};el.ondragend=()=>el.classList.remove('dragging');
  el.ondragover=e=>e.preventDefault();
  el.ondrop=e=>{e.preventDefault();run(async()=>{if(dirty)await save();const from=e.dataTransfer.getData('text/plain'),to=el.dataset.id;if(from===to||!state.items.some(x=>x.id===from))return;
    const a=state.items.findIndex(x=>x.id===from),b=state.items.findIndex(x=>x.id===to);const [m]=state.items.splice(a,1);state.items.splice(b,0,m);render();await save();
  })};
 });
}
async function select(id){if(dirty)await save();let fd=new FormData();fd.append('id',id);if(state.items.find(it=>it.id===id)?.type==='pdf'){toast('Preparando p\u00e1ginas do PDF...');await api('prepare',{method:'POST',body:fd})}await api('select',{method:'POST',body:fd});toast('Conte\u00fado em apresenta\u00e7\u00e3o')}
async function command(cmd){if(dirty)await save();let fd=new FormData();fd.append('command',cmd);$('#commandStatus').textContent='Enviando comando...';await api('command',{method:'POST',body:fd});$('#commandStatus').textContent=({play:'Reprodu\u00e7\u00e3o iniciada',pause:'Pausa confirmada',next:'Avan\u00e7o confirmado',prev:'Retorno confirmado',reload:'Recarregamento solicitado',blackout:state.playback.blackout?'Tela de espera ativada. Apresentação pausada.':state.playback.paused?'Tela de espera removida. Continua pausado.':'Apresentação retomada.'})[cmd];toast($('#commandStatus').textContent)}
$$('[data-cmd]').forEach(b=>b.onclick=()=>run(()=>command(b.dataset.cmd)));
function fitHint(){ $('#fitHint').textContent=({width:'Ocupa toda a largura, sem distorcer. O excesso de altura pode ficar fora da tela.',height:'Ocupa toda a altura, sem distorcer. O excesso de largura pode ficar fora da tela.',contain:'Mostra todo o conteúdo sem cortes, mantendo a proporção em celulares e TVs.'})[$('#fit').value] }
['volume','muted','showClock','showProgress','fit','transition','transitionMs','background'].forEach(id=>$('#'+id).oninput=()=>{if(id==='volume')$('#volLabel').textContent=$('#volume').value+'%';if(id==='fit')fitHint();if(id==='transitionMs')$('#transitionMsLabel').textContent=$('#transitionMs').value+' ms';scheduleSave()});
$('#uploadBtn').onclick=()=>{clientEvent('upload.open');$('#fileInput').click()};
$('#fileInput').onchange=e=>run(()=>uploadFiles(e.target.files));
async function uploadFiles(list){
 if(dirty)await save();let failures=0;
 const errors=[];
 for(const file of list){const fd=new FormData();fd.append('file',file);$('#commandStatus').textContent='Enviando e preparando '+file.name+'…';toast($('#commandStatus').textContent);try{if(file.type.startsWith('video/')||/\.(mp4|webm|ogv)$/i.test(file.name)){const url=URL.createObjectURL(file);try{fd.append('videoDuration',await readVideoDuration(url))}finally{URL.revokeObjectURL(url)}}await api('upload',{method:'POST',body:fd})}catch(e){failures++;errors.push(file.name+': '+e.message);toast(e.message)}}
 $('#commandStatus').textContent=errors.length?errors.join(' / '):'Arquivos preparados para exibição.';
 $('#fileInput').value='';await load();toast(failures?'Envio encerrado com '+failures+' falha(s)':'Upload concluído');
}
const dz=$('#dropzone');['dragenter','dragover'].forEach(n=>dz.addEventListener(n,e=>{e.preventDefault();dz.classList.add('drag')}));
['dragleave','drop'].forEach(n=>dz.addEventListener(n,e=>{e.preventDefault();dz.classList.remove('drag')}));
dz.ondrop=e=>run(()=>uploadFiles(e.dataTransfer.files));
let modalReturnFocus=null;
function modal(id,on=true){const el=$(id);if(on)modalReturnFocus=document.activeElement;el.classList.toggle('open',on);document.body.classList.toggle('dialog-open',!!$('.modal.open'));if(!on){if(id==='#mediaModal')$('#mediaPreview').replaceChildren();const trigger=modalReturnFocus;setTimeout(()=>{if(trigger?.isConnected)trigger.focus();else if(id==='#renameModal'){const item=$$('[data-id]').find(x=>x.dataset.id===$('#renameForm').dataset.id);(item?.querySelector('.rename-item')||$('#queueSearch')).focus()}},0)}}

$('#textBtn').onclick=()=>{clientEvent('text.open');modal('#textModal')};$$('.modal-x').forEach(x=>x.onclick=()=>modal('#'+x.closest('.modal').id,false));
$('#saveText').onclick=()=>run(async()=>{if(!$('#textContent').value.trim())throw new Error('Digite o texto antes de adicionar.');if(dirty)await save();let fd=new FormData();fd.append('title',$('#textTitle').value);fd.append('text',$('#textContent').value);fd.append('duration',$('#textDuration').value);await api('add_text',{method:'POST',body:fd});modal('#textModal',false);$('#textTitle').value='';$('#textContent').value='';await load()});
$('#logout').onclick=()=>run(async()=>{if(dirty)await save();await api('logout',{method:'POST'});location.reload()});
async function run(task){
 if(busy){if(task===save)saveTimer=setTimeout(()=>run(save),450);return}busy=true;
 $$('[data-cmd],#saveText,#uploadBtn,.item input,.item select,aside input,aside select').forEach(b=>b.disabled=true);
 try{await task()}catch(e){$('#commandStatus').textContent=e.message;toast(e.message||'Não foi possível concluir a operação.')}finally{busy=false;if(state){if(!dirty)render();$$('[data-cmd],#saveText,#uploadBtn,.item input,.item select,aside input,aside select').forEach(b=>b.disabled=false);$$('[data-cmd]').forEach(b=>b.disabled=!state.items.length&&['play','pause','next','prev'].includes(b.dataset.cmd))}}
}
$('#queueTab').onclick=()=>{queueView='active';filterQueue();clientEvent('queue.active')};$('#archiveTab').onclick=()=>{queueView='archive';filterQueue();clientEvent('queue.archived')};let searchLogTimer;$('#queueSearch').oninput=()=>{archivePage=1;filterQueue();clearTimeout(searchLogTimer);searchLogTimer=setTimeout(()=>clientEvent('queue.search',$('#queueSearch').value),700)};
if($('#openViewer'))$('#openViewer').onclick=()=>clientEvent('viewer.open');
document.addEventListener('keydown',e=>{const dialog=$('.modal.open');if(!dialog)return;if(e.key==='Escape'){if(busy)return;modal('#'+dialog.id,false)}if(e.key==='Tab'){const controls=[...dialog.querySelectorAll('button:not(:disabled),input:not(:disabled),textarea,select,a[href]')].filter(x=>!x.hidden);const first=controls[0],last=controls[controls.length-1];if(e.shiftKey&&document.activeElement===first){e.preventDefault();last?.focus()}else if(!e.shiftKey&&document.activeElement===last){e.preventDefault();first?.focus()}}});
setInterval(renderLive,100);sync.start();

function clientEvent(event,value=''){if(!globalThis.StageAuth?.csrf)return;const body=new FormData();body.append('event',event);body.append('value',value);fetch('api.php?action=ui_event',{method:'POST',headers:{'X-CSRF-Token':StageAuth.csrf},body,keepalive:true}).catch(()=>{})}

if($('#screenSettingsToggle'))$('#screenSettingsToggle').onclick=()=>{const panel=$('#screenSettings'),open=panel.hidden;panel.hidden=!open;$('#presentationPanel').classList.toggle('settings-open',open);$('#screenSettingsToggle').setAttribute('aria-expanded',String(open));if(open)panel.scrollIntoView?.({behavior:'smooth',block:'nearest'});};
