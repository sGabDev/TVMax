# TVMax

Sistema de programação e apresentação de conteúdo para TVs, monitores, computadores e celulares, controlado pelo navegador. O apresentador organiza a fila e envia comandos; os visualizadores acompanham a programação do mesmo servidor.

O projeto usa PHP, JavaScript e CSS, sem compilação do frontend. A programação fica em JSON; contas e auditoria usam SQLite. Python e LibreOffice são necessários para converter novos documentos em páginas de apresentação.

## Índice

- [Recursos](#recursos)
- [Funcionamento](#funcionamento)
- [Requisitos](#requisitos)
- [Instalação local](#instalação-local)
- [Configurações](#configurações)
- [Hospedagem](#hospedagem)
- [Contas e permissões](#contas-e-permissões)
- [Apresentador](#apresentador)
- [Visualizador](#visualizador)
- [Formatos e qualidade](#formatos-e-qualidade)
- [Auditoria](#auditoria)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Backup e migração](#backup-e-migração)
- [Manutenção](#manutenção)
- [Testes](#testes)
- [Solução de problemas](#solução-de-problemas)
- [Limitações](#limitações)

## Recursos

- Controle ao vivo: reproduzir/pausar, anterior, próximo, selecionar conteúdo e tela de espera.
- Fila recorrente, busca por título e reordenação por arraste ou botões.
- Fila completa na página, sem rolagem interna.
- Arquivamento manual ou por vencimento, preservando arquivos e permitindo recuperação.
- Arquivados em grade, com 10, 20, 50 ou 100 itens por página.
- Agendamento com início e fim, rascunho e salvamento explícito.
- Upload de imagens, vídeos, áudios, PDFs, planilhas e slides; criação de telas de texto.
- Duração de vídeos identificada pelo navegador e aplicada à programação.
- Documentos convertidos em páginas PNG de alta resolução.
- Ajuste à largura, à altura ou à tela; transições, volume, relógio e progresso.
- Interface responsiva, aprovação de cadastros, autoria dos conteúdos e auditoria.

## Funcionamento

| Entrada | Finalidade |
| --- | --- |
| `index.php` | Página inicial. |
| `apresentador.php` | Login, cadastro e painel para usuários aprovados. |
| `visualizador.php` | Exibição pública da programação, sem exigir login. |
| `administracao.php` | Contas e auditoria, exclusivo de administradores. |
| `api.php` | Entrada HTTP para autenticação, estado e comandos. |

O servidor mantém a referência de tempo, o item, a página e a posição de reprodução. Cada visualizador estima a diferença de relógio e acompanha essa referência. A consulta automática acontece aproximadamente a cada **300 ms**, sem WebSocket.

Todos os dispositivos devem abrir o **mesmo servidor**. Telas conectadas depois acompanham a posição atual. A sincronização não é exata quadro a quadro: rede, decodificação, carregamento e suspensão do navegador podem causar diferenças temporárias.

O indicador do apresentador confirma o comando salvo no servidor; não confirma individualmente seu recebimento por cada TV.

## Requisitos

### Aplicação principal

| Requisito | Utilização |
| --- | --- |
| PHP 8.1 ou superior | Backend. |
| `pdo_sqlite` | Contas, tentativas de acesso e auditoria. |
| `fileinfo` | Identificação de arquivos. |
| `mbstring` | Tratamento e validação de textos. |
| `zip` | Validação de documentos Office compactados. |
| Escrita em `data/` e `uploads/` | Persistência, sessões, logs e mídias. |
| Navegador compatível com as mídias utilizadas | Operação do painel e reprodução. |

Não é necessário MySQL. Node.js é utilizado nos testes JavaScript, não na execução da aplicação.

### Conversão de documentos

- Python **3.10+** com [scripts/requirements.txt](scripts/requirements.txt), atualmente PyMuPDF 1.28.2.
- `proc_open` habilitado no PHP.
- LibreOffice para Excel, PowerPoint e OpenDocument; PDFs usam diretamente o PyMuPDF.
- Permissão do usuário do PHP para executar os conversores e gravar temporários.
- Espaço em disco, memória e CPU suficientes para renderizar as páginas.

Imagens, vídeos, áudios, textos e páginas PNG já convertidas não dependem dos conversores.

## Instalação local

### 1. Prepare o servidor

Coloque o projeto em uma pasta atendida pelo Apache/XAMPP ou servidor PHP equivalente. Habilite as extensões necessárias e permita escrita em `data/` e `uploads/` pelo usuário do PHP.

Exemplo usando uma subpasta chamada `tvmax`:

```text
http://localhost/tvmax/
http://localhost/tvmax/apresentador.php
http://localhost/tvmax/visualizador.php
```

Adapte os endereços à instalação. Os arquivos PHP precisam ser acessados pelo servidor, não abertos diretamente no explorador de arquivos.

### 2. Crie a configuração

Copie `config/local.example.php` para `config/local.php`.

PowerShell:

```powershell
Copy-Item config/local.example.php config/local.php
```

Linux:

```bash
cp config/local.example.php config/local.php
```

Se `local.php` já existir, edite-o sem sobrescrever seus valores. Troque `ADMIN_PASSWORD` por uma chave própria antes de criar o primeiro administrador.

### 3. Prepare os conversores, se necessário

Instale as dependências no Python que o PHP irá executar:

```bash
python3 -m pip install -r scripts/requirements.txt
```

No Windows, normalmente o comando é:

```powershell
python -m pip install -r scripts/requirements.txt
```

Instale LibreOffice para converter planilhas e slides. Se os executáveis não estiverem no PATH do serviço PHP, informe caminhos completos em `config/local.php`.

O conversor também reconhece dependências locais opcionais em `.runtime/python/` e `.runtime/LibreOffice/`. Binários Windows não funcionam em uma hospedagem Linux.

### 4. Crie o primeiro administrador

1. Abra `apresentador.php`.
2. Selecione **Primeiro admin**, disponível enquanto não existir administrador.
3. Informe a chave `ADMIN_PASSWORD`, nome, e-mail e sua senha pessoal.
4. Use **Usuários e logs** para aprovar os demais cadastros.

`ADMIN_PASSWORD` é somente a chave de configuração inicial: não substitui a senha pessoal e não redefine senhas de contas existentes.

### 5. Conecte as telas

Em outro dispositivo, use o IP ou domínio real do servidor:

```text
http://192.168.1.50/tvmax/visualizador.php
```

Substitua o IP pelo endereço correto. `localhost` em uma TV aponta para a própria TV, não para o computador que hospeda o TVMax.

## Configurações

[config/settings.php](config/settings.php) carrega os valores. Personalizações ficam em `config/local.php`.

| Chave local | Variável de ambiente | Padrão | Finalidade |
| --- | --- | --- | --- |
| `ADMIN_PASSWORD` | `TVMAX_ADMIN_PASSWORD` | `troque-esta-senha` | Criar o primeiro administrador. |
| `MAX_UPLOAD_MB` | `TVMAX_MAX_UPLOAD_MB` | `100` | Limite por arquivo em MB. |
| `PYTHON` | `TVMAX_PYTHON` | `python` no Windows; `python3` nos demais sistemas | Executável Python. |
| `SOFFICE` | `TVMAX_SOFFICE` | Vazio | Caminho opcional do LibreOffice. |

Variáveis `TVMAX_*` não vazias prevalecem sobre `local.php`. As variáveis antigas `STAGETV_PYTHON` e `STAGETV_SOFFICE` continuam reconhecidas como alternativas quando não há configuração moderna correspondente.

Exemplo Linux, ajustando os caminhos à instalação:

```php
<?php
return [
    'ADMIN_PASSWORD' => 'substitua-por-uma-chave-propria',
    'MAX_UPLOAD_MB' => 100,
    'PYTHON' => '/usr/bin/python3',
    'SOFFICE' => '/usr/bin/soffice',
];
```

No Windows, configure o Python instalado e, quando necessário, o `soffice.com` do LibreOffice. O PATH do terminal pode ser diferente do PATH do Apache ou serviço PHP.

O fuso horário é **`America/Sao_Paulo`**, definido em `app/bootstrap.php`. O agendamento utiliza esse fuso.

O limite efetivo de upload também depende de `upload_max_filesize`, `post_max_size` e restrições do servidor/proxy. Configure `post_max_size` acima do limite do arquivo para comportar a requisição completa. Conversões podem exigir ajustes de memória e tempo de execução.

## Hospedagem

O [guia de hospedagem](docs/hospedagem.md) complementa as instruções desta seção.

### Arquivos a enviar

Envie para `public_html` ou uma subpasta:

- As cinco entradas PHP da raiz: `index.php`, `apresentador.php`, `visualizador.php`, `administracao.php` e `api.php`.
- `.htaccess` da raiz e todos os `.htaccess` das pastas enviadas.
- `app/`, `config/`, `assets/`, `scripts/`, `data/` e `uploads/`.

Não é necessário enviar testes, caches, instaladores, logs de instalação ou binários Windows de `.runtime/`. Os scripts PHP de manutenção são opcionais. Preserve `scripts/convert_document.py` e `scripts/requirements.txt` para conversão no servidor.

Na instalação nova, use pastas de dados novas e graváveis, preservando as proteções HTTP. Para levar as contas e conteúdos existentes, siga [Backup e migração](#backup-e-migração).

### Compatibilidade do provedor

A aplicação PHP pode operar sem conversor, mas converter novos documentos exige as dependências descritas acima. **Confirme com o provedor** a disponibilidade de Python/PyMuPDF, LibreOffice e execução de processos externos; esses recursos podem não existir em hospedagem compartilhada.

Sem conversores, envie conteúdo já convertido em imagens ou use um ambiente que permita instalá-los. Documentos anteriormente convertidos continuam sendo exibidos pelas páginas PNG existentes.

### Proteção HTTP

- Apache/LiteSpeed precisam aplicar os `.htaccess`, incluindo `Require` e `Options`.
- Nginx ignora `.htaccess` e exige regras equivalentes na configuração do servidor.
- Negue acesso HTTP a `app/`, `config/`, `data/`, `scripts/`, `tests/`, `docs/` e `.runtime/`.
- Desabilite listagem de diretórios e execução de scripts em `uploads/`.
- Use HTTPS e permissões restritas ao necessário; não aplique `777` indiscriminadamente.
- Não use o servidor embutido do PHP em produção.

Antes de liberar o acesso, confirme que `/data/state.json`, `/config/local.php`, `/app/auth.php` e arquivos de sessão estão bloqueados. Com as regras Apache fornecidas, devem retornar **403**. Inclua o prefixo da subpasta quando aplicável.

O visualizador e o estado público da programação não exigem login. Mídias são servidas por URL em `uploads/`. O login do apresentador não torna a transmissão privada; para restringir quem assiste, configure controle de acesso na rede ou no servidor.

## Contas e permissões

| Perfil/situação | Acesso |
| --- | --- |
| Visitante | Visualizador público e telas de acesso/cadastro. |
| Cadastro pendente | Aguarda aprovação; não opera o apresentador. |
| Operador aprovado | Conteúdos, fila, agendamento, configurações e controle ao vivo. |
| Administrador | Operação completa, gerenciamento de usuários e consulta dos logs. |
| Conta recusada ou suspensa | Sem acesso às operações protegidas. |

Cadastros comuns entram como operadores pendentes. Administradores podem aprovar, recusar, suspender e alterar cargos. O último administrador ativo não pode ser suspenso ou rebaixado. A suspensão bloqueia novas operações de sessões já abertas.

Senhas são armazenadas como hashes. As operações protegidas validam sessão, permissões e token CSRF. Há limites de tentativas de login/cadastro. A interface atual não oferece recuperação de senha por e-mail.

## Apresentador

### Adicionar e organizar

Use **Arquivo** ou arraste arquivos para a área de upload. Use **Texto** para criar conteúdo com título, mensagem e tempo de exibição.

Conteúdos novos registram o autor autenticado. Itens anteriores ao sistema de contas podem mostrar **Autor não registrado**; a autoria antiga não é reconstruída.

A fila mostra todos os itens na página. A busca filtra pelo título. Use as setas ou arraste os cartões para reordenar. **Exibir** seleciona um conteúdo imediatamente.

### Duração

- **Imagens e textos:** segundos por item.
- **Documentos:** segundos por página, não pelo arquivo inteiro.
- **Vídeos:** o navegador lê a duração antes do upload e o campo fica somente para leitura. Vídeos antigos sem essa informação são verificados ao abrir o apresentador.
- **Áudios:** usam o tempo configurado; a detecção automática dos vídeos não se aplica a eles.
- **Intervalo ao terminar:** tempo adicional antes do próximo item, depois da última página de um documento.

A programação preserva frações de segundo dos vídeos. O navegador deve conseguir ler os metadados do arquivo; a duração máxima aceita é de 24 horas.

### Agendamento

1. Expanda **Agendamento**.
2. Preencha **Início**, **Fim** ou ambos, no horário de Brasília.
3. Clique em **Salvar agendamento**.

Datas em branco deixam a respectiva restrição aberta. Quando ambas são preenchidas, o fim deve ser posterior ao início.

As datas editadas ficam como rascunho: não salvam automaticamente nem são enviadas ao alterar volume ou outros controles. **Cancelar alterações** restaura os valores salvos. Recarregar ou sair da página descarta rascunhos não salvos.

Outras configurações continuam com salvamento automático. Um fim já vencido torna o item elegível para arquivamento automático.

### Repetição e arquivamento

A fila repete enquanto houver conteúdo habilitado e dentro da validade. Terminar um item, avançar ou selecionar outro **não arquiva** o conteúdo.

O item vai para **Arquivados** ao clicar em **Remover** ou quando vence a validade final, inclusive com a apresentação pausada. Os arquivos originais, páginas e dados do item são preservados.

Na aba **Arquivados**, use a busca, selecione 10/20/50/100 itens por página e clique em **Recuperar para a fila**. O item volta ao final; uma data final vencida é limpa para evitar arquivamento imediato.

Arquivar não libera espaço em disco. Não há exclusão definitiva de mídias pela interface.

### Controle ao vivo

| Controle | Comportamento |
| --- | --- |
| Reproduzir / Pausar | Um único botão alterna a ação conforme o estado. |
| Anterior / Próximo | Percorre páginas; nas extremidades, troca de item. |
| Exibir | Seleciona o item e inicia a apresentação. |
| Aguardar | Pausa a programação e mostra a tela de espera. |
| Voltar | Sai da espera, restaurando a condição anterior de reprodução ou pausa. |
| Recarregar visualizador | Solicita o recarregamento das telas conectadas. |

### Ajuste da mídia

| Opção | Resultado |
| --- | --- |
| Ajustar à largura | Preenche a largura mantendo a proporção; pode cortar o excesso vertical. |
| Ajustar à altura | Preenche a altura mantendo a proporção; pode cortar o excesso horizontal. |
| Ajustar à tela | Mostra todo o conteúdo sem distorcer; pode deixar bordas. |

O ajuste é recalculado ao redimensionar, girar o dispositivo ou entrar em tela cheia. Textos se adaptam ao espaço disponível.

As transições são **Fade**, **Deslizar**, **Zoom** e **Sem efeito**, com duração de **0 a 2000 ms**. O visualizador prepara a mídia antes de trocar; durante carregamento lento, o conteúdo anterior pode permanecer visível até o próximo estar pronto.

O painel também controla volume geral, silenciamento, relógio, barra de progresso e cor de fundo.

## Visualizador

1. Abra `visualizador.php` em cada dispositivo, na mesma instalação.
2. Acione o botão de tela cheia.
3. Clique em **Habilitar áudio nesta tela**, mesmo que a fila ainda esteja vazia. A ativação vale para esta abertura do visualizador; após recarregar, ative novamente.
4. Mantenha a página ativa e a conexão disponível.

O botão aparece também durante pausa e tela de espera. A ativação prepara os players para os próximos conteúdos, respeitando volume, silenciamento e pausa definidos no apresentador. Se o navegador bloquear a reprodução, o botão continua disponível para nova tentativa. Antes da ativação, a reprodução automática permanece sem som.

Autoplay com som e tela cheia podem exigir interação. Dispositivos diferentes podem suportar codecs diferentes, mesmo com a mesma extensão de arquivo.

Evite suspensão da TV, computador ou aba. Ao reconectar ou retornar à aba, o visualizador consulta o estado e se realinha.

## Formatos e qualidade

| Categoria | Formatos aceitos pelo backend |
| --- | --- |
| Imagens | JPEG, PNG, GIF, WebP. |
| Vídeos | MP4, WebM, Ogg Video. |
| Áudios | MPEG/MP3, Ogg, WAV, conforme identificação MIME. |
| PDF | PDF sem senha, convertido em páginas. |
| Planilhas | XLS, XLSX, ODS. |
| Apresentações | PPT, PPTX, PPS, PPSX, ODP. |
| Texto | Criado diretamente no painel. |

A aceitação considera o conteúdo/MIME: renomear a extensão não converte o arquivo. Não há inclusão de sites na fila.

Documentos são renderizados em PNG com **3840 pixels no maior lado**, preservando a proporção, com limite de **300 páginas**. Isso oferece resolução acima de Full HD para telas 1080p; não significa que todos os documentos tenham dimensões 3840 × 2160.

Fotos e vídeos usam os originais. Aumentar a resolução não recupera detalhes ausentes no original. Apresentações antigas podem oferecer **Melhorar qualidade** para regenerar as páginas.

Planilhas seguem a paginação de impressão: ajuste área, orientação e escala antes do envio. Slides viram páginas estáticas, sem animações ou reprodução de mídias incorporadas. Fontes disponíveis no servidor podem afetar o resultado do LibreOffice.

A conversão acontece no servidor, sem envio dos documentos a serviços externos.

## Auditoria

Administradores consultam **Usuários e logs**, com filtro por usuário, busca textual e carregamento de registros anteriores.

O histórico inclui autenticação e falhas, gerenciamento de contas, uploads, textos, alterações de fila, configurações, agendamento, duração de vídeos, comandos, qualidade, arquivamento, recuperação e vencimentos automáticos.

Conforme a ação, há autor, horário, IP, resultado, identificador do conteúdo e valores anteriores/novos. Eventos informados pelo cliente são diferenciados das alterações efetivas do servidor.

- Ações automáticas são atribuídas ao sistema; telas sem login podem aparecer como visitante.
- Senhas, hashes, tokens e chaves de configuração não entram nos detalhes da auditoria.
- Consultas periódicas de estado não geram um registro a cada 300 ms.
- O histórico não reconstrói eventos anteriores à implantação da auditoria.
- Não há exclusão de logs pela interface nem garantia de armazenamento imutável: administradores dos arquivos do servidor podem alterá-los.

## Estrutura do projeto

```text
TVMax/
├── index.php                  # Página inicial
├── apresentador.php           # Acesso e painel
├── visualizador.php           # Exibição nas TVs
├── administracao.php          # Usuários e auditoria
├── api.php                    # Entrada pública da API
├── .htaccess                  # Proteção HTTP da raiz
├── README.md
├── app/
│   ├── bootstrap.php          # Inicialização, sessão e estado
│   ├── auth.php               # Contas, permissões e auditoria
│   ├── timeline.php           # Linha do tempo do servidor
│   ├── documents.php          # Integração com o conversor
│   ├── http/api.php           # Implementação da API
│   └── views/auth_screen.php  # Formulários de acesso
├── config/
│   ├── settings.php           # Carregamento da configuração
│   ├── local.example.php      # Modelo de configuração
│   └── local.php              # Criado durante a instalação
├── assets/                    # JavaScript, estilos e logos
├── data/
│   ├── state.json             # Programação e configurações
│   ├── state.json.lock        # Coordenação das escritas
│   ├── accounts.sqlite        # Contas e auditoria
│   ├── sessions/              # Sessões PHP
│   ├── conversion.log         # Erros do conversor
│   └── conversion-profiles/   # Perfis temporários do LibreOffice
├── uploads/                   # Originais e páginas convertidas
├── scripts/                   # Conversão e manutenção
├── tests/                     # Testes JS, PHP e HTTP
├── docs/                      # Documentação complementar
└── .runtime/                  # Dependências locais opcionais
```

Alguns arquivos e pastas de dados só são criados quando utilizados. SQLite também pode criar auxiliares `-wal` e `-shm`. Pastas privadas possuem `.htaccess` próprios.

A identidade visual está em `assets/tvmax-mark.svg`, `assets/tvmax-logo.svg` e `assets/theme.css`.

## Backup e migração

### O que preservar

| Caminho | Conteúdo |
| --- | --- |
| `data/state.json` | Fila ativa/arquivada, tela e reprodução. |
| `data/accounts.sqlite` e estado SQLite consistente | Contas e histórico. |
| `uploads/` inteiro | Originais e páginas referenciadas na programação. |
| `config/local.php` e variáveis do ambiente | Ajustes da instalação. |

Proteja os backups: eles contêm contas, auditoria e mídias.

### Procedimento

1. Interrompa o acesso à aplicação durante a cópia, inclusive dos visualizadores: consultas de estado podem processar vencimentos e gravar alterações.
2. Copie `data/` e `uploads/` juntos, além da configuração. Não copie somente o banco principal SQLite enquanto houver gravações; preserve os auxiliares ou use uma ferramenta de backup consistente do SQLite.
3. Transfira o código e os dados ao destino.
4. Ajuste executáveis, permissões e proteção HTTP.
5. Valide login, arquivos, fila, visualizador e conversão antes de liberar acesso.

Os caminhos de mídia são relativos à instalação. Originais são encontrados em `uploads/`, sem depender de caminhos absolutos antigos do computador de origem. É normal precisar entrar novamente após migrar.

Em atualizações, faça backup primeiro e preserve `config/local.php`, `data/` e `uploads/`. Não os substitua por arquivos vazios ou de demonstração.

## Manutenção

Execute a partir da raiz com o PHP apropriado. Estes scripts modificam dados reais: faça backup antes.

```bash
php scripts/upgrade_quality.php
php scripts/migrate_archive_policy.php
```

- `upgrade_quality.php`: regenera PDFs/apresentações antigos ainda não marcados com a qualidade atual; precisa dos originais e conversores.
- `migrate_archive_policy.php`: aplica a política atual, podendo devolver à fila itens arquivados pela antiga regra de conclusão e processar vencimentos. Remoções manuais são preservadas.

Não apague originais ou páginas para limpar a fila: itens ativos ou arquivados podem referenciá-los. Não remova arquivos de lock ou auxiliares SQLite durante o uso.

Acompanhe o espaço em `uploads/` e `data/`: arquivamento preserva mídias e a auditoria acumula registros. Caches, instaladores e temporários de testes podem ser limpos quando não houver processos correspondentes em execução. Remova dependências de `.runtime/` somente se a instalação não depender delas.

## Testes

Execute na raiz. Os testes JavaScript usam Node.js, sem instalação de pacotes npm:

```bash
node tests/viewer.test.cjs
node tests/ui-state.test.cjs
node tests/media-layout.test.cjs
node tests/sync.test.cjs
node tests/parity.test.cjs
php tests/queue.php
```

| Teste | Cobertura principal |
| --- | --- |
| `viewer.test.cjs` | Linha do tempo, páginas, repetição e pausa. |
| `ui-state.test.cjs` | Controles, transições, agendamento e paginação. |
| `media-layout.test.cjs` | Proporção e ajuste das mídias. |
| `sync.test.cjs` | Relógio e ordenação das respostas. |
| `parity.test.cjs` | Consistência entre PHP e JavaScript. |
| `queue.php` | Repetição, navegação, validade e arquivamento. |

O teste de paridade usa `STAGETV_PHP` para selecionar o PHP, com fallback para `C:\xampp\php\php.exe`. Defina essa variável em outro ambiente.

Integração HTTP:

```bash
python tests/integration.py --auth-only
python tests/integration.py --queue-only
python tests/integration.py
```

- `--auth-only`: contas, aprovação, permissões, autoria e auditoria.
- `--queue-only`: fila, agendamento e duração de vídeos.
- Sem opção: uploads reais, conversão PDF/XLSX/PPTX/ODP e controles.

O teste cria uma cópia temporária em `.runtime/integration-*` e usa `127.0.0.1:8765`, sem acessar os dados reais da instalação. A porta deve estar livre e `.runtime/` precisa existir e ser gravável.

**O executor de integração atual foi preparado para Windows/XAMPP:** usa `C:\xampp\php\php.exe`, opções de processo Windows e, no teste completo, LibreOffice em `.runtime/LibreOffice/`. Importa PyMuPDF mesmo nos modos reduzidos. Para executá-lo em outro ambiente, adapte esses caminhos/opções; essa restrição é do executor de testes, não do backend publicado.

Os testes não substituem conferir layout, reprodução, áudio e legibilidade nos modelos reais de TV/navegador utilizados.

## Solução de problemas

| Sintoma | Verificação |
| --- | --- |
| Tela vazia ou aguardando | Confira fila ativa, validade, pausa/espera, conexão e disponibilidade das mídias. |
| TV não abre o sistema | Use IP/domínio real; confira firewall, rede e subpasta. Não use `localhost` no dispositivo remoto. |
| Telas em posições diferentes | Confira mesma instalação, conexão, aba ativa e carregamento. Recarregue pelo painel após resolver a causa. |
| Vídeo sem som/autoplay | Ative a mídia por interação; confira volume, silenciamento e suporte ao codec. |
| Falha ao ler duração do vídeo | Verifique se o navegador lê os metadados/reproduz o arquivo; envie pelo apresentador. |
| Conteúdo cortado | Selecione **Ajustar à tela**. |
| Documento pouco nítido | Confira original, páginas geradas e **Melhorar qualidade** nos itens antigos. |
| Planilha em muitas páginas | Ajuste área de impressão, orientação e escala antes de enviar. |
| Conversão falha | Confira Python/PyMuPDF, LibreOffice para Office, `proc_open`, permissões e `data/conversion.log`. PDFs com senha não são aceitos. |
| Funciona no terminal, mas não no PHP | Confira usuário/PATH do serviço e configure caminhos completos dos executáveis. |
| Upload acima do limite | Verifique `MAX_UPLOAD_MB`, limites PHP/proxy e espaço em disco. |
| Cadastro não consegue entrar | Confira aprovação, recusa ou suspensão com um administrador. |
| CSRF ou sessão expirada | Reabra o apresentador, entre novamente e confira cookies e escrita em `data/sessions/`. |
| SQLite somente leitura | Confira `pdo_sqlite` e escrita em `data/`, incluindo criação dos auxiliares. |
| Erro 500 após publicar | Consulte o log PHP/servidor; confira extensões e suporte ao `.htaccess`. |
| Erro de JSON na API | Avisos PHP ou uma página de erro podem estar substituindo a resposta JSON; examine a resposta HTTP e os logs. |
| Dados privados acessíveis por URL | Corrija as regras HTTP antes de liberar a instalação. |
| Espaço não diminui após remover | **Remover** arquiva; não apaga arquivos. |

## Limitações

- Uma programação compartilhada por instalação, sem grupos independentes de telas no painel.
- Sincronização HTTP sem garantia quadro a quadro nem confirmação individual de cada TV.
- Visualizador público, sem modo offline completo.
- Reprodução depende de codecs e políticas do navegador; não há transcodificação automática de vídeo/áudio.
- Documentos estáticos, sem animações ou mídias internas dos slides.
- Sem recuperação de senha por e-mail, exclusão definitiva de mídias ou limpeza de logs pela interface.
- Alta resolução e muitos visualizadores aumentam uso de armazenamento, banda e requisições; dimensione o servidor para a carga real.
- Dependências externas possuem licenças próprias; confira os termos antes de redistribuir binários.

Para detalhes de implantação, consulte [docs/hospedagem.md](docs/hospedagem.md).
