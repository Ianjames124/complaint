#!/bin/bash

echo "============================================"
echo "Starting Socket.io Server"
echo "============================================"
echo ""

# Check if .env exists, if not create it
if [ ! -f .env ]; then
    echo "Creating .env file..."
    cat > .env << EOF
PORT=4000
ALLOWED_ORIGINS=http://localhost:3000,http://localhost:5173,http://localhost:5174,http://localhost/complaint,http://127.0.0.1:3000,http://127.0.0.1:5173
EOF
    echo ".env file created!"
    echo ""
fi

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo "Installing dependencies..."
    npm install
    echo ""
fi

echo "Starting server on port 4000..."
echo ""
node index.js
