'use strict';
const $=s=>document.querySelector(s),escapeHtml=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
let nextLog=null,loadingLogs=false;
const roles={operator:'Operador',admin:'Administrador'},statuses={pending:'Aguardando aprovação',active:'Aprovado',rejected:'Recusado',suspended:'Suspenso'};
async function api(action,body){
 const response=await fetch('api.php?action='+action,{cache:'no-store',...(body?{method:'POST',body,headers:{'X-CSRF-Token':StageAuth.csrf}}:{})});
 const data=await response.json();if(!response.ok||!data.ok){if(response.status===401)location.href='apresentador.php';throw new Error(data.error||'Falha ao concluir a ação.')}
 return data;
}
async function users(){
 const data=await api('users');
 $('#usersList').innerHTML=data.users.map(u=>`<article class="user-card" data-id="${escapeHtml(u.id)}"><div class="user-info"><b>${escapeHtml(u.name)}</b><span>${escapeHtml(u.email)}</span><small>Cadastrado em ${new Date(u.created_at*1000).toLocaleString('pt-BR')}</small></div><label>Cargo<select class="input role">${Object.entries(roles).map(([key,label])=>`<option value="${key}" ${key===u.role?'selected':''}>${label}</option>`).join('')}</select></label><label>Situação<select class="input status">${Object.entries(statuses).map(([key,label])=>`<option value="${key}" ${key===u.status?'selected':''}>${label}</option>`).join('')}</select></label><div class="user-actions">${u.status==='pending'?'<button class="btn primary approve">Aprovar</button><button class="btn reject">Recusar</button>':'<button class="btn save-user">Salvar</button>'}</div></article>`).join('');
 const selected=$('#logActor').value;
 $('#logActor').innerHTML='<option value="">Todos os usuários e sistema</option>'+data.users.map(u=>`<option value="${escapeHtml(u.id)}">${escapeHtml(u.name)}</option>`).join('');$('#logActor').value=selected;
 document.querySelectorAll('.user-card').forEach(card=>{
  card.querySelectorAll('button').forEach(button=>button.onclick=async()=>{
   const body=new FormData();body.append('id',card.dataset.id);body.append('role',card.querySelector('.role').value);body.append('status',button.classList.contains('approve')?'active':button.classList.contains('reject')?'rejected':card.querySelector('.status').value);
   card.querySelectorAll('button').forEach(b=>b.disabled=true);
   try{await api('user_update',body);$('#adminMessage').textContent='Acesso atualizado.';await users()}catch(error){$('#adminMessage').textContent=error.message;card.querySelectorAll('button').forEach(b=>b.disabled=false)}
  });
 });
}
const actions={'account.setup':'Criou o primeiro administrador','account.register':'Solicitou cadastro','account.login':'Entrou no sistema','account.logout':'Saiu do sistema','users.update':'Alterou acesso de usuário','users.view':'Consultou usuários','audit.view':'Consultou logs','admin.open':'Abriu a administração','presenter.open':'Abriu o apresentador','viewer.open':'Abriu o visualizador','upload.started':'Iniciou envio de arquivo','presentation.upload':'Enviou arquivo','presentation.text':'Adicionou texto','presentation.edit':'Alterou fila ou configurações','presentation.archive':'Removeu da fila e arquivou','presentation.restore':'Recuperou item','presentation.quality':'Regenerou qualidade','playback.play':'Reproduziu','playback.pause':'Pausou','playback.next':'Avançou','playback.prev':'Voltou','playback.select':'Selecionou conteúdo','playback.blackout':'Alterou tela de espera','playback.reload':'Solicitou recarregamento','queue.automatic':'Atualização automática da fila','ui.queue.search':'Pesquisou na fila','ui.queue.active':'Abriu a fila','ui.queue.archived':'Abriu os arquivados','ui.text.open':'Abriu novo texto','ui.upload.open':'Abriu envio de arquivo','ui.viewer.open':'Abriu a TV'};
Object.assign(actions,{'viewer.fullscreen.enter':'Entrou em tela cheia','viewer.fullscreen.exit':'Saiu da tela cheia','viewer.media.enable':'Solicitou ativar a m\u00eddia','viewer.content.display':'Visualizador informou exibi\u00e7\u00e3o de conte\u00fado','request.login':'Tentativa de login recusada','request.register':'Cadastro recusado','request.setup':'Configura\u00e7\u00e3o inicial recusada','request.user_update':'Altera\u00e7\u00e3o de acesso recusada','request.command':'Comando recusado','request.upload':'Envio de arquivo falhou','request.state':'Acesso ao painel recusado','request.logs':'Consulta de logs recusada','request.users':'Consulta de usu\u00e1rios recusada'});
async function logs(reset=true){
 if(loadingLogs)return;loadingLogs=true;$('#moreLogs').disabled=true;
 try{
  if(reset){$('#logsList').replaceChildren();nextLog=null}
  const params=new URLSearchParams({q:$('#logQuery').value,actor:$('#logActor').value});if(nextLog)params.set('before',nextLog);
  const data=await api('logs&'+params);
  for(const log of data.logs){
   const card=document.createElement('article');card.className='audit-card';
   card.innerHTML=`<div class="audit-heading"><b>${escapeHtml(log.actor_name)}</b><time>${new Date(log.at).toLocaleString('pt-BR')}</time><span class="audit-outcome ${log.outcome==='failure'?'failure':''}">${log.outcome==='failure'?'Falhou / recusado':'Concluído'}</span></div><p>${escapeHtml(actions[log.action]||log.action)}</p><small>${escapeHtml(log.source==='client'?'Evento de interface':'Registro do servidor')} • IP ${escapeHtml(log.ip)} • #${log.id}</small><details><summary>Ver detalhes</summary><pre></pre></details>`;
   card.querySelector('pre').textContent=JSON.stringify({action:log.action,target:log.target,...log.details},null,2);$('#logsList').appendChild(card);
  }
  if(!$('#logsList').children.length)$('#logsList').textContent='Nenhuma atividade encontrada para estes filtros.';
  nextLog=data.next;$('#moreLogs').hidden=!nextLog;
 }finally{loadingLogs=false;$('#moreLogs').disabled=false}
}
function report(task){task.catch(error=>{$('#adminMessage').textContent=error.message})}
$('#refreshUsers').onclick=()=>report(users());
$('#usersTab').onclick=()=>{$('#usersPanel').hidden=false;$('#logsPanel').hidden=true;$('#usersTab').setAttribute('aria-pressed','true');$('#logsTab').setAttribute('aria-pressed','false');report(users())};
$('#logsTab').onclick=()=>{$('#usersPanel').hidden=true;$('#logsPanel').hidden=false;$('#usersTab').setAttribute('aria-pressed','false');$('#logsTab').setAttribute('aria-pressed','true');report(logs())};
$('#logFilters').onsubmit=event=>{event.preventDefault();report(logs())};$('#moreLogs').onclick=()=>report(logs(false));
report(users());
