# Instalação em hospedagem

## PHP e armazenamento

Use PHP 8.1 ou superior, com `pdo_sqlite`, `fileinfo`, `mbstring` e `zip`. O PHP precisa escrever em `data/` e `uploads/`. Não use permissões 777 indiscriminadamente; configure o proprietário conforme seu provedor.

Envie as cinco entradas PHP da raiz, `.htaccess`, `app/`, `config/`, `assets/`, `scripts/`, `data/` e `uploads/` para `public_html` ou uma subpasta. Os caminhos são relativos à instalação, sem depender do XAMPP. Inclua os arquivos `.htaccess` das subpastas.

Para migrar o sistema existente, pare os uploads e as alterações durante a cópia e transfira `data/` e `uploads/` juntos. SQLite pode usar arquivos `accounts.sqlite-wal` e `accounts.sqlite-shm`: não copie somente o banco principal enquanto houver escrita. Faça backup antes da transferência. As contas, auditoria, fila e páginas convertidas serão preservadas. Sessões ficam em `data/sessions/`; é normal precisar entrar novamente após migrar.

Copie `config/local.example.php` para `config/local.php`. Configure a chave `ADMIN_PASSWORD` antes de criar o primeiro administrador. Não há necessidade de recriar administradores de uma instalação já existente. Variáveis `TVMAX_ADMIN_PASSWORD`, `TVMAX_MAX_UPLOAD_MB`, `TVMAX_PYTHON` e `TVMAX_SOFFICE` têm precedência sobre o arquivo local.

No painel da hospedagem, ajuste `upload_max_filesize`, `post_max_size` (maior que o limite de upload), memória e tempo de execução. Abra `index.php` e confira login, upload, apresentador e visualizador. Use HTTPS.

## Documentos PDF, Excel e PowerPoint

Converter novos documentos exige `proc_open`, Python 3.10+ com PyMuPDF e, para Office, LibreOffice. Essas dependências **não são fornecidas pelo PHP** e frequentemente não estão disponíveis em hospedagem compartilhada. Confirme com o provedor; se não houver suporte, use VPS ou envie imagens/vídeos e documentos já convertidos em imagens. As páginas PNG já existentes continuam funcionando sem conversor.

Instale as dependências no servidor de destino, por exemplo `python3 -m pip install -r scripts/requirements.txt`, e instale LibreOffice pelo mecanismo do servidor. Configure `PYTHON` e `SOFFICE` em `config/local.php` se os executáveis não estiverem no PATH. A instalação local opcional em `.runtime/` continua reconhecida, mas seus binários Windows não funcionam no Linux. Nenhum caminho `C:\Python313` é exigido pela aplicação.

## Proteção das pastas internas

Apache/LiteSpeed precisam permitir as regras `.htaccess` (`Require` e `Options`). Verifique que URLs como `/data/state.json`, `/config/local.php`, `/app/auth.php` e arquivos de sessão retornam **403**, não o conteúdo. Na instalação em subpasta, inclua o prefixo nas URLs.

Nginx ignora `.htaccess`: peça ao provedor regras equivalentes para negar acesso HTTP a `app`, `config`, `data`, `scripts`, `tests`, `docs` e `.runtime`, desabilitar listagem e impedir execução de scripts em `uploads`. Isso deve ser configurado no servidor antes da publicação. Não use o servidor embutido do PHP em produção.

Não envie `.runtime/`, `tests/`, caches `__pycache__`, instaladores nem logs de instalação para a hospedagem. `scripts/convert_document.py` e `scripts/requirements.txt` devem ser enviados para permitir conversão. Os scripts PHP de manutenção são opcionais.
