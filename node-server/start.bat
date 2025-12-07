@echo off
echo ============================================
echo Starting Socket.io Server
echo ============================================
echo.

REM Check if .env exists, if not create it
if not exist .env (
    echo Creating .env file...
    (
        echo PORT=4000
        echo ALLOWED_ORIGINS=http://localhost:3000,http://localhost:5173,http://localhost:5174,http://localhost/complaint,http://127.0.0.1:3000,http://127.0.0.1:5173
    ) > .env
    echo .env file created!
    echo.
)

REM Check if node_modules exists
if not exist node_modules (
    echo Installing dependencies...
    call npm install
    echo.
)

echo Starting server on port 4000...
echo.
node index.js
