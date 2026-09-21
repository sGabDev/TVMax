'use strict';
const forms=[...document.querySelectorAll('[data-auth-form]')],message=document.querySelector('#authMessage');
function showForm(name){forms.forEach(form=>form.hidden=form.dataset.authForm!==name);document.querySelectorAll('[data-auth-tab]').forEach(b=>b.setAttribute('aria-pressed',String(b.dataset.authTab===name)));message.textContent=''}
document.querySelectorAll('[data-auth-tab]').forEach(b=>b.onclick=()=>showForm(b.dataset.authTab));
showForm(document.querySelector('#setupForm')?'setup':'login');
forms.forEach(form=>form.onsubmit=async event=>{
 event.preventDefault();message.textContent='';message.classList.remove('success');
 const body=new FormData(form),action=form.dataset.authForm;
 if(action!=='login'&&body.get('password')!==body.get('passwordConfirm')){message.textContent='As senhas não coincidem.';return}
 const button=form.querySelector('button');button.disabled=true;
 try {
  const response=await fetch('api.php?action='+action,{method:'POST',headers:{'X-CSRF-Token':StageAuth.csrf},body});
  const data=await response.json();if(!response.ok||!data.ok)throw new Error(data.error||'Não foi possível concluir.');
  if(action==='register'){form.reset();message.classList.add('success');message.textContent=data.message}
  else location.href='apresentador.php';
 } catch(error){message.textContent=error.message}finally{button.disabled=false}
});
