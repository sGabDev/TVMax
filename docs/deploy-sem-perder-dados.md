# Atualizar o código sem substituir usuários e uploads

## O que mudou

`data/` guarda o banco de contas, OAuth, configurações SmartThings, horários, auditoria, sessões e a programação. `uploads/` guarda as mídias e páginas convertidas. Esses dados pertencem a cada instalação.

Os arquivos de execução foram removidos **do índice do Git**, mantendo os arquivos no disco local. `.gitignore` ignora o conteúdo dessas pastas e `config/local.php`. Apenas os `.htaccess` de proteção/cache continuam versionados. Inclua `.gitignore` e as remoções do índice no mesmo commit da correção.

## Primeiro deploy desta correção: preservar os dados da Hostinger

**Faça esta etapa antes do push que dispara o deploy automático.** O primeiro deploy recebe exclusões de arquivos que antes eram rastreados; por isso, somente adicionar `.gitignore` não basta para proteger essa transição.

1. Pause o cron e coloque a aplicação em manutenção na hospedagem, impedindo novos uploads, logins e alterações durante a cópia. Não use os dados do computador local como backup de produção.
2. Pelo gerenciador de arquivos/backup da Hostinger, copie **as pastas completas `data/` e `uploads/` de produção**, além de `config/local.php` se existir, para um local fora da pasta implantada, preferencialmente fora de `public_html`. Baixe uma cópia. Inclua arquivos ocultos e os auxiliares SQLite `-wal`/`-shm`; não copie apenas `accounts.sqlite` enquanto houver gravações.
3. Confirme que o backup contém os arquivos e o banco do servidor. Só então publique o commit com esta correção.
4. Com a aplicação ainda em manutenção, restaure **o conteúdo de produção** em `data/` e `uploads/` e o `config/local.php` original. Preserve os `.htaccess` novos distribuídos com o código. Não misture o banco de produção com auxiliares SQLite criados por outra base: restaure o conjunto original, mantendo a aplicação parada durante essa operação.
5. Confira usuários, fila, mídias e configurações SmartThings. Gere/copie o cron no painel de produção, reative o cron e retire a manutenção.

Se os dados já foram substituídos em um deploy anterior, esta correção não recupera a versão antiga: restaure um backup anterior da Hostinger.

## Deploys seguintes

- Atualize somente o código e preserve `data/`, `uploads/` e `config/local.php` no destino. Os arquivos ignorados não entrarão em novos commits por `git add .`.
- Não use `git add -f` nessas pastas e não envie o banco local por FTP.
- Se o deploy remove/recria toda a pasta do site, usa limpeza de arquivos ignorados ou sincronização com exclusão, configure essas três entradas como persistentes/excluídas da limpeza. `.gitignore` controla o Git, não impede ferramentas de implantação de apagar arquivos.
- Mantenha backups de produção. O histórico antigo do Git ainda contém os dados versionados anteriormente; esta alteração não reescreve esse histórico.

Para conferir o índice local, `git ls-files data uploads` deve listar somente `data/.htaccess` e `uploads/.htaccess`.
