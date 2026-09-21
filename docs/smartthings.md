# SmartThings: OAuth e controle de TVs

Na aba **TVs · SmartThings**, administradores configuram a integração; operadores aprovados consultam e controlam as TVs.

## Conectar com Client ID e Client Secret

1. Abra o TVMax pelo endereço público HTTPS. Em **Conexão OAuth**, informe Client ID, Client Secret e a URL da pasta da aplicação, por exemplo `https://tv.exemplo.com/tvmax` (sem `apresentador.php`).
2. Para usar o webhook, informe também o **App ID** da aplicação SmartThings. Ele é diferente do Client ID.
3. Clique em **Salvar OAuth e gerar links**. O segredo salvo não é devolvido ao navegador; deixe o campo vazio para mantê-lo. Alterar credenciais, App ID ou URL pública exige conectar novamente.
4. Copie **Callback OAuth · Redirect URI** para os Redirect URIs do OAuth-In App no SmartThings. A URL completa deve coincidir exatamente. O callback será `https://tv.exemplo.com/tvmax/smartthings-callback.php`.
5. Configure as permissões `r:devices:*` e `x:devices:*` na aplicação SmartThings.
6. Clique em **Conectar conta Samsung**, autorize o acesso e volte às TVs. Cadastre o nome e Device ID de cada TV. Sem token exclusivo, as TVs usam a conta OAuth conectada.

O fluxo usa os endpoints `/oauth/authorize` e `/oauth/token` documentados para OAuth-In Apps no [guia oficial](https://developer.smartthings.com/docs/getting-started/quickstart). Client ID e Client Secret não são tokens Bearer e não devem ser colados nos campos de token manual.

Se estiver no XAMPP local, publique por HTTPS ou configure um túnel para esta instalação. Abra o painel pelo endereço público antes de iniciar a autorização, para manter o cookie da sessão durante o retorno. Os links são gerados usando a URL configurada; o TVMax não publica o servidor nem cria um domínio/túnel automaticamente.

## Webhook (Target URL)

Copie a URL completa exibida no painel, incluindo `?key=...`, para o Target URL. Ela aponta para `smartthings-webhook.php`. Preserve a query string e os cabeçalhos `Authorization`, `Digest` e `Date` no proxy/servidor.

Solicite a confirmação no SmartThings, clique em **Atualizar conexão / webhook** no TVMax e abra **Confirmar webhook no SmartThings**. A confirmação somente aceita o domínio oficial e o App ID configurado. O link de confirmação pode expirar; solicite outro caso necessário.

O receptor aceita a confirmação e PING de registro pelo endereço secreto e valida assinaturas RSA-SHA256/ST_PADLOCK nos demais eventos. Eventos de desinstalação da conta vinculada removem seus tokens locais. Não há criação automática de subscriptions: o status das TVs continua por consulta a cada 30 segundos, enquanto a aba está visível. Apps configurados com assinatura legada APP_RSA devem usar ST_PADLOCK para este receptor.

## Tokens e manutenção

- Access token e refresh token são armazenados no SQLite privado em `data/`, junto das configurações; não entram no estado público do visualizador nem nos logs de auditoria.
- A renovação ocorre quando uma consulta/comando precisa de um token perto da expiração, ou após uma resposta 401 (uma tentativa). Um bloqueio impede que consultas simultâneas reutilizem o mesmo refresh token.
- Não há renovação em segundo plano sem tráfego. Se o refresh token expirar por inatividade ou a permissão for revogada, use **Conectar conta Samsung** novamente.
- **Desconectar do TVMax** apaga os tokens locais. Para revogar a concessão na Samsung, remova a integração da conta Samsung.
- Uma conta OAuth compartilhada é vinculada por instalação. Tokens exclusivos por TV continuam tendo prioridade; remova-os para usar OAuth. O token padrão manual só é usado quando OAuth não está configurado.
- PHP precisa de cURL, OpenSSL e PDO SQLite, certificados CA válidos e acesso HTTPS aos domínios oficiais. Mantenha o acesso HTTP a `data/` bloqueado.

## Verificação local

Execute `php tests/smartthings.php` e `php tests/smartthings_oauth.php`. Os testes de OAuth usam SQLite em memória, respostas simuladas para renovação e assinaturas RSA locais; não conectam a uma conta Samsung nem enviam comandos a TVs.
