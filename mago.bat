@echo off
REM Windows shim for Infection's Mago static analysis tool.
REM
REM Infection prefixes PHP_BINARY to the static analysis executable whenever its
REM "PHP interpreter available" probe fails. That probe shells out to `command -v`,
REM a POSIX builtin absent from cmd.exe, so on Windows Mago is invoked as
REM
REM   php C:\...\mago.exe --version
REM
REM and PHP reports a parse error at a byte inside the Rust binary.
REM
REM CommandLineBuilder short-circuits on a .bat before reaching that probe, and
REM StaticAnalysisToolExecutableFinder probes mago.bat before mago, so this file
REM makes Infection exec the native binary directly.
REM
REM Harmless on Linux and in CI: the file is committed non-executable, so
REM Symfony's ExecutableFinder skips it and the real mago on PATH is used.
REM
REM See webinertia/webware-tools issue 59.
mago.exe %*
