; PlexiQ LIMS Server - Inno Setup Script
; Compile with Inno Setup 6 (https://jrsoftware.org/isdl.php)
; This installer deploys a complete PlexiQ LIMS server on Windows

#define MyAppName "PlexiQ LIMS Server"
#define MyAppVersion "2.0"
#define MyAppPublisher "PlexiQ Labs"
#define MyAppURL "http://localhost:8080"
#define MyAppExeName "start-server.bat"

[Setup]
AppId={{A1B2C3D4-E5F6-7890-ABCD-EF1234567890}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
DefaultDirName={autopf}\PlexiQ-LIMS
DisableProgramGroupPage=yes
DisableDirPage=no
DefaultGroupName=PlexiQ LIMS
AllowNoIcons=yes
OutputDir=Output
OutputBaseFilename=PlexiQ-LIMS-Server-Setup-{#MyAppVersion}
SetupIconFile=assets\icon.ico
WizardImageFile=assets\logo.bmp
WizardSmallImageFile=assets\logo.bmp
UninstallDisplayIcon={app}\assets\icon.ico
Compression=lzma2/ultra64
SolidCompression=yes
MinVersion=10.0.10240
PrivilegesRequired=admin
ShowLanguageDialog=no
UninstallDisplayName={#MyAppName}
VersionInfoCompany={#MyAppPublisher}
VersionInfoDescription=PlexiQ LIMS Server - Laboratory Information Management System
VersionInfoProductName={#MyAppName}
DisableWelcomePage=no
WizardStyle=modern

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[CustomMessages]
LaunchServer=Start PlexiQ LIMS Server after installation
CreateDesktopIcon=Create desktop shortcut
CreateStartMenuIcon=Create Start menu shortcut
ServerPortLabel=Enter the port for the LIMS server:
ServerPortDescription=Port number for the PHP development server.%n%nDefault: 8080%nMake sure this port is not in use.
DBHostLabel=PostgreSQL Host:
DBPortLabel=PostgreSQL Port:
DBNameLabel=Database Name:
DBUserLabel=Database User:
DBPassLabel=Database Password:

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"
Name: "startmenuicon"; Description: "{cm:CreateStartMenuIcon}"; GroupDescription: "{cm:AdditionalIcons}"

[Types]
Name: "typical"; Description: "Typical installation (recommended)"
Name: "custom"; Description: "Custom installation"; Flags: iscustom

[Components]
Name: "app"; Description: "PlexiQ LIMS Application Files"; Types: typical custom; Flags: fixed
Name: "scripts"; Description: "Management Scripts (start/stop/validate)"; Types: typical custom; Flags: fixed
Name: "php"; Description: "Portable PHP Runtime (downloads PHP 8.0)"; Types: typical custom
Name: "service"; Description: "Windows Service (run LIMS as a background service)"; Types: custom

[Files]
; Application files - entire PlexiQ LIMS codebase
Source: "..\public\*"; DestDir: "{app}\public"; Flags: ignoreversion recursesubdirs createallsubdirs; Components: app
Source: "..\app\*"; DestDir: "{app}\app"; Flags: ignoreversion recursesubdirs createallsubdirs; Components: app
Source: "..\config\*"; DestDir: "{app}\config"; Flags: ignoreversion recursesubdirs createallsubdirs; Components: app
Source: "..\database\*"; DestDir: "{app}\database"; Flags: ignoreversion recursesubdirs createallsubdirs; Components: app
Source: "..\resources\*"; DestDir: "{app}\resources"; Flags: ignoreversion recursesubdirs createallsubdirs; Components: app
Source: "..\routes\*"; DestDir: "{app}\routes"; Flags: ignoreversion recursesubdirs createallsubdirs; Components: app
Source: "..\storage\labels\*"; DestDir: "{app}\storage\labels"; Flags: ignoreversion recursesubdirs createallsubdirs skipifsourcedoesntexist; Components: app
Source: "..\storage\exports\*"; DestDir: "{app}\storage\exports"; Flags: ignoreversion recursesubdirs createallsubdirs skipifsourcedoesntexist; Components: app
Source: "..\vendor\*"; DestDir: "{app}\vendor"; Flags: ignoreversion recursesubdirs createallsubdirs; Components: app
Source: "..\docs\*"; DestDir: "{app}\docs"; Flags: ignoreversion recursesubdirs createallsubdirs; Components: app
Source: "..\.env"; DestDir: "{app}"; Flags: ignoreversion onlyifdoesntexist; Components: app
Source: "..\composer.json"; DestDir: "{app}"; Flags: ignoreversion; Components: app
Source: "..\composer.lock"; DestDir: "{app}"; Flags: ignoreversion; Components: app

; Management scripts
Source: "src\start-server.bat"; DestDir: "{app}"; Flags: ignoreversion; Components: scripts
Source: "src\stop-server.bat"; DestDir: "{app}"; Flags: ignoreversion; Components: scripts
Source: "src\restart-server.bat"; DestDir: "{app}"; Flags: ignoreversion; Components: scripts
Source: "src\status-server.bat"; DestDir: "{app}"; Flags: ignoreversion; Components: scripts
Source: "src\setup-database.ps1"; DestDir: "{app}"; Flags: ignoreversion; Components: scripts
Source: "src\validate-install.ps1"; DestDir: "{app}"; Flags: ignoreversion; Components: scripts
Source: "src\setup-database.bat"; DestDir: "{app}"; Flags: ignoreversion; Components: scripts
Source: "src\config.ini"; DestDir: "{app}"; Flags: ignoreversion onlyifdoesntexist; Components: scripts
Source: "src\install-service.ps1"; DestDir: "{app}"; Flags: ignoreversion; Components: service
Source: "src\remove-service.ps1"; DestDir: "{app}"; Flags: ignoreversion; Components: service

; Assets
Source: "assets\icon.ico"; DestDir: "{app}\assets"; Flags: ignoreversion
Source: "assets\logo.bmp"; DestDir: "{app}\assets"; Flags: ignoreversion
Source: "assets\plexiq-icon.png"; DestDir: "{app}\assets"; Flags: ignoreversion

[Dirs]
Name: "{app}\storage\logs"; Components: app
Name: "{app}\storage\sessions"; Components: app
Name: "{app}\storage\coa"; Components: app

[Icons]
Name: "{group}\PlexiQ LIMS Server (Start)"; Filename: "{app}\start-server.bat"; WorkingDir: "{app}"; IconFilename: "{app}\assets\icon.ico"; Tasks: startmenuicon
Name: "{group}\PlexiQ LIMS Server (Stop)"; Filename: "{app}\stop-server.bat"; WorkingDir: "{app}"; IconFilename: "{app}\assets\icon.ico"; Tasks: startmenuicon
Name: "{group}\PlexiQ LIMS Dashboard"; Filename: "http://localhost:8080"; IconFilename: "{app}\assets\icon.ico"; Tasks: startmenuicon
Name: "{group}\Validate Installation"; Filename: "{app}\validate-install.bat"; WorkingDir: "{app}"; IconFilename: "{app}\assets\icon.ico"; Tasks: startmenuicon
Name: "{group}\Uninstall PlexiQ LIMS"; Filename: "{uninstallexe}"
Name: "{autodesktop}\PlexiQ LIMS Server"; Filename: "{app}\start-server.bat"; WorkingDir: "{app}"; IconFilename: "{app}\assets\icon.ico"; Tasks: desktopicon

[Run]
Filename: "{app}\setup-database.bat"; Description: "Set up database"; Flags: postinstall nowait skipifsilent runhidden
Filename: "{app}\start-server.bat"; Description: "{cm:LaunchServer}"; Flags: postinstall nowait skipifsilent
Filename: "http://localhost:8080"; Description: "Open PlexiQ LIMS Dashboard"; Flags: postinstall nowait skipifsilent shellexec

[UninstallRun]
Filename: "taskkill"; Parameters: "/F /IM php.exe /FI ""WINDOWTITLE eq PlexiQ*"""; Flags: runhidden skipifdoesntexist; RunOnceId: "KillPhp"

[UninstallDelete]
Type: filesandordirs; Name: "{app}\storage\logs"
Type: filesandordirs; Name: "{app}\storage\sessions"
Type: files; Name: "{app}\.env"

[Code]

var
  ConfigPage: TInputQueryWizardPage;
  DbPage: TInputQueryWizardPage;
  ServerPort: String;
  DbHost: String;
  DbPort: String;
  DbName: String;
  DbUser: String;
  DbPass: String;

procedure InitializeWizard;
begin
  { Server Port Configuration }
  ConfigPage := CreateInputQueryPage(
    wpSelectComponents,
    'Server Configuration',
    'Configure the LIMS server port',
    'Specify the port number for the PHP development server.'#13#10 +
    'Default is 8080. Make sure the port is not in use by another application.'
  );
  ConfigPage.Add('Server Port:', False);

  if RegQueryStringValue(HKLM, 'Software\PlexiQ LIMS Server', 'ServerPort', ServerPort) then
    ConfigPage.Values[0] := ServerPort
  else
    ConfigPage.Values[0] := '8080';

  { Database Configuration }
  DbPage := CreateInputQueryPage(
    ConfigPage.ID,
    'Database Configuration',
    'Enter your PostgreSQL database connection details',
    'PlexiQ LIMS requires PostgreSQL 14 or later.'#13#10 +
    'If PostgreSQL is not installed, the installer will guide you.'#13#10#13#10 +
    'Default local development connection:'#13#10 +
    '  Host: 127.0.0.1  Port: 5432  Database: limsdb'#13#10 +
    '  User: postgres    Password: (leave blank for default)'
  );

  DbPage.Add('Host:', False);
  DbPage.Add('Port:', False);
  DbPage.Add('Database:', False);
  DbPage.Add('User:', False);
  DbPage.Add('Password:', True);

  if RegQueryStringValue(HKLM, 'Software\PlexiQ LIMS Server', 'DbHost', DbHost) then
    DbPage.Values[0] := DbHost else DbPage.Values[0] := '127.0.0.1';
  if RegQueryStringValue(HKLM, 'Software\PlexiQ LIMS Server', 'DbPort', DbPort) then
    DbPage.Values[1] := DbPort else DbPage.Values[1] := '5432';
  if RegQueryStringValue(HKLM, 'Software\PlexiQ LIMS Server', 'DbName', DbName) then
    DbPage.Values[2] := DbName else DbPage.Values[2] := 'limsdb';
  if RegQueryStringValue(HKLM, 'Software\PlexiQ LIMS Server', 'DbUser', DbUser) then
    DbPage.Values[3] := DbUser else DbPage.Values[3] := 'postgres';
  if RegQueryStringValue(HKLM, 'Software\PlexiQ LIMS Server', 'DbPass', DbPass) then
    DbPage.Values[4] := DbPass else DbPage.Values[4] := '';
end;

function UpdateReadyMemo(Space, NewLine, MemoUserInfoInfo, MemoDirInfo, MemoTypeInfo, MemoComponentsInfo, MemoGroupInfo, MemoTasksInfo: String): String;
var
  S: String;
begin
  S := '';
  S := S + 'Server Configuration:' + NewLine;
  S := S + '  Port: ' + ConfigPage.Values[0] + NewLine;
  S := S + '  URL: http://localhost:' + ConfigPage.Values[0] + NewLine;
  S := S + NewLine;
  S := S + 'Database Configuration:' + NewLine;
  S := S + '  Host: ' + DbPage.Values[0] + NewLine;
  S := S + '  Port: ' + DbPage.Values[1] + NewLine;
  S := S + '  Database: ' + DbPage.Values[2] + NewLine;
  S := S + '  User: ' + DbPage.Values[3] + NewLine;
  S := S + NewLine;
  S := S + MemoDirInfo + NewLine;
  S := S + MemoComponentsInfo + NewLine;
  S := S + MemoTasksInfo;
  Result := S;
end;

function GetPsqlPath(): String;
begin
  Result := '';
  if FileExists('C:\Program Files\PostgreSQL\18\bin\psql.exe') then
    Result := 'C:\Program Files\PostgreSQL\18\bin\psql.exe'
  else if FileExists('C:\Program Files\PostgreSQL\17\bin\psql.exe') then
    Result := 'C:\Program Files\PostgreSQL\17\bin\psql.exe'
  else if FileExists('C:\Program Files\PostgreSQL\16\bin\psql.exe') then
    Result := 'C:\Program Files\PostgreSQL\16\bin\psql.exe'
  else if FileExists('C:\Program Files\PostgreSQL\15\bin\psql.exe') then
    Result := 'C:\Program Files\PostgreSQL\15\bin\psql.exe'
  else if FileExists('C:\Program Files\PostgreSQL\14\bin\psql.exe') then
    Result := 'C:\Program Files\PostgreSQL\14\bin\psql.exe';
end;

function GetPhpPath(): String;
begin
  Result := '';
  if FileExists(ExpandConstant('{app}\php\php.exe')) then
    Result := ExpandConstant('{app}\php\php.exe')
  else if FileExists('C:\xampp\php\php.exe') then
    Result := 'C:\xampp\php\php.exe'
  else if FileExists('C:\php\php.exe') then
    Result := 'C:\php\php.exe';
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  ConfigPath: String;
  Lines: TArrayOfString;
  ServerPortValue, DbHostValue, DbPortValue, DbNameValue, DbUserValue, DbPassValue: String;
  PhpFound, PsqlFound: Boolean;
begin
  if CurStep = ssPostInstall then
  begin
    ServerPortValue := ConfigPage.Values[0];
    DbHostValue := DbPage.Values[0];
    DbPortValue := DbPage.Values[1];
    DbNameValue := DbPage.Values[2];
    DbUserValue := DbPage.Values[3];
    DbPassValue := DbPage.Values[4];

    { Write .env configuration }
    ConfigPath := ExpandConstant('{app}\.env');
    SetArrayLength(Lines, 12);
    Lines[0] := '# PlexiQ LIMS - Environment Configuration';
    Lines[1] := '# Generated by Server Installer';
    Lines[2] := 'DB_HOST=' + DbHostValue;
    Lines[3] := 'DB_PORT=' + DbPortValue;
    Lines[4] := 'DB_DATABASE=' + DbNameValue;
    Lines[5] := 'DB_USERNAME=' + DbUserValue;
    Lines[6] := 'DB_PASSWORD=' + DbPassValue;
    Lines[7] := 'SERVER_PORT=' + ServerPortValue;
    Lines[8] := 'APP_URL=http://localhost:' + ServerPortValue;
    Lines[9] := 'APP_ENV=production';
    Lines[10] := 'APP_DEBUG=false';
    Lines[11] := 'SESSION_DRIVER=file';
    SaveStringsToFile(ConfigPath, Lines, False);

    { Write start-server config }
    ConfigPath := ExpandConstant('{app}\config.ini');
    SetArrayLength(Lines, 3);
    Lines[0] := '; PlexiQ LIMS Server Configuration';
    Lines[1] := 'PORT=' + ServerPortValue;
    Lines[2] := 'PHP_PATH=' + GetPhpPath();
    SaveStringsToFile(ConfigPath, Lines, False);

    { Save to registry }
    RegWriteStringValue(HKLM, 'Software\PlexiQ LIMS Server', 'InstallPath', ExpandConstant('{app}'));
    RegWriteStringValue(HKLM, 'Software\PlexiQ LIMS Server', 'ServerPort', ServerPortValue);
    RegWriteStringValue(HKLM, 'Software\PlexiQ LIMS Server', 'DbHost', DbHostValue);
    RegWriteStringValue(HKLM, 'Software\PlexiQ LIMS Server', 'DbPort', DbPortValue);
    RegWriteStringValue(HKLM, 'Software\PlexiQ LIMS Server', 'DbName', DbNameValue);
    RegWriteStringValue(HKLM, 'Software\PlexiQ LIMS Server', 'DbUser', DbUserValue);
    RegWriteStringValue(HKLM, 'Software\PlexiQ LIMS Server', 'DbPass', DbPassValue);

    { Check prerequisites and show warnings }
    PhpFound := (GetPhpPath() <> '');
    PsqlFound := (GetPsqlPath() <> '');

    if not PhpFound then
      MsgBox('Warning: PHP 8.0+ was not found.'#13#10#13#10 +
             'Please install PHP 8.0 or later and add it to your system PATH.'#13#10#13#10 +
             'Download: https://windows.php.net/download/', mbInformation, MB_OK);

    if not PsqlFound then
      MsgBox('Warning: PostgreSQL was not found.'#13#10#13#10 +
             'Please install PostgreSQL 14 or later.'#13#10#13#10 +
             'Download: https://www.postgresql.org/download/windows/', mbInformation, MB_OK);
  end;
end;

procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if CurUninstallStep = usPostUninstall then
  begin
    RegDeleteKeyIfEmpty(HKLM, 'Software\PlexiQ LIMS Server');
  end;
end;
