<?php require __DIR__.'/app/bootstrap.php'; audit_event('viewer.open'); ?>
<!doctype html><html lang="pt-BR"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<meta name="theme-color" content="#000000"><title>Visualizador • <?=APP_NAME?></title>
<link rel="stylesheet" href="assets/app.css?v=<?=filemtime(__DIR__.'/assets/app.css')?>">
<link rel="icon" type="image/svg+xml" href="assets/tvmax-mark.svg"><link rel="stylesheet" href="assets/theme.css?v=<?=filemtime(__DIR__.'/assets/theme.css')?>"></head><body class="viewer">
<div id="stage" class="stage">
 <div id="layerA" class="media-layer active"></div><div id="layerB" class="media-layer"></div>
 <div id="empty" class="empty-screen"><img class="brand-mark big" src="assets/tvmax-mark.svg" alt="" width="80" height="80"><h1><?=APP_NAME?></h1><p>Aguardando conteúdo do apresentador...</p></div>
 <div id="blackout" class="blackout standby-screen" aria-hidden="true">
  <div class="standby-card"><img class="standby-logo" src="assets/tvmax-mark.svg" alt="" width="100" height="100">
   <p class="standby-brand"><?=APP_NAME?></p><h1>Aguardando</h1><p>A apresentação continua em instantes.</p>
  </div>
 </div>
 <div id="viewerNotice" class="viewer-notice" role="status" hidden></div>
 <button id="enableMedia" class="btn media-enable" hidden>Ativar reprodução de áudio/vídeo</button>
 <div id="clock" class="viewer-clock"></div>
 <div id="progressWrap" class="progress-wrap"><div id="progress"></div></div>
 <button id="fsBtn" class="fs-btn" title="Tela cheia">⛶</button>
</div>
<script>globalThis.StageAuth=<?=json_encode(['csrf'=>csrf_token()],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT)?>;</script>
<script src="assets/timeline.js?v=<?=filemtime(__DIR__.'/assets/timeline.js')?>"></script>
<script src="assets/sync.js?v=<?=filemtime(__DIR__.'/assets/sync.js')?>"></script>
<script src="assets/media-layout.js?v=<?=filemtime(__DIR__.'/assets/media-layout.js')?>"></script>
<script src="assets/viewer.js?v=<?=filemtime(__DIR__.'/assets/viewer.js')?>"></script>
</body></html>
