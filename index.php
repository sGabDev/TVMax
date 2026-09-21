<?php require __DIR__.'/app/bootstrap.php'; ?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=APP_NAME?></title>
<link rel="stylesheet" href="assets/app.css">
<link rel="icon" type="image/svg+xml" href="assets/tvmax-mark.svg"><link rel="stylesheet" href="assets/theme.css?v=<?=filemtime(__DIR__.'/assets/theme.css')?>"></head>
<body class="landing">
<div class="landing-card">
  <div class="brand"><img class="brand-mark" src="assets/tvmax-mark.svg" alt="" width="40" height="40"><div><b><?=APP_NAME?></b><small>Apresentação em tempo real</small></div></div>
  <div class="landing-kicker"><span class="dot"></span>Suas telas, em sintonia</div>
  <h1>Uma programação.<br>Todas as suas telas.</h1>
  <p>Envie imagens, vídeos, textos e páginas; organize a fila e controle a exibição ao vivo.</p>
  <div class="landing-actions">
    <a class="btn primary" href="apresentador.php">Abrir apresentador</a>
    <a class="btn" href="visualizador.php" target="_blank">Abrir visualizador</a>
  </div>
  <div class="landing-features"><div><b>Controle ao vivo</b><span>Organize a fila e comande a apresentação de qualquer lugar.</span></div><div><b>Conteúdo em alta definição</b><span>Imagens, vídeos e documentos com cada página em destaque.</span></div><div><b>Equipe conectada</b><span>Acessos aprovados e histórico das atividades em um só painel.</span></div></div>
</div>
</body></html>
