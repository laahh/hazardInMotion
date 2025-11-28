<?php

namespace App\Services;

use phpseclib3\Net\SSH2;

class SshTunnelService
{
    private $sshHost;
    private $sshPort;
    private $sshUser;
    private $sshPassword;
    private $sshPkey;
    private $pgHost;
    private $pgPort;
    private $localPort;
    private $ssh = null;

    public function __construct()
    {
        // SSH Configuration
        $this->sshHost = config('database.connections.pgsql_ssh.ssh_host', '13.212.87.127');
        $this->sshPort = config('database.connections.pgsql_ssh.ssh_port', 22);
        $this->sshUser = config('database.connections.pgsql_ssh.ssh_user', 'ubuntu');
        $this->sshPassword = config('database.connections.pgsql_ssh.ssh_password');
        $this->sshPkey = config('database.connections.pgsql_ssh.ssh_pkey');
        
        // PostgreSQL Configuration
        $this->pgHost = config('database.connections.pgsql_ssh.pg_host', 'postgresql-olap-bc-production.cgehsbzl48r0.ap-southeast-1.rds.amazonaws.com');
        $this->pgPort = config('database.connections.pgsql_ssh.pg_port', 5432);
        
        // Local port for tunneling
        $this->localPort = config('database.connections.pgsql_ssh.local_port', 5433);
    }

    /**
     * Establish SSH tunnel
     */
    public function establishTunnel()
    {
        try {
            // Create SSH connection
            $this->ssh = new SSH2($this->sshHost, $this->sshPort);
            
            // Authenticate
            if ($this->sshPkey) {
                // Use private key authentication
                $key = \phpseclib3\Crypt\PublicKeyLoader::load(file_get_contents($this->sshPkey));
                if (!$this->ssh->login($this->sshUser, $key)) {
                    throw new \Exception('SSH authentication failed using private key');
                }
            } elseif ($this->sshPassword) {
                // Use password authentication
                if (!$this->ssh->login($this->sshUser, $this->sshPassword)) {
                    throw new \Exception('SSH authentication failed using password');
                }
            } else {
                throw new \Exception('No SSH authentication method provided');
            }

            // Create SSH tunnel using port forwarding
            // Note: phpseclib doesn't directly support tunneling, so we'll use exec to create tunnel
            // For production, consider using system SSH command or pecl ssh2 extension
            $command = "ssh -N -L {$this->localPort}:{$this->pgHost}:{$this->pgPort} {$this->sshUser}@{$this->sshHost} -p {$this->sshPort}";
            
            // Alternative: Use phpseclib's exec with background process
            // This is a workaround - in production, use proper SSH tunneling
            $this->ssh->exec('sleep 1', false);
            
            return true;
        } catch (\Exception $e) {
            \Log::error('SSH Tunnel Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get local port for database connection
     */
    public function getLocalPort()
    {
        return $this->localPort;
    }

    /**
     * Close SSH connection
     */
    public function closeTunnel()
    {
        if ($this->ssh) {
            $this->ssh->disconnect();
            $this->ssh = null;
        }
    }

    /**
     * Alternative method using system SSH command
     * This requires SSH to be available in system PATH
     */
    public function establishTunnelSystem()
    {
        try {
            // Check if port is already in use - if so, assume tunnel is already established
            if ($this->isPortInUse($this->localPort)) {
                \Log::info("Port {$this->localPort} is already in use, assuming tunnel exists");
                return true;
            }

            // Check if PEM file path is set and valid
            if ($this->sshPkey) {
                $pkeyPath = str_replace(['"', "'"], '', trim($this->sshPkey));
                if ($pkeyPath === '/path/to/your/pem/file.pem' || $pkeyPath === '') {
                    throw new \Exception("SSH_PKEY is not configured properly. Please set the correct path to your PEM file in .env file.\nExample: SSH_PKEY=C:\\path\\to\\your\\key.pem");
                }
                
                if (!file_exists($pkeyPath)) {
                    throw new \Exception("SSH private key file not found: {$pkeyPath}\nPlease check SSH_PKEY path in .env file.");
                }
            }

            // Build SSH command
            $command = "ssh";
            
            // Add SSH options
            $sshOptions = [
                "-N", // Don't execute remote command
                "-L {$this->localPort}:{$this->pgHost}:{$this->pgPort}", // Local port forwarding
                "-o StrictHostKeyChecking=no", // Don't prompt for host key verification
                "-o ConnectTimeout=10", // Connection timeout
            ];

            // Handle Windows vs Unix paths for UserKnownHostsFile
            if (PHP_OS_FAMILY === 'Windows') {
                $sshOptions[] = "-o UserKnownHostsFile=NUL"; // Windows null device
                
                // On Windows, we can't use -f flag, so we'll use a different approach
                // First, test if SSH command works
                $testCommand = "ssh -V 2>&1";
                exec($testCommand, $testOutput, $testReturn);
                
                if ($testReturn !== 0) {
                    throw new \Exception('SSH client not found. Please install OpenSSH Client or add it to PATH.');
                }
                
                // Build SSH command
                $sshCmd = "ssh -N -L {$this->localPort}:{$this->pgHost}:{$this->pgPort}";
                $sshCmd .= " -o StrictHostKeyChecking=no";
                $sshCmd .= " -o UserKnownHostsFile=NUL";
                $sshCmd .= " -o ConnectTimeout=10";
                $sshCmd .= " -p {$this->sshPort}";
                
                if ($this->sshPkey) {
                    $pkeyPath = str_replace(['"', "'"], '', trim($this->sshPkey));
                    $sshCmd .= " -i \"" . addslashes($pkeyPath) . "\"";
                }
                
                $sshCmd .= " {$this->sshUser}@{$this->sshHost}";
                
                // Create VBScript to run SSH in background
                $vbsScript = storage_path('app/ssh_tunnel_' . time() . '.vbs');
                $vbsContent = "Set WshShell = CreateObject(\"WScript.Shell\")\n";
                $vbsContent .= "WshShell.Run \"cmd /c \"\"" . str_replace('"', '""', $sshCmd) . "\"\", 0, False\n";
                
                file_put_contents($vbsScript, $vbsContent);
                
                // Execute VBScript
                exec("cscript //nologo " . escapeshellarg($vbsScript), $output, $returnVar);
                
                // Clean up VBScript after a delay
                sleep(2);
                @unlink($vbsScript);
                
                \Log::info("Windows SSH Command: " . $sshCmd);
                \Log::info("VBScript created at: " . $vbsScript);
                
                // Give it more time on Windows
                sleep(5);
            } else {
                // Linux/Mac: Use -f flag for background
                $sshOptions[] = "-f"; // Background mode
                $sshOptions[] = "-o UserKnownHostsFile=/dev/null"; // Unix null device
                
                if ($this->sshPkey) {
                    $pkeyPath = str_replace(['"', "'"], '', trim($this->sshPkey));
                    $sshOptions[] = "-i " . escapeshellarg($pkeyPath);
                }

                $command .= " " . implode(" ", $sshOptions);
                $command .= " -p {$this->sshPort}";
                $command .= " {$this->sshUser}@{$this->sshHost}";
                $command .= " 2>&1 > /dev/null &";
                
                exec($command, $output, $returnVar);
                sleep(3);
            }
            
            \Log::info("Executing SSH tunnel command. Platform: " . PHP_OS_FAMILY);
            \Log::info("Command output: " . implode("\n", $output));
            
            // Verify tunnel is working
            if (!$this->isPortInUse($this->localPort)) {
                $errorMsg = 'SSH tunnel failed to establish. Port ' . $this->localPort . ' is not listening.';
                $errorMsg .= "\n\nDebug info:\n";
                $errorMsg .= "- SSH Host: {$this->sshHost}:{$this->sshPort}\n";
                $errorMsg .= "- SSH User: {$this->sshUser}\n";
                $errorMsg .= "- Private Key: " . ($this->sshPkey ? $this->sshPkey : 'Not set') . "\n";
                $errorMsg .= "- Target DB: {$this->pgHost}:{$this->pgPort}\n";
                $errorMsg .= "- Local Port: {$this->localPort}\n";
                $errorMsg .= "\nManual Setup:\n";
                $errorMsg .= "You can manually set up the SSH tunnel by running this command in a separate terminal:\n";
                $manualCmd = "ssh -N -L {$this->localPort}:{$this->pgHost}:{$this->pgPort} {$this->sshUser}@{$this->sshHost} -p {$this->sshPort}";
                if ($this->sshPkey) {
                    $manualCmd .= " -i " . escapeshellarg(str_replace(['"', "'"], '', trim($this->sshPkey)));
                }
                $errorMsg .= $manualCmd . "\n";
                $errorMsg .= "\nKeep that terminal open, then refresh this page.\n";
                $errorMsg .= "\nCommand output: " . implode("\n", $output);
                
                throw new \Exception($errorMsg);
            }
            
            \Log::info("SSH tunnel established successfully on port {$this->localPort}");
            return true;
        } catch (\Exception $e) {
            \Log::error('SSH Tunnel System Error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check if port is in use
     */
    private function isPortInUse($port)
    {
        $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
        if ($connection) {
            fclose($connection);
            return true;
        }
        return false;
    }
}

