<?php
require __DIR__.'/app/bootstrap.php';
$user=current_user();
if(!$user){audit_event('admin.denied',['reason'=>'not_logged_in'],null,'failure');header('Location: apresentador.php');exit;}
if($user['role']!=='admin'){audit_event('admin.denied',['reason'=>'not_admin'],null,'failure');http_response_code(403);echo 'Acesso exclusivo de administradores.';exit;}
audit_event('admin.open');
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Administração • <?=APP_NAME?></title><link rel="stylesheet" href="assets/app.css?v=<?=filemtime(__DIR__.'/assets/app.css')?>"><link rel="icon" type="image/svg+xml" href="assets/tvmax-mark.svg"><link rel="stylesheet" href="assets/theme.css?v=<?=filemtime(__DIR__.'/assets/theme.css')?>"></head>
<body class="admin-page">
<header class="topbar"><div class="brand"><img class="brand-mark" src="assets/tvmax-mark.svg" alt="" width="40" height="40"><div><b><?=APP_NAME?></b><small>Administração</small></div></div><a class="btn small" href="apresentador.php">Voltar ao apresentador</a></header>
<main class="admin-shell">
 <h1>Usuários e auditoria</h1><p class="hint">Aprove solicitações, gerencie o acesso e consulte o histórico de ações.</p>
 <div class="account-tabs"><button class="btn" id="usersTab" aria-pressed="true">Usuários</button><button class="btn" id="logsTab" aria-pressed="false">Logs de atividades</button></div>
 <p id="adminMessage" role="status" aria-live="polite"></p>
 <section id="usersPanel" class="panel"><div class="panel-head"><h2>Contas cadastradas</h2><button class="btn small" id="refreshUsers">Atualizar</button></div><div id="usersList"></div></section>
 <section id="logsPanel" class="panel" hidden>
  <h2>Histórico de atividades</h2>
  <form id="logFilters" class="log-filters"><label>Usuário<select id="logActor" class="input"><option value="">Todos os usuários e sistema</option></select></label><label>Buscar ação ou detalhe<input id="logQuery" class="input" type="search" maxlength="120" placeholder="Ex.: upload, pausa, nome…"></label><button class="btn primary">Pesquisar</button></form>
  <p class="hint">Inclui ações concluídas, tentativas recusadas, alterações e eventos automáticos. Eventos de interface são identificados separadamente.</p>
  <div id="logsList" class="audit-list"></div><button id="moreLogs" class="btn wide" hidden>Carregar registros anteriores</button>
 </section>
</main>
<script>globalThis.StageAuth=<?=json_encode(['csrf'=>csrf_token(),'user'=>$user,'panel'=>true],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<script src="assets/admin.js?v=<?=filemtime(__DIR__.'/assets/admin.js')?>"></script>
</body></html>
