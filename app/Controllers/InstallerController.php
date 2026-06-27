<?php
namespace App\Controllers;

use App\BaseController;
use App\Helpers\Auth;
use App\Helpers\Audit;

class InstallerController extends BaseController
{
    public function builder(): string
    {
        Auth::requireAuth();

        $isccPath = $this->findIscc();
        $settings = $this->loadSettings();

        return $this->render('installer.builder', [
            'iscc_available' => $isccPath !== null,
            'iscc_path' => $isccPath ?: 'Not found',
            'settings' => $settings,
        ]);
    }

    public function build(): void
    {
        Auth::requireAuth();
        $iscc = $this->findIscc();
        if (!$iscc) {
            session_flash('error', 'ISCC.exe (Inno Setup) not found on this server.');
            redirect('/installer/builder');
        }

        $db = \App\Helpers\Database::connect();

        // Gather form settings
        $settings = [
            'app_name' => $_POST['app_name'] ?? 'PlexiQ LIMS Server',
            'app_version' => $_POST['app_version'] ?? '2.0',
            'app_publisher' => $_POST['app_publisher'] ?? 'PlexiQ Labs',
            'server_port' => $_POST['server_port'] ?? '8080',
            'db_host' => $_POST['db_host'] ?? '127.0.0.1',
            'db_port' => $_POST['db_port'] ?? '5432',
            'db_name' => $_POST['db_name'] ?? 'limsdb',
            'db_user' => $_POST['db_user'] ?? 'postgres',
            'db_pass' => $_POST['db_pass'] ?? '',
            'output_filename' => $_POST['output_filename'] ?? 'PlexiQ-LIMS-Server-Setup',
        ];

        // Generate unique build ID
        $buildId = 'build_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));
        $buildDir = __DIR__ . '/../../storage/installer/' . $buildId;
        $outputDir = $buildDir . '/Output';

        @mkdir($buildDir . '/src', 0777, true);
        @mkdir($buildDir . '/assets', 0777, true);
        @mkdir($outputDir, 0777, true);

        // Copy app files
        $this->copyAppFiles($buildDir);

        // Copy assets
        $assetSrc = __DIR__ . '/../../server-installer/assets';
        if (is_dir($assetSrc)) {
            $this->recurseCopy($assetSrc, $buildDir . '/assets');
        } else {
            // Generate placeholder assets
            $this->generatePlaceholderAssets($buildDir);
        }

        // Copy management scripts
        $scriptSrc = __DIR__ . '/../../server-installer/src';
        if (is_dir($scriptSrc)) {
            $this->recurseCopy($scriptSrc, $buildDir . '/src');
        }

        // Generate setup.iss with user settings
        $issContent = $this->generateIss($settings, $buildDir);
        file_put_contents($buildDir . '/setup.iss', $issContent);

        // Generate .env file
        $envContent = $this->generateEnv($settings);
        file_put_contents($buildDir . '/.env', $envContent);

        // Generate config.ini
        $configContent = "; PlexiQ LIMS Server Configuration\r\nPORT={$settings['server_port']}\r\nPHP_PATH=php\r\n";
        file_put_contents($buildDir . '/config.ini', $configContent);

        // Log build start
        $logFile = $buildDir . '/build.log';
        $startTime = microtime(true);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Starting Inno Setup compilation...\n", FILE_APPEND);

        // Run ISCC
        $issPath = $buildDir . '/setup.iss';
        $cmd = "\"{$iscc}\" \"/O{$outputDir}\" \"{$issPath}\" 2>&1";
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);

        $elapsed = round(microtime(true) - $startTime, 2);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Compilation " . ($returnCode === 0 ? "SUCCESS" : "FAILED") . " (exit: {$returnCode}, time: {$elapsed}s)\n", FILE_APPEND);
        foreach ($output as $line) {
            file_put_contents($logFile, $line . "\n", FILE_APPEND);
        }

        // Find the generated EXE
        $exeFiles = glob($outputDir . '/*.exe');
        $exePath = $exeFiles[0] ?? null;

        // Save build record to DB
        $stmt = $db->prepare("
            INSERT INTO installer_builds (build_id, app_name, app_version, server_port, db_host, db_name, exit_code, exe_size, build_time, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $exeSize = $exePath ? filesize($exePath) : 0;
        $stmt->execute([
            $buildId,
            $settings['app_name'],
            $settings['app_version'],
            $settings['server_port'],
            $settings['db_host'],
            $settings['db_name'],
            $returnCode,
            $exeSize,
            $elapsed,
            Auth::id(),
        ]);

        Audit::log('Installer Built', 'installer_builds', null, null, [
            'build_id' => $buildId,
            'exit_code' => $returnCode,
            'exe_size' => $exeSize,
        ]);

        if ($returnCode === 0 && $exePath) {
            $_SESSION['_installer_download'] = $exePath;
            $_SESSION['_installer_build_id'] = $buildId;
            session_flash('success', "Installer built successfully ({$elapsed}s, " . round($exeSize / 1024) . " KB). <a href='/installer/download' class='alert-link'>Download Now</a>");
        } else {
            $logContent = file_get_contents($logFile);
            session_flash('error', "Installer build failed (exit code: {$returnCode}). Check server log.");
            $_SESSION['_installer_log'] = $logContent;
            $_SESSION['_installer_build_id'] = $buildId;
        }

        redirect('/installer/builder');
    }

    public function download(): void
    {
        Auth::requireAuth();
        $exePath = $_SESSION['_installer_download'] ?? null;
        if (!$exePath || !file_exists($exePath)) {
            session_flash('error', 'No installer available for download.');
            redirect('/installer/builder');
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($exePath) . '"');
        header('Content-Length: ' . filesize($exePath));
        header('Cache-Control: no-cache');
        readfile($exePath);
        exit;
    }

    public function log(string $buildId): string
    {
        Auth::requireAuth();
        $logFile = __DIR__ . '/../../storage/installer/' . $buildId . '/build.log';
        if (!file_exists($logFile)) {
            return $this->json(['error' => 'Log not found']);
        }
        return $this->json(['log' => file_get_contents($logFile)]);
    }

    public function history(): string
    {
        Auth::requireAuth();
        $db = \App\Helpers\Database::connect();
        $stmt = $db->query("
            SELECT ib.*, u.full_name AS built_by_name
            FROM installer_builds ib
            LEFT JOIN users u ON ib.created_by = u.id
            ORDER BY ib.created_at DESC LIMIT 50
        ");
        $builds = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        return $this->json($builds);
    }

    // --- Private helpers ---

    private function findIscc(): ?string
    {
        $paths = [
            'C:\Program Files (x86)\Inno Setup 6\ISCC.exe',
            'C:\Program Files\Inno Setup 6\ISCC.exe',
            'C:\Program Files (x86)\Inno Setup 5\ISCC.exe',
            'C:\Program Files\Inno Setup 5\ISCC.exe',
            // User-local install (non-admin)
            getenv('LOCALAPPDATA') . '\Programs\Inno Setup 6\ISCC.exe',
        ];
        foreach ($paths as $p) {
            if (file_exists($p)) return $p;
        }
        // Check PATH
        $which = trim(shell_exec('where ISCC.exe 2>nul') ?? '');
        if ($which) return $which;
        return null;
    }

    private function loadSettings(): array
    {
        $db = \App\Helpers\Database::connect();
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $_ENV['DB_PORT'] ?? '5432';
        $name = $_ENV['DB_DATABASE'] ?? 'limsdb';
        $user = $_ENV['DB_USERNAME'] ?? 'postgres';
        $pass = $_ENV['DB_PASSWORD'] ?? '';

        // Read current SERVER_PORT from .env or config.ini
        $srvPort = '8080';
        $configIni = __DIR__ . '/../../config.ini';
        if (file_exists($configIni)) {
            $lines = file($configIni, FILE_IGNORE_NEW_LINES);
            foreach ($lines as $line) {
                if (str_starts_with($line, 'PORT=')) {
                    $srvPort = trim(substr($line, 5));
                }
            }
        } elseif (isset($_ENV['SERVER_PORT'])) {
            $srvPort = $_ENV['SERVER_PORT'];
        }

        return [
            'app_name' => 'PlexiQ LIMS Server',
            'app_version' => '2.0',
            'app_publisher' => 'PlexiQ Labs',
            'server_port' => $srvPort,
            'db_host' => $host,
            'db_port' => $port,
            'db_name' => $name,
            'db_user' => $user,
            'db_pass' => $pass,
            'output_filename' => 'PlexiQ-LIMS-Server-Setup',
        ];
    }

    private function generateIss(array $settings, string $buildDir): string
    {
        $name = $settings['app_name'];
        $ver = $settings['app_version'];
        $pub = $settings['app_publisher'];
        $port = $settings['server_port'];
        $dbHost = $settings['db_host'];
        $dbPort = $settings['db_port'];
        $dbName = $settings['db_name'];
        $dbUser = $settings['db_user'];
        $dbPass = $settings['db_pass'];
        $outFile = $settings['output_filename'];

        return <<<ISS
; PlexiQ LIMS Server - Inno Setup Script
; Generated by PlexiQ LIMS Installer Builder

#define MyAppName "{$name}"
#define MyAppVersion "{$ver}"
#define MyAppPublisher "{$pub}"
#define MyAppURL "http://localhost:{$port}"
#define MyAppExeName "start-server.bat"

[Setup]
AppId={{A1B2C3D4-E5F6-7890-ABCD-EF1234567890}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
DefaultDirName={autopf}\\{$name}
DisableProgramGroupPage=yes
DefaultGroupName=PlexiQ LIMS
AllowNoIcons=yes
OutputDir=Output
OutputBaseFilename={$outFile}-{#MyAppVersion}
SetupIconFile=assets\\icon.ico
WizardImageFile=assets\\logo.bmp
WizardSmallImageFile=assets\\logo.bmp
UninstallDisplayIcon={app}\\assets\\icon.ico
Compression=lzma2/ultra64
SolidCompression=yes
MinVersion=10.0.10240
PrivilegesRequired=admin
DisableWelcomePage=no
WizardStyle=modern

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[Tasks]
Name: "desktopicon"; Description: "Create desktop shortcut"; GroupDescription: "Additional icons:"
Name: "startmenuicon"; Description: "Create Start menu shortcut"; GroupDescription: "Additional icons:"

[Files]
Source: "public\\*"; DestDir: "{app}\\public"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "app\\*"; DestDir: "{app}\\app"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "config\\*"; DestDir: "{app}\\config"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "database\\*"; DestDir: "{app}\\database"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "resources\\*"; DestDir: "{app}\\resources"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "routes\\*"; DestDir: "{app}\\routes"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "storage\\*"; DestDir: "{app}\\storage"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "vendor\\*"; DestDir: "{app}\\vendor"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: "docs\\*"; DestDir: "{app}\\docs"; Flags: ignoreversion recursesubdirs createallsubdirs
Source: ".env"; DestDir: "{app}"; Flags: ignoreversion onlyifdoesntexist
Source: "src\\*"; DestDir: "{app}"; Flags: ignoreversion
Source: "assets\\*"; DestDir: "{app}\\assets"; Flags: ignoreversion

[Icons]
Name: "{group}\\PlexiQ LIMS Server (Start)"; Filename: "{app}\\start-server.bat"; WorkingDir: "{app}"; IconFilename: "{app}\\assets\\icon.ico"; Tasks: startmenuicon
Name: "{group}\\PlexiQ LIMS Server (Stop)"; Filename: "{app}\\stop-server.bat"; WorkingDir: "{app}"; IconFilename: "{app}\\assets\\icon.ico"; Tasks: startmenuicon
Name: "{group}\\PlexiQ LIMS Dashboard"; Filename: "http://localhost:{$port}"; IconFilename: "{app}\\assets\\icon.ico"; Tasks: startmenuicon
Name: "{group}\\Validate Installation"; Filename: "{app}\\validate-install.bat"; WorkingDir: "{app}"; IconFilename: "{app}\\assets\\icon.ico"; Tasks: startmenuicon
Name: "{group}\\Uninstall PlexiQ LIMS"; Filename: "{uninstallexe}"
Name: "{autodesktop}\\PlexiQ LIMS Server"; Filename: "{app}\\start-server.bat"; WorkingDir: "{app}"; IconFilename: "{app}\\assets\\icon.ico"; Tasks: desktopicon

[Run]
Filename: "{app}\\setup-database.bat"; Description: "Set up database"; Flags: postinstall nowait skipifsilent runhidden
Filename: "http://localhost:{$port}"; Description: "Open PlexiQ LIMS Dashboard"; Flags: postinstall nowait skipifsilent shellexec

[UninstallRun]
Filename: "taskkill"; Parameters: "/F /IM php.exe /FI ""WINDOWTITLE eq PlexiQ*"""; Flags: runhidden skipifdoesntexist
ISS;
    }

    private function generateEnv(array $settings): string
    {
        return <<<ENV
# PlexiQ LIMS - Environment Configuration
DB_HOST={$settings['db_host']}
DB_PORT={$settings['db_port']}
DB_DATABASE={$settings['db_name']}
DB_USERNAME={$settings['db_user']}
DB_PASSWORD={$settings['db_pass']}
SERVER_PORT={$settings['server_port']}
APP_URL=http://localhost:{$settings['server_port']}
APP_ENV=production
APP_DEBUG=false
SESSION_DRIVER=file
ENV;
    }

    private function copyAppFiles(string $dest): void
    {
        $root = realpath(__DIR__ . '/../..');
        $dirs = ['public', 'app', 'config', 'database', 'resources', 'routes', 'storage', 'vendor', 'docs'];
        foreach ($dirs as $dir) {
            $src = $root . '/' . $dir;
            $dst = $dest . '/' . $dir;
            if ($dir === 'storage') {
                @mkdir($dst, 0777, true);
                @mkdir($dst . '/coa', 0777, true);
                @mkdir($dst . '/logs', 0777, true);
                @mkdir($dst . '/sessions', 0777, true);
                @mkdir($dst . '/installer', 0777, true);
            } elseif (is_dir($src)) {
                $this->recurseCopy($src, $dst);
            } else {
                @mkdir($dst, 0777, true);
            }
        }
        // Copy root files
        foreach (['composer.json', 'composer.lock'] as $f) {
            if (file_exists($root . '/' . $f)) {
                copy($root . '/' . $f, $dest . '/' . $f);
            }
        }
    }

    private function recurseCopy(string $src, string $dst): void
    {
        @mkdir($dst, 0777, true);
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($src, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($items as $item) {
            $target = $dst . '/' . $items->getSubPathname();
            if ($item->isDir()) {
                @mkdir($target, 0777, true);
            } else {
                copy($item, $target);
            }
        }
    }

    private function generatePlaceholderAssets(string $buildDir): void
    {
        // Generate a simple 48x48 BMP
        $bmpPath = $buildDir . '/assets/logo.bmp';
        @mkdir(dirname($bmpPath), 0777, true);

        $width = 48;
        $height = 48;
        $pixels = $width * $height;
        $fileSize = 54 + $pixels * 3;
        $bmp = pack('s', 0x4D42)                          // BM signature
             . pack('V', $fileSize)                        // file size
             . pack('V', 0)                                // reserved
             . pack('V', 54)                               // offset to pixel data
             . pack('V', 40)                               // header size
             . pack('V', $width)                           // width
             . pack('V', $height)                          // height
             . pack('v', 1)                                // planes
             . pack('v', 24)                               // bits per pixel
             . pack('V', 0)                                // compression
             . pack('V', $pixels * 3)                      // image size
             . pack('V', 2835)                             // x pixels per meter
             . pack('V', 2835)                             // y pixels per meter
             . pack('V', 0)                                // colors used
             . pack('V', 0);                               // important colors

        $r = 13; $g = 110; $b = 253; // #0D6EFD
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $bmp .= pack('C', $b) . pack('C', $g) . pack('C', $r); // BGR order
            }
        }
        file_put_contents($bmpPath, $bmp);

        // Copy favicon as icon.ico
        $favicon = __DIR__ . '/../../public/favicon.ico';
        $icoDest = $buildDir . '/assets/icon.ico';
        if (file_exists($favicon)) {
            copy($favicon, $icoDest);
        }
    }
}
