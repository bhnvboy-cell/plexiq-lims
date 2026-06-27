; PlexiQ LIMS Client - Inno Setup Script
; Compile with Inno Setup 6 (https://jrsoftware.org/isdl.php)

#define MyAppName "PlexiQ LIMS Client"
#define MyAppVersion "1.0"
#define MyAppPublisher "PlexiQ Labs"
#define MyAppURL "http://localhost:8080"
#define MyAppExeName "lims-client.hta"

[Setup]
AppId={{B8A3C8E4-9F2D-4E6A-8C1D-3F5E7A9B2C4D}
AppName={#MyAppName}
AppVersion={#MyAppVersion}
AppPublisher={#MyAppPublisher}
AppPublisherURL={#MyAppURL}
AppSupportURL={#MyAppURL}
AppUpdatesURL={#MyAppURL}
DefaultDirName={autopf}\{#MyAppName}
DisableProgramGroupPage=yes
DisableDirPage=no
DefaultGroupName=PlexiQ LIMS
AllowNoIcons=yes
OutputDir=Output
OutputBaseFilename=PlexiQ-LIMS-Client-Setup-{#MyAppVersion}
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

[Languages]
Name: "english"; MessagesFile: "compiler:Default.isl"

[CustomMessages]
ServerURLLabel=Enter your PlexiQ LIMS server address:
ServerURLDescription=This is the URL your browser will use to connect to the LIMS server.%n%nFor example:%nhttp://192.168.1.100:8080%nhttps://lims.yourcompany.com
LaunchApp=Launch PlexiQ LIMS Client after installation
CreateDesktopIcon=Create desktop shortcut
CreateStartMenuIcon=Create Start menu shortcut

[Tasks]
Name: "desktopicon"; Description: "{cm:CreateDesktopIcon}"; GroupDescription: "{cm:AdditionalIcons}"
Name: "startmenuicon"; Description: "{cm:CreateStartMenuIcon}"; GroupDescription: "{cm:AdditionalIcons}"

[Files]
Source: "src\lims-client.hta"; DestDir: "{app}"; Flags: ignoreversion
Source: "src\config.ini"; DestDir: "{app}"; Flags: ignoreversion onlyifdoesntexist
Source: "assets\icon.ico"; DestDir: "{app}\assets"; Flags: ignoreversion
Source: "assets\logo.bmp"; DestDir: "{app}\assets"; Flags: ignoreversion

[Icons]
Name: "{group}\PlexiQ LIMS Client"; Filename: "{app}\lims-client.hta"; WorkingDir: "{app}"; IconFilename: "{app}\assets\icon.ico"; Tasks: startmenuicon
Name: "{autodesktop}\PlexiQ LIMS Client"; Filename: "{app}\lims-client.hta"; WorkingDir: "{app}"; IconFilename: "{app}\assets\icon.ico"; Tasks: desktopicon
Name: "{group}\Configure Server URL"; Filename: "{app}\config.ini"; Tasks: startmenuicon
Name: "{group}\Uninstall PlexiQ LIMS Client"; Filename: "{uninstallexe}"

[Run]
Filename: "{app}\lims-client.hta"; Description: "{cm:LaunchApp}"; Flags: postinstall nowait skipifsilent shellexec

[UninstallRun]
Filename: "mshta.exe"; Parameters: "javascript:close()"; Flags: runhidden

[Code]

var
  ServerURLPage: TInputQueryWizardPage;
  ServerURL: String;

procedure InitializeWizard;
begin
  ServerURLPage := CreateInputQueryPage(
    wpSelectTasks,
    'Server Configuration',
    'Enter your PlexiQ LIMS server address',
    'Specify the URL of the PlexiQ LIMS server that this client will connect to.'#13#10 +
    'This should be the full URL including protocol and port.'#13#10#13#10 +
    'Examples:'#13#10 +
    '  http://192.168.1.100:8080'#13#10 +
    '  https://lims.yourcompany.com'#13#10#13#10 +
    'This setting can be changed later by editing config.ini in the installation folder.'
  );

  ServerURLPage.Add('Server URL:', False);

  { Load default from previous installation or use default }
  if RegQueryStringValue(HKLM, 'Software\PlexiQ LIMS Client', 'ServerURL', ServerURL) then
    ServerURLPage.Values[0] := ServerURL
  else
    ServerURLPage.Values[0] := 'http://localhost:8080';
end;

function ShouldSkipPage(PageID: Integer): Boolean;
begin
  Result := False;
end;

function UpdateReadyMemo(Space, NewLine, MemoUserInfoInfo, MemoDirInfo, MemoTypeInfo, MemoComponentsInfo, MemoGroupInfo, MemoTasksInfo: String): String;
var
  S: String;
begin
  S := '';
  S := S + 'Server URL:' + NewLine;
  S := S + '  ' + ServerURLPage.Values[0] + NewLine;
  S := S + NewLine;
  S := S + MemoDirInfo + NewLine;
  S := S + MemoTasksInfo;
  Result := S;
end;

procedure CurStepChanged(CurStep: TSetupStep);
var
  ConfigPath: String;
  Lines: TArrayOfString;
  I: Integer;
begin
  if CurStep = ssPostInstall then
  begin
    { Write server URL to config.ini }
    ConfigPath := ExpandConstant('{app}\config.ini');
    SetArrayLength(Lines, 3);
    Lines[0] := '; PlexiQ LIMS Client Configuration';
    Lines[1] := '; Change URL to your LIMS server address';
    Lines[2] := 'URL=' + ServerURLPage.Values[0];
    SaveStringsToFile(ConfigPath, Lines, False);

    { Save to registry for future upgrades }
    RegWriteStringValue(HKLM, 'Software\PlexiQ LIMS Client', 'ServerURL', ServerURLPage.Values[0]);
    RegWriteStringValue(HKLM, 'Software\PlexiQ LIMS Client', 'InstallPath', ExpandConstant('{app}'));
  end;
end;

procedure CurUninstallStepChanged(CurUninstallStep: TUninstallStep);
begin
  if CurUninstallStep = usPostUninstall then
  begin
    RegDeleteKeyIfEmpty(HKLM, 'Software\PlexiQ LIMS Client');
  end;
end;
