@echo off
setlocal

set JUMP_HOST=52.221.229.4
set JUMP_USER=ubuntu
set JUMP_KEY=%~dp0public\BeMCU_jumpserver.pem

set DB_HOST=10.11.48.135
set DB_REMOTE_PORT=3306
set LOCAL_PORT=3316

echo ============================================================
echo  Tunnel: localhost:%LOCAL_PORT% -^> %DB_HOST%:%DB_REMOTE_PORT% (database bewell)
echo ============================================================
echo  Biarkan window ini terbuka selama pakai koneksi bewell_db di Laravel.
echo  Tutup window ini / Ctrl+C untuk memutus tunnel.
echo ============================================================
echo.

ssh -N -L %LOCAL_PORT%:%DB_HOST%:%DB_REMOTE_PORT% -i "%JUMP_KEY%" %JUMP_USER%@%JUMP_HOST%

echo.
echo Tunnel terputus.
pause
endlocal
