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
  <h2>Conexão OAuth · Conta Samsung</h2>
  <p class="hint">Informe as credenciais do seu OAuth-In App. Salve, cadastre o Callback no SmartThings e clique em Conectar conta Samsung. As permissões necessárias são r:devices:* e x:devices:*.</p>
  <form id="stOAuthForm">
   <label class="field">Client ID<input class="input" id="stClientId" required maxlength="200" autocomplete="off"></label>
   <label class="field">Client Secret <span id="stSecretState"></span><input class="input" id="stClientSecret" type="password" maxlength="4096" autocomplete="new-password" placeholder="Vazio mantém o segredo salvo"></label>
   <label class="field">URL pública do TVMax<input class="input" id="stBaseUrl" type="url" required placeholder="https://seu-dominio.com/tvmax"></label>
   <p class="hint">Use a URL HTTPS da pasta que contém apresentador.php, acessível pela internet. Se usar um túnel, informe o endereço público dele e abra o TVMax por esse mesmo endereço antes de conectar.</p>
   <label class="field">App ID (necessário para confirmar o webhook)<input class="input" id="stAppId" maxlength="36" placeholder="ID da aplicação, diferente do Client ID"></label>
   <button class="btn primary" type="submit">Salvar OAuth e gerar links</button>
  </form>
  <p id="stOAuthState" role="status"></p>
  <div class="panel-actions"><button class="btn primary" type="button" id="stConnect">Conectar conta Samsung</button><button class="btn" type="button" id="stDisconnect">Desconectar do TVMax</button><button class="btn" type="button" id="stReloadOAuth">Atualizar conexão / webhook</button></div>
  <label class="field">Callback OAuth · Redirect URI<input class="input" id="stCallbackUrl" readonly placeholder="Salve a configuração para gerar o link"></label><button class="btn small" type="button" data-st-copy="stCallbackUrl">Copiar callback</button>
  <label class="field">Webhook · Target URL<input class="input" id="stWebhookUrl" readonly placeholder="Salve a configuração para gerar o link"></label><button class="btn small" type="button" data-st-copy="stWebhookUrl">Copiar webhook</button>
  <p class="hint">Copie os links completos para o cadastro da aplicação SmartThings. Após solicitar a confirmação do Target URL, clique em Atualizar conexão / webhook e abra o link de confirmação abaixo. Os status das TVs continuam sendo consultados a cada 30 segundos.</p>
  <p id="stWebhookState" class="hint"></p><a id="stConfirmWebhook" class="btn" target="_blank" rel="noopener noreferrer" hidden>Confirmar webhook no SmartThings</a>
  <p class="hint">O token OAuth é renovado automaticamente quando necessário ao consultar ou controlar uma TV. Se a autorização for revogada ou o refresh token expirar por inatividade, conecte a conta novamente. Desconectar aqui remove os tokens locais; para revogar o acesso, remova a integração na conta Samsung.</p>
  <hr>
  <details><summary>Tokens manuais (opcional)</summary>
  <p class="hint">Cadastre suas TVs no aplicativo SmartThings. Crie um <a href="https://account.smartthings.com/tokens" target="_blank" rel="noopener">token de acesso</a> com permissões de leitura e execução de dispositivos (r:devices:* e x:devices:*). Consulte o Device ID em <a href="https://my.smartthings.com/advanced" target="_blank" rel="noopener">SmartThings Advanced</a>.</p>
  <p class="hint">Novos tokens pessoais expiram em 24 horas e precisam ser substituídos aqui. As chaves ficam no servidor e não são exibidas novamente.</p>
  <form id="stTokenForm"><label class="field">Token padrão <span id="stTokenState"></span><input class="input" type="password" id="stToken" autocomplete="new-password" maxlength="4096" required placeholder="Cole um novo token"></label><button class="btn" type="submit">Salvar token padrão</button> <button class="btn" type="button" id="stClearToken">Remover token padrão</button></form>
  <p class="hint">Quando OAuth está configurado, ele substitui o token padrão. Um token exclusivo de TV tem prioridade sobre OAuth.</p>
  </details>
  <hr>
  <form id="stTvForm"><h2 id="stFormTitle">Cadastrar TV</h2><input type="hidden" id="stId">
   <label class="field">Nome da TV<input class="input" id="stName" maxlength="100" required placeholder="Ex.: Recepção"></label>
   <label class="field">Device ID<input class="input" id="stDevice" maxlength="36" required placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"></label>
   <label class="field">Token exclusivo (opcional)<input class="input" type="password" id="stTvToken" autocomplete="new-password" maxlength="4096" placeholder="Vazio mantém o token salvo; novas TVs usam OAuth configurado"></label>
   <label class="switch-row"><span>Remover token exclusivo e usar OAuth / conexão padrão</span><input type="checkbox" id="stUseDefault"></label>
   <button class="btn primary" type="submit">Salvar TV</button> <button class="btn" type="button" id="stCancel">Limpar formulário</button>
  </form>
 </details>
 <?php endif; ?>
</section>
