========================================
   PlexiQ LIMS - Server Installer Builder
========================================

OVERVIEW
--------
This builds a professional Windows installer (EXE) for the PlexiQ LIMS
server. The installer deploys the complete LIMS application onto a
Windows server with all management scripts and validation tools.

WHAT IT INSTALLS
----------------
- Full PlexiQ LIMS application (PHP codebase, routes, views, etc.)
- Management scripts (start/stop/restart/status server)
- Database setup script (auto-creates database + imports schema)
- Installation validation tool
- Windows Service installer (runs LIMS as background service)
- Start menu and desktop shortcuts
- Add/Remove Programs uninstall support

REQUIREMENTS
------------
Build machine:
- Windows 10 or later
- Inno Setup 6 (free): https://jrsoftware.org/isdl.php
- PlexiQ LIMS project files (this repo)

Target server:
- Windows 10/11 or Windows Server 2016+
- PostgreSQL 14 or later: https://www.postgresql.org/download/windows/
- PHP 8.0 or later: https://windows.php.net/download/
- PHP extensions: pdo_pgsql, json, session, mbstring

BUILD INSTRUCTIONS
------------------
1. Install Inno Setup 6 on your build machine
2. Open a Command Prompt in this directory
3. Run: build.bat
4. Find the installer at: Output\PlexiQ-LIMS-Server-Setup-2.0.exe

INSTALLATION ON TARGET SERVER
-----------------------------
1. Prerequisites on target server:
   a. Install PostgreSQL 14+ (must be running)
   b. Install PHP 8.0+ with pdo_pgsql extension enabled
   c. Ensure php.exe is in system PATH

2. Run the installer as Administrator:
   - Accept the license agreement
   - Choose installation directory (default: C:\Program Files\PlexiQ-LIMS)
   - Configure server port (default: 8080)
   - Enter PostgreSQL connection details
   - Complete installation

3. Post-installation:
   - The installer runs database setup automatically
   - Start the server: Start Menu > PlexiQ LIMS > Start Server
   - Open dashboard: http://localhost:8080
   - Default login: admin / admin@123
   - Validate: Start Menu > PlexiQ LIMS > Validate Installation

4. Optional - Run as Windows Service:
   - Open PowerShell as Administrator
   - Navigate to installation directory
   - Run: powershell -ExecutionPolicy Bypass -File install-service.ps1
   - The LIMS will start automatically on system boot

FILES
-----
setup.iss                - Inno Setup script (main configuration)
build.bat                - Build script to compile installer
README.txt               - This file
assets\                  - Generated icons and logos
  generate-logo.ps1      - PowerShell script to generate BMP/ICO assets
  logo.bmp              - Wizard logo image
  icon.ico              - Application icon
  plexiq-icon.png       - PNG app icon
src\                     - Installer source files
  start-server.bat       - Start the LIMS server
  stop-server.bat        - Stop the LIMS server
  restart-server.bat     - Restart the LIMS server
  status-server.bat      - Check server status
  setup-database.ps1     - Create database and import schema
  setup-database.bat     - Batch wrapper for database setup
  validate-install.ps1   - Post-installation validation
  validate-install.bat   - Batch wrapper for validation
  install-service.ps1    - Install as Windows service (via NSSM)
  remove-service.ps1     - Remove Windows service
  config.ini             - Server configuration file

TROUBLESHOOTING
---------------
Issue: "PHP not found" warning during install
  Solution: Install PHP 8.0+ or use the portable PHP option

Issue: "PostgreSQL not found" warning during install
  Solution: Install PostgreSQL 14+ from postgresql.org

Issue: Database setup fails
  Solution: Ensure PostgreSQL is running and credentials are correct

Issue: Server won't start
  Solution: Check port 8080 is not in use by another application

Issue: "500 Internal Server Error" on dashboard
  Solution: Run validation tool; check storage/logs/lims-error.log

SUPPORT
-------
Website: https://plexiq.ai
Documentation: docs/ directory in installation folder
