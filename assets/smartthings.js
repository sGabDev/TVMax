(() => {
 'use strict';
 const el=id=>document.getElementById(id),panel=el('smartthingsPanel');
 const labels={on:'Ligada',off:'Desligada',offline:'Offline',unknown:'Status desconhecido',error:'Falha na consulta',loading:'Consultando…'};
 let tvs=[],busy=false,loaded=false;const states=new Map();
 async function request(action,values={}) {
  const response=await fetch('api.php?action=smartthings_'+action,{method:'POST',cache:'no-store',headers:{'X-CSRF-Token':StageAuth.csrf},body:new URLSearchParams(values)});
  const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'Não foi possível concluir a solicitação.');return data;
 }
 function message(text){el('stMessage').textContent=text;}
 function button(label,fn){const b=document.createElement('button');b.type='button';b.className='btn small';b.textContent=label;b.disabled=busy;b.onclick=fn;return b;}
 function render(){
  el('stList').replaceChildren();
  if(!tvs.length)el('stList').textContent='Nenhuma TV cadastrada. Um administrador pode cadastrar as TVs abaixo.';
  for(const tv of tvs){
   const state=states.get(tv.id)||{status:'unknown'},card=document.createElement('article');card.className='st-card';
   const name=document.createElement('h2');name.textContent=tv.name;
   const badge=document.createElement('span');badge.className='st-badge st-'+state.status;badge.textContent=labels[state.status];
   const detail=document.createElement('p');detail.className='hint';detail.textContent=state.message||(state.checkedAt?'Consultado às '+new Date(state.checkedAt*1000).toLocaleTimeString('pt-BR'):'Aguardando consulta');
   const actions=document.createElement('div');actions.className='panel-actions';actions.append(button('Ligar',()=>command([tv],'on')),button('Desligar',()=>command([tv],'off')));
   if(StageAuth.user.role==='admin')actions.append(button('Editar',()=>edit(tv)),button('Remover',()=>remove(tv)));
   card.append(name,badge,detail,actions);el('stList').append(card);
  }
  for(const id of ['stRefresh','stAllOn','stAllOff'])el(id).disabled=busy||!tvs.length;
  panel.querySelectorAll('form button, #stClearToken').forEach(b=>b.disabled=busy);
 }
 async function pool(items,fn){let next=0;await Promise.all(Array.from({length:Math.min(3,items.length)},async()=>{while(next<items.length)await fn(items[next++]);}));}
 async function status(tv){try{states.set(tv.id,await request('status',{id:tv.id}));}catch(error){states.set(tv.id,{status:'error',message:error.message});}render();}
 async function refresh(){if(busy)return;busy=true;render();try{await pool(tvs,status);}finally{busy=false;render();}}
 async function load(){const data=await request('config');tvs=data.tvs;loaded=true;if(el('stTokenState'))el('stTokenState').textContent=data.hasToken?'— configurado':'— não configurado';render();}
 async function command(targets,power){
  if(busy)return;busy=true;render();message('Enviando comandos…');let failures=0;
  try{await pool(targets,async tv=>{try{const result=await request('command',{id:tv.id,command:power});states.set(tv.id,{status:'unknown',message:result.message});}catch(error){failures++;states.set(tv.id,{status:'error',message:error.message});}render();});message(`${targets.length-failures} comando(s) enviado(s), ${failures} falha(s). O status será consultado novamente.`);}finally{busy=false;render();}
  setTimeout(()=>{if(!panel.hidden)refresh();},3000);
 }
 function reset(){el('stTvForm').reset();el('stId').value='';el('stFormTitle').textContent='Cadastrar TV';}
 function edit(tv){reset();el('stId').value=tv.id;el('stName').value=tv.name;el('stDevice').value=tv.deviceId;el('stFormTitle').textContent='Editar TV'+(tv.hasToken?' — token exclusivo configurado':' — usa token padrão');panel.querySelector('details').open=true;el('stName').focus();}
 async function mutate(action,values,done){if(busy)return;busy=true;render();try{await request(action,values);done?.();await load();message('Configuração salva.');}catch(error){message(error.message);}finally{busy=false;render();}}
 function remove(tv){if(confirm('Remover '+tv.name+' deste painel?'))mutate('delete',{id:tv.id},()=>{states.delete(tv.id);if(el('stId').value===tv.id)reset();});}
 function select(show){panel.hidden=!show;el('presentationPanel').hidden=show;el('presentationTab').setAttribute('aria-pressed',String(!show));el('smartthingsTab').setAttribute('aria-pressed',String(show));if(show){(async()=>{try{if(!loaded)await load();await refresh();}catch(error){message(error.message);}})();}}
 el('presentationTab').onclick=()=>select(false);el('smartthingsTab').onclick=()=>select(true);
 el('stRefresh').onclick=refresh;el('stAllOn').onclick=()=>command([...tvs],'on');el('stAllOff').onclick=()=>command([...tvs],'off');
 if(el('stTvForm')){
  el('stCancel').onclick=reset;
  el('stTvForm').onsubmit=e=>{e.preventDefault();mutate('save',{id:el('stId').value,name:el('stName').value,deviceId:el('stDevice').value,token:el('stTvToken').value,clearToken:el('stUseDefault').checked?'1':''},reset);};
  el('stTokenForm').onsubmit=e=>{e.preventDefault();mutate('token',{token:el('stToken').value},()=>el('stTokenForm').reset());};
  el('stClearToken').onclick=()=>{if(confirm('Remover o token padrão? TVs que dependem dele ficarão sem acesso.'))mutate('token',{token:''},()=>el('stTokenForm').reset());};
 }
 setInterval(()=>{if(loaded&&!panel.hidden&&!document.hidden)refresh();},30000);
})();
