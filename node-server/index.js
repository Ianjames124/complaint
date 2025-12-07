require('dotenv').config();
const express = require('express');
const http = require('http');
const { Server } = require('socket.io');
const cors = require('cors');

const app = express();
const server = http.createServer(app);

// Parse allowed origins from environment
const allowedOrigins = process.env.ALLOWED_ORIGINS 
  ? process.env.ALLOWED_ORIGINS.split(',').map(origin => origin.trim())
  : ['http://localhost:3000', 'http://localhost:5173', 'http://localhost:5174', 'http://localhost/complaint'];

// CORS configuration for Socket.io
const io = new Server(server, {
  cors: {
    origin: allowedOrigins,
    methods: ['GET', 'POST'],
    credentials: true,
    allowedHeaders: ['Content-Type', 'Authorization']
  },
  transports: ['websocket', 'polling'],
  allowEIO3: false, // Use EIO4 only for better compatibility
  pingTimeout: 60000, // Increase ping timeout
  pingInterval: 25000, // Increase ping interval
  upgradeTimeout: 10000, // Timeout for transport upgrade
  maxHttpBufferSize: 1e6, // 1MB max buffer
  connectTimeout: 45000, // Connection timeout
  serveClient: false // Don't serve client files
});

// CORS middleware for Express
app.use(cors({
  origin: (origin, callback) => {
    // Allow requests with no origin (like mobile apps or curl requests)
    if (!origin) return callback(null, true);
    
    if (allowedOrigins.indexOf(origin) !== -1 || allowedOrigins.includes('*')) {
      callback(null, true);
    } else {
      callback(new Error('Not allowed by CORS'));
    }
  },
  credentials: true,
  methods: ['GET', 'POST', 'OPTIONS'],
  allowedHeaders: ['Content-Type', 'Authorization']
}));

app.use(express.json());

const PORT = process.env.PORT || 4000;

// Store connected users by role and ID
const connectedUsers = {
  admin: new Set(),
  staff: new Map(), // staff_id -> socket_id
  citizen: new Map() // citizen_id -> socket_id
};

// Socket.io connection handling
io.on('connection', (socket) => {
  console.log(`\n✅ Client connected: ${socket.id}`);
  console.log(`   Transport: ${socket.conn.transport.name}`);
  console.log(`   Remote address: ${socket.handshake.address}`);
  console.log(`   Headers:`, socket.handshake.headers);

  // Handle transport upgrade
  socket.conn.on('upgrade', () => {
    console.log(`   Transport upgraded to: ${socket.conn.transport.name}`);
  });

  // Note: Connection timeout is handled at server level via pingTimeout/pingInterval
  // No need to set individual socket timeouts in Socket.IO v4

  // Handle user authentication and room joining
  socket.on('authenticate', (data) => {
    try {
      const { role, userId } = data || {};

      if (!role || !userId) {
        console.warn(`⚠️ Invalid authentication data from ${socket.id}:`, data);
        socket.emit('error', { message: 'Invalid authentication data. Role and userId are required.' });
        return;
      }

      // Validate role
      if (!['admin', 'staff', 'citizen'].includes(role)) {
        console.warn(`⚠️ Invalid role from ${socket.id}:`, role);
        socket.emit('error', { message: 'Invalid role. Must be admin, staff, or citizen.' });
        return;
      }

      // Store user connection
      if (role === 'admin') {
        connectedUsers.admin.add(socket.id);
        socket.join('admin');
        console.log(`✅ Admin authenticated: ${socket.id}`);
      } else if (role === 'staff') {
        connectedUsers.staff.set(userId.toString(), socket.id);
        socket.join(`staff_${userId}`);
        console.log(`✅ Staff ${userId} authenticated: ${socket.id}`);
      } else if (role === 'citizen') {
        connectedUsers.citizen.set(userId.toString(), socket.id);
        socket.join(`citizen_${userId}`);
        console.log(`✅ Citizen ${userId} authenticated: ${socket.id}`);
      }

      socket.emit('authenticated', { role, userId, socketId: socket.id });
    } catch (error) {
      console.error(`❌ Error during authentication for ${socket.id}:`, error);
      socket.emit('error', { message: 'Authentication failed: ' + error.message });
    }
  });

  // Handle disconnection
  socket.on('disconnect', (reason) => {
    console.log(`\n❌ Client disconnected: ${socket.id}`);
    console.log(`   Reason: ${reason}`);

    // Remove from connected users
    connectedUsers.admin.delete(socket.id);
    
    // Remove from staff map
    for (const [userId, socketId] of connectedUsers.staff.entries()) {
      if (socketId === socket.id) {
        connectedUsers.staff.delete(userId);
        console.log(`   Removed staff ${userId} from connected users`);
        break;
      }
    }

    // Remove from citizen map
    for (const [userId, socketId] of connectedUsers.citizen.entries()) {
      if (socketId === socket.id) {
        connectedUsers.citizen.delete(userId);
        console.log(`   Removed citizen ${userId} from connected users`);
        break;
      }
    }
  });

  // Handle connection errors
  socket.on('error', (error) => {
    console.error(`❌ Socket error for ${socket.id}:`, error);
  });

  // Handle transport errors
  socket.conn.on('error', (error) => {
    console.error(`❌ Transport error for ${socket.id}:`, error);
  });

  // Handle ping/pong for connection health
  socket.on('ping', () => {
    socket.emit('pong');
  });
});

// HTTP endpoint for PHP to emit events
app.post('/emit-event', (req, res) => {
  const { event, data, target } = req.body;

  if (!event) {
    return res.status(400).json({ success: false, message: 'Event name is required' });
  }

  try {
    switch (event) {
      case 'new_complaint':
        // Emit to all admins
        io.to('admin').emit('new_complaint', data);
        console.log(`Emitted new_complaint to admin room`);
        break;

      case 'assignment_created':
        // Emit to specific staff member
        if (data.staff_id) {
          io.to(`staff_${data.staff_id}`).emit('assignment_created', data);
          console.log(`Emitted assignment_created to staff_${data.staff_id}`);
        }
        break;

      case 'complaint_status_updated':
        // Emit to specific citizen
        if (data.citizen_id) {
          io.to(`citizen_${data.citizen_id}`).emit('complaint_status_updated', data);
          console.log(`Emitted complaint_status_updated to citizen_${data.citizen_id}`);
        }
        // Also notify admins
        io.to('admin').emit('complaint_status_updated', data);
        break;

      case 'complaint_assigned':
        // Emit to specific staff and admin
        if (data.staff_id) {
          io.to(`staff_${data.staff_id}`).emit('complaint_assigned', data);
        }
        io.to('admin').emit('complaint_assigned', data);
        break;

      default:
        // Generic event emission
        if (target) {
          if (target.type === 'admin') {
            io.to('admin').emit(event, data);
          } else if (target.type === 'staff' && target.id) {
            io.to(`staff_${target.id}`).emit(event, data);
          } else if (target.type === 'citizen' && target.id) {
            io.to(`citizen_${target.id}`).emit(event, data);
          } else {
            io.emit(event, data); // Broadcast to all
          }
        } else {
          io.emit(event, data); // Broadcast to all if no target
        }
    }

    res.json({ success: true, message: 'Event emitted successfully' });
  } catch (error) {
    console.error('Error emitting event:', error);
    res.status(500).json({ success: false, message: 'Failed to emit event', error: error.message });
  }
});

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({ 
    status: 'ok', 
    connectedUsers: {
      admin: connectedUsers.admin.size,
      staff: connectedUsers.staff.size,
      citizen: connectedUsers.citizen.size
    }
  });
});

// Start server
server.listen(PORT, '0.0.0.0', () => {
  console.log('═══════════════════════════════════════════════════════');
  console.log(`🚀 Socket.io server running on port ${PORT}`);
  console.log(`📡 Ready to receive events from PHP backend`);
  console.log(`🌐 CORS enabled for:`);
  allowedOrigins.forEach(origin => {
    console.log(`   ✓ ${origin}`);
  });
  console.log(`🔌 WebSocket URL: ws://localhost:${PORT}`);
  console.log(`📡 HTTP URL: http://localhost:${PORT}`);
  console.log('═══════════════════════════════════════════════════════');
  console.log('\n✅ Server is ready! Connect your React app now.\n');
  console.log('📝 Test the server by visiting: http://localhost:4000/health\n');
});

// Handle server errors
server.on('error', (error) => {
  if (error.code === 'EADDRINUSE') {
    console.error(`❌ Port ${PORT} is already in use!`);
    console.error('   Please stop the process using port 4000 or change the PORT in .env');
    process.exit(1);
  } else {
    console.error('❌ Server error:', error);
    process.exit(1);
  }
});

// Export io for use in other modules
module.exports = { io, server };

