param([string]$PhpPath='C:\xampp\php\php.exe')
$ErrorActionPreference='Stop'
$projectPath=(Resolve-Path -LiteralPath (Join-Path $PSScriptRoot '..')).Path
$phpExecutable=(Resolve-Path -LiteralPath $PhpPath).Path
$workerPath=Join-Path $projectPath 'scripts\smartthings_scheduler.php'
& $phpExecutable $workerPath --check
if($LASTEXITCODE -ne 0){throw 'A verificacao do agendador falhou.'}
$taskName='TVMax-SmartThings-'+(Split-Path $projectPath -Leaf)
$action=New-ScheduledTaskAction -Execute $phpExecutable -Argument ('"'+$workerPath+'"') -WorkingDirectory $projectPath
$trigger=New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 1)
$principal=New-ScheduledTaskPrincipal -UserId 'SYSTEM' -LogonType ServiceAccount
$settings=New-ScheduledTaskSettingsSet -StartWhenAvailable -MultipleInstances IgnoreNew -ExecutionTimeLimit (New-TimeSpan -Minutes 10) -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries
$existing=Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
if($existing -and $existing.Actions.Arguments -ne ('"'+$workerPath+'"')){throw 'Existe outra tarefa com o mesmo nome. Nenhuma alteracao realizada.'}
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description 'Executa os horarios das TVs a cada minuto, mesmo sem navegador ou usuario conectado.' -Force | Out-Null
Start-ScheduledTask -TaskName $taskName
Get-ScheduledTask -TaskName $taskName | Select-Object TaskName,State
