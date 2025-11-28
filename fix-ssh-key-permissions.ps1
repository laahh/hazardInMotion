# Fix SSH Key Permissions (PowerShell)

# Fix permissions for SSH private key file
$keyFile = "C:\laragon\www\Admin\public\JumpHostVPC2.pem"

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Fixing SSH Key Permissions" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "Current permissions:" -ForegroundColor Yellow
icacls $keyFile
Write-Host ""

Write-Host "Removing inheritance and setting restrictive permissions..." -ForegroundColor Yellow
icacls $keyFile /inheritance:r
icacls $keyFile /grant:r "$env:USERNAME`:R"

Write-Host ""
Write-Host "New permissions:" -ForegroundColor Green
icacls $keyFile
Write-Host ""

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "Permission fix complete!" -ForegroundColor Green
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Now you can run the SSH tunnel command." -ForegroundColor Yellow

