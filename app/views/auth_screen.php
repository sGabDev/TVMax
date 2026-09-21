<?php $setup=first_admin_needed(); ?>
<div class="login-wrap"><section class="login-card account-card">
 <div class="brand"><img class="brand-mark" src="assets/tvmax-mark.svg" alt="" width="40" height="40"><div><b><?=APP_NAME?></b><small>Acesso ao apresentador</small></div></div>
 <div class="account-tabs" role="group" aria-label="Acesso"><button type="button" class="btn small" data-auth-tab="login">Entrar</button><button type="button" class="btn small" data-auth-tab="register">Cadastrar</button><?php if($setup): ?><button type="button" class="btn small" data-auth-tab="setup">Primeiro admin</button><?php endif; ?></div>
 <form id="loginForm" data-auth-form="login" <?= $setup?'hidden':'' ?>>
  <h1>Bem-vindo de volta</h1><p>Entre com sua conta aprovada.</p>
  <label class="field">E-mail<input class="input" type="email" name="email" autocomplete="username" required maxlength="254"></label>
  <label class="field">Senha<input class="input" type="password" name="password" autocomplete="current-password" required></label>
  <button class="btn primary wide">Entrar</button>
 </form>
 <form id="registerForm" data-auth-form="register" hidden>
  <h1>Solicitar acesso</h1><p>Um administrador precisa aprovar seu cadastro antes de você usar o apresentador.</p>
  <label class="field">Nome completo<input class="input" name="name" autocomplete="name" minlength="2" maxlength="100" required></label>
  <label class="field">E-mail<input class="input" type="email" name="email" autocomplete="email" maxlength="254" required></label>
  <label class="field">Senha<input class="input" type="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required></label>
  <label class="field">Confirmar senha<input class="input" type="password" name="passwordConfirm" autocomplete="new-password" required></label>
  <button class="btn primary wide">Enviar cadastro para aprovação</button>
 </form>
 <?php if($setup): ?><form id="setupForm" data-auth-form="setup">
  <h1>Criar primeiro administrador</h1><p>Use a senha atual do sistema como chave de configuração. Depois, o acesso será pelo e-mail e pela senha desta conta.</p>
  <label class="field">Chave de configuração<input class="input" type="password" name="setupKey" autocomplete="off" required></label>
  <label class="field">Seu nome<input class="input" name="name" autocomplete="name" minlength="2" maxlength="100" required></label>
  <label class="field">Seu e-mail<input class="input" type="email" name="email" autocomplete="email" maxlength="254" required></label>
  <label class="field">Nova senha<input class="input" type="password" name="password" autocomplete="new-password" minlength="8" maxlength="72" required></label>
  <label class="field">Confirmar nova senha<input class="input" type="password" name="passwordConfirm" autocomplete="new-password" required></label>
  <button class="btn primary wide">Criar administrador</button>
 </form><?php endif; ?>
 <p id="authMessage" class="form-error" role="status" aria-live="polite"></p>
</section></div>
<script>globalThis.StageAuth=<?=json_encode(['csrf'=>csrf_token()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<script src="assets/auth.js?v=<?=filemtime(APP_ROOT.'/assets/auth.js')?>"></script>
