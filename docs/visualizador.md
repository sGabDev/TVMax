# Reprodução e recuperação do visualizador

- As mídias de `uploads/` têm cache HTTP de 6 horas em Apache com `mod_headers`. Em HTTPS ou localhost, o service worker também prepara as mídias da fila em segundo plano e reutiliza os arquivos completos, inclusive nas requisições por trechos dos vídeos.
- O cache local dura até 6 horas, com limite de 512 MB, 500 arquivos e 256 MB por arquivo. Arquivos maiores continuam pela rede/cache HTTP normal. O navegador pode limitar ou remover o armazenamento. APIs, credenciais e páginas do painel não são armazenadas pelo worker.
- A primeira reprodução ainda depende do download. O cache reduz downloads repetidos; não elimina limitações de codec, memória ou processamento da TV. O visualizador mantém a sincronização com a transmissão.
- Falhas de mídia/carregamento, travamentos detectados e erros de JavaScript agendam recarregamento após 5 segundos. O limite é de 3 recarregamentos em 10 minutos por aba, persistido na sessão quando disponível. Sem rede, preserva-se a reprodução atual em vez de recarregar.
- A perda de sincronização mantém a reprodução e tenta recuperar a API; após 60 segundos de falhas pode recarregar. Pausas, intervalos e pedidos de permissão de áudio não provocam recuperação automática.
- O cursor desaparece após 3 segundos parado e volta ao mover ou tocar. A autorização de som fica no canto inferior esquerdo e exige interação no navegador da própria TV.
- Pausar e o estado legado Aguardar mostram `app/views/paused_screen.php`. Reproduzir retorna à apresentação. O apresentador oferece um único controle de pausa; tempo/agendamento e aparência ficam em seções expansíveis.

Verificações: `node tests/viewer-cache.test.cjs`, `node tests/viewer-resilience.test.cjs`, `node tests/ui-state.test.cjs`, `node tests/viewer.test.cjs` e `node tests/sync.test.cjs`.
