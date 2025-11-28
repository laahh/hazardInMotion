# SSH Tunnel Helper Script for PowerShell
# This script creates an SSH tunnel to PostgreSQL database

$SSH_HOST = "13.212.87.127"
$SSH_PORT = "22"
$SSH_USER = "ubuntu"
$SSH_KEY = "C:\laragon\www\Admin\public\JumpHostVPC2.pem"
$LOCAL_PORT = "5433"
$DB_HOST = "postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com"
$DB_PORT = "5432"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "SSH Tunnel Setup" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Creating SSH tunnel..." -ForegroundColor Yellow
Write-Host "Local port: $LOCAL_PORT" -ForegroundColor Green
Write-Host "Remote host: ${DB_HOST}:${DB_PORT}" -ForegroundColor Green
Write-Host "SSH server: ${SSH_USER}@${SSH_HOST}:${SSH_PORT}" -ForegroundColor Green
Write-Host ""
Write-Host "Keep this window open while using the database viewer." -ForegroundColor Yellow
Write-Host "Press Ctrl+C to stop the tunnel." -ForegroundColor Yellow
Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

ssh -N -L "${LOCAL_PORT}:${DB_HOST}:${DB_PORT}" "${SSH_USER}@${SSH_HOST}" -p $SSH_PORT -i "$SSH_KEY"

