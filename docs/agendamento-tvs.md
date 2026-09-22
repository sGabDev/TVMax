# Horários automáticos das TVs

Na aba **Ligar / desligar TVs**, escolha **Programar horários** na TV desejada. Marque os dias, defina ligar e/ou desligar e salve. Administradores podem configurar; os demais usuários podem consultar os horários e resultados.

Os horários usam **America/Sao_Paulo (Brasília)**, independentemente do fuso do navegador. Cada horário ocorre no próprio dia marcado. Para ligar segunda às 22h e desligar terça às 02h, inclua ambos os dias; com ambos marcados, o padrão se repetirá nos dois dias. É possível deixar um dos horários vazio. Desmarcar **Ativar horários automáticos** preserva a configuração sem enviar comandos.

## Execução com o site fechado

O processo `scripts/smartthings_scheduler.php` deve rodar uma vez por minuto no servidor. Ele não depende de visitas, sessão de navegador ou Apache. Usa as mesmas credenciais das TVs, incluindo renovação OAuth automática. O servidor e a conexão de rede precisam continuar ligados. A TV precisa aceitar ligar remotamente pelo SmartThings.

O aviso na aba informa se o processo foi visto nos últimos três minutos. Salvar horários não instala uma tarefa no sistema operacional. Sem o processo ativo, o painel mostra um aviso em vez de prometer que a automação funcionará.

### Windows / XAMPP

Abra PowerShell como administrador, na pasta do projeto, e execute:

```powershell
.\scripts\install_smartthings_task.ps1 -PhpPath 'C:\xampp\php\php.exe'
```

A tarefa `TVMax-SmartThings-<pasta>` executa a cada minuto como SYSTEM, sem exigir usuário conectado. Não coloca credenciais SmartThings na linha de comando. O PHP deve ter cURL, OpenSSL e PDO SQLite habilitados. Para conferir, use o Agendador de Tarefas do Windows e o indicador no painel. Desative a tarefa pelo Agendador de Tarefas se não quiser mais a execução automática. O instalador não altera a suspensão/energia do Windows.

### Linux / hospedagem

Cadastre no cron da conta que tem acesso à pasta `data/` (ajuste os caminhos):

```cron
* * * * * /usr/bin/php /caminho/tvmax/scripts/smartthings_scheduler.php >> /caminho/privado/tvmax-scheduler.log 2>&1
```

O log deve ficar fora de uma pasta pública. Esse modo exige PHP CLI. Para cron via `wget`, use a alternativa abaixo.

### Hostinger com wget (HTTP)

Não use a URL `/scripts/smartthings_scheduler.php`: a pasta é privada e esse arquivo aceita apenas PHP CLI. O retorno 403 é esperado.

Depois de publicar esta atualização, entre como administrador no site **da Hostinger** e abra **Ligar / desligar TVs → Cadastrar TVs e configurar conexão → Ativar agendamento na Hostinger (cron)**. Copie o comando gerado ali e substitua o cron antigo. Mantenha a frequência em todos os minutos, horas, dias e meses.

O formato será:

```sh
wget -q -O /dev/null "https://tvmax.poseitech.com.br/smartthings-cron.php?key=SUA_CHAVE_GERADA_NO_SERVIDOR"
```

Não use o texto `SUA_CHAVE_GERADA_NO_SERVIDOR` literalmente. A chave é criada e guardada no banco da instalação, exibida apenas para administradores. Copie o comando do site de produção, não do ambiente local. Como alternativa, o endpoint aceita a chave no cabeçalho `X-TVMax-Cron-Key`. Sem a chave correta, retorna 403. Nunca libere o acesso à pasta `scripts/` para resolver esse erro.

Aguarde até dois minutos e verifique o indicador de agendamento ativo. O cron HTTP só executa os horários vencidos dentro da janela normal; não é um comando para ligar todas as TVs. Se a hospedagem bloquear requisições HTTP por firewall, use o cron PHP CLI acima. Evite configurar as duas formas simultaneamente, embora o bloqueio impeça execução concorrente.

## Falhas e atrasos

O executor aceita até cinco minutos de atraso, com no máximo três tentativas, separadas por pelo menos um minuto. Depois dessa janela, o comando é ignorado até a próxima ocorrência; não se reproduz uma sequência antiga de ligar/desligar quando o servidor volta. Se dois horários estiverem na janela, prevalece o mais recente. Editar uma programação só afeta ocorrências futuras. Comandos aceitos não são reenviados pela mesma ocorrência. Em caso de interrupção do processo após enviar, uma tentativa de recuperação pode repetir o mesmo comando on/off, que é idempotente.

O resultado “comando enviado” significa aceito pela API; confira o status da TV para saber se o aparelho realmente mudou de estado. Erros ficam visíveis na TV correspondente e na auditoria. Execuções são mantidas por 30 dias. Um bloqueio evita dois executores simultâneos.

Teste sem enviar comandos: `php scripts/smartthings_scheduler.php --check`.
Testes isolados com transporte simulado: `php tests/smartthings_schedule.php`.
