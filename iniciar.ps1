$ErrorActionPreference = 'Stop'
$projectRoot = $PSScriptRoot
$runtimePath = Join-Path $projectRoot '.runtime'
New-Item -ItemType Directory -Path $runtimePath -Force | Out-Null
$phpPath = (Get-Command php.exe).Source
$nodePath = (Get-Command node.exe).Source
if (-not (Test-Path (Join-Path $projectRoot 'backend/vendor/autoload.php'))) { throw 'Instala primero las dependencias con composer install en backend.' }
if (-not (Test-Path (Join-Path $projectRoot 'node_modules/vite/bin/vite.js'))) { throw 'Instala primero las dependencias con npm.cmd install.' }
foreach ($service in @(@{Port=8001;Name='backend';Executable=$phpPath;Arguments=@('-S','127.0.0.1:8001',('"' + (Join-Path $projectRoot 'backend/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php') + '"'));WorkingDirectory=(Join-Path $projectRoot 'backend/public')},@{Port=5173;Name='frontend';Executable=$nodePath;Arguments=@('node_modules/vite/bin/vite.js','--host','127.0.0.1','--port','5173','--strictPort');WorkingDirectory=$projectRoot})) {
    $listener = Get-NetTCPConnection -State Listen -LocalPort $service.Port -ErrorAction SilentlyContinue
    if ($listener) { Write-Output "El puerto $($service.Port) ya está ocupado. No se inició otra instancia."; continue }
    $process = Start-Process -FilePath $service.Executable -ArgumentList $service.Arguments -WorkingDirectory $service.WorkingDirectory -WindowStyle Hidden -PassThru -RedirectStandardOutput (Join-Path $runtimePath ($service.Name+'.log')) -RedirectStandardError (Join-Path $runtimePath ($service.Name+'-error.log'))
    Set-Content -LiteralPath (Join-Path $runtimePath ($service.Name+'.pid')) -Value $process.Id
}
Write-Output 'Portal: http://127.0.0.1:5173'
Write-Output 'Primer acceso: abre .runtime/acceso-inicial.txt'
Write-Output 'Registros de los servidores: .runtime'
