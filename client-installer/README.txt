========================================
  PlexiQ LIMS - Client Installer Builder
========================================

OVERVIEW
--------
This builds a professional Windows installer (EXE) for client PCs
to connect to a PlexiQ LIMS server. The installer provides:

  - Graphical wizard with branded welcome page
  - Server URL configuration
  - Desktop and Start menu shortcuts
  - Lightweight HTA wrapper application
  - Add/Remove Programs uninstall support

REQUIREMENTS
------------
- Windows 10 or later
- Inno Setup 6 (free): https://jrsoftware.org/isdl.php

BUILD INSTRUCTIONS
------------------
1. Install Inno Setup 6 from https://jrsoftware.org/isdl.php
2. Run build.bat
3. Find the installer at: Output\PlexiQ-LIMS-Client-Setup-1.0.exe

DISTRIBUTION
------------
Copy the generated EXE to client PCs and run it.
During installation, enter the LIMS server URL (e.g., http://192.168.1.50:8080)
Users will get a desktop shortcut to access the LIMS Client Portal.

FILES
-----
setup.iss              - Inno Setup script (main configuration)
build.bat              - Build script to compile the installer
assets\                - Generated icons and logos
  generate-logo.ps1    - PowerShell script to generate BMP/ICO assets
  logo.bmp             - Wizard logo image (generated)
  icon.ico             - Application icon (generated)
src\                   - Installer source files
  lims-client.hta      - HTA web app wrapper (fullscreen client)
  config.ini           - Server URL configuration template
