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
  panel.querySelectorAll('form button, #stClearToken, #stConnect, #stDisconnect, #stReloadOAuth').forEach(b=>b.disabled=busy);
 }
 async function pool(items,fn){let next=0;await Promise.all(Array.from({length:Math.min(3,items.length)},async()=>{while(next<items.length)await fn(items[next++]);}));}
 async function status(tv){try{states.set(tv.id,await request('status',{id:tv.id}));}catch(error){states.set(tv.id,{status:'error',message:error.message});}render();}
 async function refresh(){if(busy)return;busy=true;render();try{await pool(tvs,status);}finally{busy=false;render();}}
 function showOAuth(c){
  if(!c||!el('stOAuthForm'))return;
  el('stClientId').value=c.clientId;el('stAppId').value=c.appId;el('stBaseUrl').value=c.baseUrl||new URL('.',location.href).href.replace(/\/$/,'');
  el('stSecretState').textContent=c.hasSecret?'— salvo':'';
  el('stCallbackUrl').value=c.callbackUrl;el('stWebhookUrl').value=c.webhookUrl;
  el('stOAuthState').textContent=c.connected?'Conta conectada · renovação automática ao usar as TVs.':'Conta não conectada. Salve as credenciais e autorize na Samsung.';
  el('stWebhookState').textContent=c.lastWebhookAt?'Último webhook recebido: '+new Date(c.lastWebhookAt*1000).toLocaleString('pt-BR'):'Nenhum webhook recebido ainda.';
  el('stConfirmWebhook').hidden=!c.confirmationUrl;
  if(c.confirmationUrl)el('stConfirmWebhook').href=c.confirmationUrl;else el('stConfirmWebhook').removeAttribute('href');
 }
 async function load(){const data=await request('config');tvs=data.tvs;loaded=true;if(el('stTokenState'))el('stTokenState').textContent=data.hasToken?'— configurado':'— não configurado';showOAuth(data.oauth);render();}
 async function command(targets,power){
  if(busy)return;busy=true;render();message('Enviando comandos…');let failures=0;
  try{await pool(targets,async tv=>{try{const result=await request('command',{id:tv.id,command:power});states.set(tv.id,{status:'unknown',message:result.message});}catch(error){failures++;states.set(tv.id,{status:'error',message:error.message});}render();});message(`${targets.length-failures} comando(s) enviado(s), ${failures} falha(s). O status será consultado novamente.`);}finally{busy=false;render();}
  setTimeout(()=>{if(!panel.hidden)refresh();},3000);
 }
 function reset(){el('stTvForm').reset();el('stId').value='';el('stFormTitle').textContent='Cadastrar TV';}
 function edit(tv){reset();el('stId').value=tv.id;el('stName').value=tv.name;el('stDevice').value=tv.deviceId;el('stFormTitle').textContent='Editar TV'+(tv.hasToken?' — token exclusivo configurado':' — usa OAuth / conexão padrão');panel.querySelector('details').open=true;el('stName').focus();}
 async function mutate(action,values,done){if(busy)return;busy=true;render();try{await request(action,values);done?.();await load();message('Configuração salva.');}catch(error){message(error.message);}finally{busy=false;render();}}
 function remove(tv){if(confirm('Remover '+tv.name+' deste painel?'))mutate('delete',{id:tv.id},()=>{states.delete(tv.id);if(el('stId').value===tv.id)reset();});}
 function select(show){panel.hidden=!show;el('presentationPanel').hidden=show;el('presentationTab').setAttribute('aria-pressed',String(!show));el('smartthingsTab').setAttribute('aria-pressed',String(show));if(show){(async()=>{try{if(!loaded)await load();await refresh();}catch(error){message(error.message);}})();}}
 el('presentationTab').onclick=()=>select(false);el('smartthingsTab').onclick=()=>select(true);
 el('stRefresh').onclick=refresh;el('stAllOn').onclick=()=>command([...tvs],'on');el('stAllOff').onclick=()=>command([...tvs],'off');
 if(el('stTvForm')){
  el('stOAuthForm').onsubmit=e=>{e.preventDefault();mutate('oauth_save',{clientId:el('stClientId').value,clientSecret:el('stClientSecret').value,baseUrl:el('stBaseUrl').value,appId:el('stAppId').value},()=>{el('stClientSecret').value='';});};
  el('stConnect').onclick=async()=>{if(busy)return;busy=true;render();try{
   const saved=await request('config');const c=saved.oauth;
   if(!c?.baseUrl)throw new Error('Salve a configuração OAuth primeiro.');
   if(new URL(c.baseUrl).origin!==location.origin)throw new Error('Abra o TVMax pela URL pública salva antes de conectar: '+c.baseUrl+'/apresentador.php#smartthings');
   if(el('stClientId').value!==c.clientId||el('stBaseUrl').value!==c.baseUrl||el('stAppId').value!==c.appId||el('stClientSecret').value)throw new Error('Salve as alterações OAuth antes de conectar.');
   const data=await request('oauth_start');location.assign(data.url);
  }catch(error){message(error.message);}finally{busy=false;render();}};
  el('stDisconnect').onclick=()=>{if(confirm('Desconectar a conta Samsung deste TVMax?'))mutate('oauth_disconnect',{});};
  el('stReloadOAuth').onclick=async()=>{if(busy)return;busy=true;render();try{await load();message('Conexão e webhook atualizados.');}catch(error){message(error.message);}finally{busy=false;render();}};
  panel.querySelectorAll('[data-st-copy]').forEach(b=>b.onclick=async()=>{const input=el(b.dataset.stCopy);if(!input.value){message('Salve a configuração OAuth para gerar os links.');return;}try{await navigator.clipboard.writeText(input.value);message('Link copiado.');}catch{input.focus();input.select();message('Selecionei o link. Use Ctrl+C para copiar.');}});
  el('stCancel').onclick=reset;
  el('stTvForm').onsubmit=e=>{e.preventDefault();mutate('save',{id:el('stId').value,name:el('stName').value,deviceId:el('stDevice').value,token:el('stTvToken').value,clearToken:el('stUseDefault').checked?'1':''},reset);};
  el('stTokenForm').onsubmit=e=>{e.preventDefault();mutate('token',{token:el('stToken').value},()=>el('stTokenForm').reset());};
  el('stClearToken').onclick=()=>{if(confirm('Remover o token padrão? TVs que dependem dele ficarão sem acesso.'))mutate('token',{token:''},()=>el('stTokenForm').reset());};
 }
 setInterval(()=>{if(loaded&&!panel.hidden&&!document.hidden)refresh();},30000);
 if(location.hash==='#smartthings')select(true);
})();
