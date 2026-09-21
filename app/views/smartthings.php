<section id="smartthingsPanel" hidden>
 <div class="panel">
  <div class="panel-head"><div><span class="eyebrow">SMARTTHINGS</span><h1>Controle das TVs</h1></div><button class="btn" id="stRefresh">Atualizar status</button></div>
  <p class="hint">Ligue ou desligue as TVs cadastradas na sua conta Samsung. O status é consultado a cada 30 segundos enquanto esta aba estiver aberta.</p>
  <div class="panel-actions"><button class="btn primary" id="stAllOn">Ligar todas</button><button class="btn" id="stAllOff">Desligar todas</button></div>
  <p id="stMessage" role="status" aria-live="polite"></p>
  <div id="stList" class="st-list"></div>
  <p class="hint">Offline significa que o SmartThings não consegue alcançar a TV. Para ligar remotamente, a TV precisa manter a conexão em espera e permitir essa função nas configurações de rede.</p>
 </div>
 <?php if($user['role']==='admin'): ?>
 <details class="panel st-settings" open><summary>Configurar SmartThings e TVs</summary>
  <p class="hint">Cadastre suas TVs no aplicativo SmartThings. Crie um <a href="https://account.smartthings.com/tokens" target="_blank" rel="noopener">token de acesso</a> com permissões de leitura e execução de dispositivos (r:devices:* e x:devices:*). Consulte o Device ID em <a href="https://my.smartthings.com/advanced" target="_blank" rel="noopener">SmartThings Advanced</a>.</p>
  <p class="hint">Novos tokens pessoais expiram em 24 horas e precisam ser substituídos aqui. As chaves ficam no servidor e não são exibidas novamente.</p>
  <form id="stTokenForm"><label class="field">Token padrão <span id="stTokenState"></span><input class="input" type="password" id="stToken" autocomplete="new-password" maxlength="4096" required placeholder="Cole um novo token"></label><button class="btn" type="submit">Salvar token padrão</button> <button class="btn" type="button" id="stClearToken">Remover token padrão</button></form>
  <hr>
  <form id="stTvForm"><h2 id="stFormTitle">Cadastrar TV</h2><input type="hidden" id="stId">
   <label class="field">Nome da TV<input class="input" id="stName" maxlength="100" required placeholder="Ex.: Recepção"></label>
   <label class="field">Device ID<input class="input" id="stDevice" maxlength="36" required placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></label>
   <label class="field">Token exclusivo (opcional)<input class="input" type="password" id="stTvToken" autocomplete="new-password" maxlength="4096" placeholder="Vazio mantém o token salvo; novas TVs usam o padrão"></label>
   <label class="switch-row"><span>Remover token exclusivo e usar o padrão</span><input type="checkbox" id="stUseDefault"></label>
   <button class="btn primary" type="submit">Salvar TV</button> <button class="btn" type="button" id="stCancel">Limpar formulário</button>
  </form>
 </details>
 <?php endif; ?>
</section>
