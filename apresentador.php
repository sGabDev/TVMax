<?php require __DIR__.'/app/bootstrap.php'; $user=current_user(); header('Cache-Control: no-store'); audit_event($user?'presenter.open':'account.open'); ?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Apresentador • <?=APP_NAME?></title><link rel="stylesheet" href="assets/app.css?v=<?=filemtime(__DIR__.'/assets/app.css')?>">
<link rel="icon" type="image/svg+xml" href="assets/tvmax-mark.svg"><link rel="stylesheet" href="assets/theme.css?v=<?=filemtime(__DIR__.'/assets/theme.css')?>"></head><body class="dashboard<?=$user?' presenter-ready':''?>">
<?php if(!$user): require __DIR__.'/app/views/auth_screen.php'; ?>
<?php else: ?>
<header class="topbar">
 <div class="brand"><img class="brand-mark" src="assets/tvmax-mark.svg" alt="" width="40" height="40"><div><b><?=APP_NAME?></b><small>Central de transmissão</small></div></div>
 <div class="status"><span class="dot"></span><span id="connectionLabel" role="status">Conectando ao servidor...</span></div>
 <div class="top-actions"><span class="session-name"><?=htmlspecialchars($user['name'],ENT_QUOTES,'UTF-8')?></span><?php if($user['role']==='admin'): ?><a class="btn small" href="administracao.php">Usuários e logs</a><?php endif; ?><a class="btn small" id="openViewer" href="visualizador.php" target="_blank" rel="noopener">Abrir TV ↗</a><button class="btn small" id="logout">Sair</button></div>
</header>
<main class="shell">
 <div class="account-tabs" role="group" aria-label="Área do apresentador"><button class="btn" id="presentationTab" aria-pressed="true">Conteúdo na tela</button><button class="btn" id="smartthingsTab" aria-pressed="false">Ligar / desligar TVs</button></div>
 <?php require __DIR__.'/app/views/smartthings.php'; ?>
 <div id="presentationPanel">
 <ol class="presenter-guide" aria-label="Como usar"><li><b>1. Adicione</b><span>Escolha fotos, vídeos ou documentos.</span></li><li><b>2. Organize</b><span>A lista toca na ordem, de cima para baixo.</span></li><li><b>3. Apresente</b><span>Use Reproduzir ou Exibir agora em um arquivo.</span></li></ol>
 <section class="hero-panel">
  <div><span class="eyebrow">TRANSMISSÃO</span><h1>O que aparece nas TVs</h1><p id="playbackStatus" class="playback-status" role="status">Conectando...</p><p id="commandStatus" class="hint" role="status" aria-live="polite"></p><p id="nowLabel">Carregando programação...</p></div>
  <div class="transport">
   <button class="round" data-cmd="prev" title="Anterior"><span aria-hidden="true">⏮</span><small>Anterior</small></button>
   <button class="round main" id="playPause" data-cmd="play" title="Reproduzir" aria-label="Reproduzir" disabled><span id="playPauseIcon" aria-hidden="true">▶</span><small id="playPauseLabel">Reproduzir</small></button>
   <button class="round" data-cmd="next" title="Próximo"><span aria-hidden="true">⏭</span><small>Próximo</small></button>
  </div>
 </section>

 <div class="grid">
  <section class="panel span2 queue-panel">
   <div class="panel-head"><div><h2>Seus arquivos</h2></div>
   <div class="panel-actions"><button class="btn primary" id="uploadBtn">＋ Adicionar arquivos</button><button class="btn" id="textBtn">Escrever mensagem</button><button class="btn" id="screenSettingsToggle" aria-expanded="false" aria-controls="screenSettings">Som e ajustes</button></div></div>
   <input type="file" id="fileInput" multiple hidden accept="image/*,video/*,audio/*,.pdf,.xlsx,.xls,.pptx,.ppt,.ppsx,.pps,.odp,.ods">
   <div class="dropzone" id="dropzone"><b>Arraste arquivos para cá</b><span>Imagens, vídeos, áudios, PDF, Excel e PowerPoint • até <?=MAX_UPLOAD_MB?> MB</span></div>
   <p class="hint">Exibir agora coloca o arquivo na tela imediatamente. Pausar mantém a TV ligada e mostra o aviso de transmissão pausada.</p>
   <div class="queue-tools">
    <div class="queue-tabs" role="group" aria-label="Listas de conteúdo"><button class="btn small active" id="queueTab" aria-pressed="true">Na fila <span id="queueCount">0</span></button><button class="btn small" id="archiveTab" aria-pressed="false">Arquivados <span id="archiveCount">0</span></button></div>
    <input id="queueSearch" type="search" class="input" placeholder="Buscar pelo título…" aria-label="Buscar conteúdo">
    <p class="hint" id="queueSummary">Os itens repetem na fila. Apenas validade vencida ou remoção envia para Arquivados.</p>
   </div>
   <div class="playlist" id="playlist" tabindex="0" aria-label="Fila de exibição"></div>
   <div class="playlist" id="archiveList" tabindex="0" aria-label="Conteúdos arquivados" hidden></div>
   <nav id="archivePagination" class="archive-pagination" aria-label="Páginas dos arquivados" hidden><label>Itens por página <select id="archivePageSize" class="input"><option>10</option><option>20</option><option>50</option><option>100</option></select></label><span id="archivePageLabel" role="status"></span><div><button id="archivePrev" class="btn small">Anterior</button> <button id="archiveNext" class="btn small">Próxima</button></div></nav>
  </section>

  <aside class="panel" id="screenSettings" hidden>
   <div class="panel-head"><div><span class="eyebrow">TELA</span><h2>Configurações</h2></div></div>
   <label class="field">Volume geral <span id="volLabel">70%</span><input id="volume" type="range" min="0" max="100"></label>
   <label class="switch-row"><span>Silenciar</span><input id="muted" type="checkbox"></label>
   <details class="screen-options"><summary>Aparência e transições</summary>
   <label class="switch-row"><span>Relógio na TV</span><input id="showClock" type="checkbox"></label>
   <label class="switch-row"><span>Barra de progresso</span><input id="showProgress" type="checkbox"></label>
   <label class="field">Ajuste da mídia<select id="fit" class="input" aria-describedby="fitHint"><option value="width">Ajustar à largura</option><option value="height">Ajustar à altura</option><option value="contain" selected>Ajustar à tela</option></select></label>
   <p class="hint" id="fitHint">Mostra todo o conteúdo sem cortes, mantendo a proporção em celulares e TVs.</p>
   <label class="field">Transição<select id="transition" class="input"><option value="fade">Fade</option><option value="slide">Deslizar</option><option value="zoom">Zoom</option><option value="none">Sem efeito</option></select></label>
   <label class="field">Duração da transição <span id="transitionMsLabel">650 ms</span><input id="transitionMs" type="range" min="0" max="2000" step="50" value="650"></label>
   <label class="field">Fundo<input id="background" class="input color" type="color"></label>
   </details>
   <details class="screen-options"><summary>Ajuda e recuperação</summary>
   <p class="hint">Pausar mostra a tela de transmissão pausada em todas as TVs. Reproduzir retoma do mesmo ponto.</p>
   <p class="hint">Para permitir o som, toque em Ativar som no canto da própria TV. O visualizador tenta se recuperar automaticamente de falhas.</p>
   <button class="btn wide" data-cmd="reload">↻ Recarregar visualizador</button>
   </details>
  </aside>
 </div>
 </div>
</main>

<div class="modal" id="textModal"><div class="modal-card"><button class="modal-x">×</button><h2>Novo texto</h2>
<label class="field">Nome da mensagem<input id="textTitle" class="input" placeholder="Ex.: Boas-vindas"></label><label class="field">Mensagem na TV<textarea id="textContent" class="input area" placeholder="Digite o texto que aparecerá na TV"></textarea></label>
<div class="modal-row"><label class="field">Tempo (s)<input id="textDuration" class="input" type="number" min="1" value="10"></label></div>
<button class="btn primary wide" id="saveText">Adicionar à fila</button></div></div>

<div class="toast" id="toast" role="status" aria-live="polite"></div>
<script>globalThis.StageAuth=<?=json_encode(['csrf'=>csrf_token(),'user'=>$user,'panel'=>true],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<script src="assets/timeline.js?v=<?=filemtime(__DIR__.'/assets/timeline.js')?>"></script>
<script src="assets/sync.js?v=<?=filemtime(__DIR__.'/assets/sync.js')?>"></script>
<script src="assets/presenter.js?v=<?=filemtime(__DIR__.'/assets/presenter.js')?>"></script>
<script src="assets/smartthings.js?v=<?=filemtime(__DIR__.'/assets/smartthings.js')?>"></script>
<?php endif; ?></body></html>
