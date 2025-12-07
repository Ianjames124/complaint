# Real-time Socket.io Server

This Node.js server handles WebSocket connections for real-time features in the E-Complaint System.

## 🚀 Quick Start

### Windows:
```bash
cd node-server
start.bat
```

### Linux/Mac:
```bash
cd node-server
chmod +x start.sh
./start.sh
```

### Manual Setup:

1. Install dependencies:
```bash
cd node-server
npm install
```

2. Create `.env` file (or run `start.bat`/`start.sh` which creates it automatically):
```
PORT=4000
ALLOWED_ORIGINS=http://localhost:3000,http://localhost:5173,http://localhost:5174,http://localhost/complaint,http://127.0.0.1:3000,http://127.0.0.1:5173
```

3. Start the server:
```bash
npm start
```

For development with auto-reload:
```bash
npm run dev
```

## ⚠️ IMPORTANT: Server Must Be Running

**The React frontend will show connection errors if this server is not running!**

Always start this server before starting your React app.

## How It Works

1. **PHP Backend** sends HTTP POST requests to `http://localhost:4000/emit-event` with event data
2. **Node Server** receives the POST and emits Socket.io events to connected clients
3. **React Frontend** connects via Socket.io client and listens for events

## Events

- `new_complaint` - Emitted to admin room when citizen submits complaint
- `assignment_created` - Emitted to specific staff member when assigned
- `complaint_status_updated` - Emitted to citizen and admin when status changes

## Testing

1. Start the Node server: `npm start`
2. Start your PHP backend (XAMPP/Apache)
3. Start your React frontend: `npm run dev`
4. Open multiple browser tabs/windows to test real-time updates

## Health Check

Visit `http://localhost:4000/health` to see connection status.

