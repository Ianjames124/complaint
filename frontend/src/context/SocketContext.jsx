import React, { createContext, useContext, useEffect, useState, useRef } from 'react';
import { io } from 'socket.io-client';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useAuth } from './AuthContext';
import toast from 'react-hot-toast';
import { fetchNotifications, markNotificationRead, markAllNotificationsRead } from '../api/notifications';

const SocketContext = createContext();

export const useSocket = () => {
  const context = useContext(SocketContext);
  if (!context) {
    throw new Error('useSocket must be used within SocketProvider');
  }
  return context;
};

export const SocketProvider = ({ children }) => {
  const { user, isAuthenticated } = useAuth();
  const queryClient = useQueryClient();
  const [socket, setSocket] = useState(null);
  const [isConnected, setIsConnected] = useState(false);
  const [notifications, setNotifications] = useState([]);
  const reconnectAttempts = useRef(0);
  const maxReconnectAttempts = 5;

  // Fetch notifications from API
  const { data: apiNotifications } = useQuery({
    queryKey: ['notifications', user?.id],
    queryFn: () => fetchNotifications(false, 50),
    enabled: !!isAuthenticated && !!user,
    refetchInterval: 30000, // Refresh every 30 seconds
  });

  // Sync API notifications with local state
  useEffect(() => {
    if (apiNotifications?.data?.notifications) {
      // Merge API notifications with socket notifications
      setNotifications((prev) => {
        const socketIds = new Set(prev.map(n => n.id));
        const apiNotifs = apiNotifications.data.notifications.map(n => ({
          id: n.id,
          type: n.type || 'in_app',
          title: n.title,
          message: n.message,
          data: { complaint_id: n.related_complaint_id },
          timestamp: n.created_at,
          read: n.read || n.status === 'read',
          read_at: n.read_at
        }));
        
        // Combine, prioritizing socket notifications (newer)
        const combined = [...prev, ...apiNotifs.filter(n => !socketIds.has(n.id))];
        // Sort by timestamp, newest first
        return combined.sort((a, b) => new Date(b.timestamp) - new Date(a.timestamp));
      });
    }
  }, [apiNotifications]);

  useEffect(() => {
    if (!isAuthenticated || !user) {
      // Disconnect if user logs out
      if (socket) {
        console.log('🔌 Disconnecting socket (user logged out)');
        socket.disconnect();
        setSocket(null);
        setIsConnected(false);
      }
      return;
    }

    // Prevent creating multiple socket instances
    if (socket && socket.connected) {
      console.log('🔌 Socket already connected, skipping new connection');
      return;
    }

    // Socket.io server URL
    const SOCKET_URL = import.meta.env.VITE_SOCKET_URL || 'http://localhost:4000';

    console.log('🔌 Attempting to connect to Socket.io server:', SOCKET_URL);

    // Create socket connection with improved settings
    const newSocket = io(SOCKET_URL, {
      transports: ['websocket', 'polling'], // Try websocket first, fallback to polling
      upgrade: true, // Allow transport upgrade
      rememberUpgrade: true, // Remember transport preference
      withCredentials: true,
      reconnection: true,
      reconnectionAttempts: 10, // Limit reconnection attempts to prevent infinite loops
      reconnectionDelay: 2000, // Start with 2 second delay
      reconnectionDelayMax: 10000, // Max 10 seconds between attempts
      timeout: 20000, // Connection timeout
      forceNew: false, // Reuse existing connection if available
      autoConnect: true,
      // Additional options for stability
      closeOnBeforeunload: false, // Don't close on page unload
      rejectUnauthorized: false, // For development only
    });

    // Connection event handlers
    newSocket.on('connect', () => {
      console.log('✅ Socket connected:', newSocket.id);
      console.log('   Transport:', newSocket.io.engine?.transport?.name || 'unknown');
      console.log('   Socket URL:', SOCKET_URL);
      setIsConnected(true);
      reconnectAttempts.current = 0;

      // Authenticate with server after connection
      if (user && user.role && user.id) {
        newSocket.emit('authenticate', {
          role: user.role,
          userId: user.id.toString(),
        });
        console.log('🔐 Authentication sent:', { role: user.role, userId: user.id });
      }
    });

    // Handle transport upgrade
    newSocket.io.on('upgrade', () => {
      console.log('🔄 Transport upgraded to:', newSocket.io.engine?.transport?.name);
    });

    newSocket.on('authenticated', (data) => {
      console.log('✅ Socket authenticated:', data);
      toast.success('Real-time connection established', {
        icon: '✅',
        duration: 3000,
      });
    });

    newSocket.on('disconnect', (reason) => {
      console.log('❌ Socket disconnected. Reason:', reason);
      setIsConnected(false);
      
      // Log disconnect details for debugging
      if (reason === 'transport close') {
        console.warn('   Transport closed - possible network issue or server restart');
      } else if (reason === 'transport error') {
        console.error('   Transport error - WebSocket connection failed');
      } else if (reason === 'ping timeout') {
        console.warn('   Ping timeout - connection lost');
      }
      
      // Only show toast for unexpected disconnects
      if (reason === 'io server disconnect') {
        // Server disconnected, reconnect manually
        console.log('🔄 Server disconnected, attempting to reconnect...');
        newSocket.connect();
      } else if (reason !== 'io client disconnect') {
        // Network issues, will auto-reconnect
        console.log('🔄 Will attempt to reconnect automatically...');
      }
    });

    newSocket.on('connect_error', (error) => {
      console.error('❌ Socket connection error:', error.message);
      console.error('Error type:', error.type);
      console.error('Error details:', error);
      reconnectAttempts.current++;
      
      // Only show error toast on first attempt to avoid spam
      if (reconnectAttempts.current === 1) {
        console.warn('⚠️ Make sure the Node.js Socket.io server is running on port 4000');
        console.warn('   Run: cd node-server && npm start');
        console.warn('   Or use: node-server/start.bat (Windows)');
        console.warn('   Server URL:', SOCKET_URL);
        
        // Show helpful toast with instructions (only once)
        toast.error('Cannot connect to real-time server. Real-time features will be unavailable.', {
          duration: 8000,
          icon: '⚠️',
          id: 'socket-connection-error', // Prevent duplicate toasts
        });
      }
      
      // Will auto-reconnect, so don't show additional errors
      if (reconnectAttempts.current <= 10) {
        console.log(`🔄 Reconnection attempt ${reconnectAttempts.current}/10...`);
      }
    });

    newSocket.on('reconnect', (attemptNumber) => {
      console.log(`✅ Socket reconnected after ${attemptNumber} attempts`);
      reconnectAttempts.current = 0;
      setIsConnected(true);
      
      // Re-authenticate after reconnection
      if (user && user.role && user.id) {
        newSocket.emit('authenticate', {
          role: user.role,
          userId: user.id.toString(),
        });
      }
      
      toast.success('Real-time connection restored', {
        icon: '✅',
        duration: 3000,
      });
    });

    newSocket.on('reconnect_attempt', (attemptNumber) => {
      console.log(`🔄 Reconnection attempt ${attemptNumber}...`);
    });

    newSocket.on('reconnect_error', (error) => {
      console.error('❌ Reconnection error:', error.message);
    });

    newSocket.on('reconnect_failed', () => {
      console.error('❌ Reconnection failed after all attempts');
      console.error('   Please ensure the Socket.IO server is running on:', SOCKET_URL);
      setIsConnected(false);
      toast.error(
        'Unable to connect to real-time server. Please ensure the server is running:\n\n1. Open terminal\n2. cd node-server\n3. npm start',
        {
          duration: 12000,
          icon: '❌',
          id: 'socket-reconnect-failed',
        }
      );
    });

    // Real-time event handlers
    newSocket.on('new_complaint', (data) => {
      console.log('New complaint received:', data);
      if (user.role === 'admin') {
        const notification = {
          id: Date.now(),
          type: 'new_complaint',
          title: 'New Complaint Submitted',
          message: `${data.citizen_name || 'A citizen'} submitted a new complaint: ${data.title}`,
          data: data,
          timestamp: new Date().toISOString(),
          read: false,
        };
        setNotifications((prev) => [notification, ...prev]);
        toast.success(`New complaint: ${data.title}`, {
          icon: '📢',
          duration: 5000,
        });
      }
    });

    newSocket.on('assignment_created', (data) => {
      console.log('Assignment created:', data);
      if (user.role === 'staff' && data.staff_id === user.id) {
        const notification = {
          id: Date.now(),
          type: 'assignment',
          title: 'New Assignment',
          message: `A new complaint has been assigned to you: ${data.title}`,
          data: data,
          timestamp: new Date().toISOString(),
          read: false,
        };
        setNotifications((prev) => [notification, ...prev]);
        toast.success(`New assignment: ${data.title}`, {
          icon: '📋',
          duration: 5000,
        });
      }
    });

    newSocket.on('complaint_status_updated', (data) => {
      console.log('Complaint status updated:', data);
      if (user.role === 'citizen' && data.citizen_id === user.id) {
        const notification = {
          id: Date.now(),
          type: 'status_update',
          title: 'Status Updated',
          message: `Your complaint "${data.title}" status changed to: ${data.new_status}`,
          data: data,
          timestamp: new Date().toISOString(),
          read: false,
        };
        setNotifications((prev) => [notification, ...prev]);
        toast.success(`Status updated: ${data.new_status}`, {
          icon: '🔄',
          duration: 5000,
        });
      } else if (user.role === 'admin') {
        // Admin also gets notified of status updates
        const notification = {
          id: Date.now(),
          type: 'status_update',
          title: 'Complaint Status Updated',
          message: `Complaint #${data.complaint_id} status changed to: ${data.new_status}`,
          data: data,
          timestamp: new Date().toISOString(),
          read: false,
        };
        setNotifications((prev) => [notification, ...prev]);
      }
    });

    // Ping/pong for connection health
    const pingInterval = setInterval(() => {
      if (newSocket.connected) {
        newSocket.emit('ping');
      }
    }, 30000); // Every 30 seconds

    setSocket(newSocket);

    // Cleanup
    return () => {
      clearInterval(pingInterval);
      newSocket.disconnect();
      setSocket(null);
      setIsConnected(false);
    };
  }, [isAuthenticated, user]);

  const markAsRead = async (notificationId) => {
    // Update local state immediately
    setNotifications((prev) =>
      prev.map((notif) =>
        notif.id === notificationId ? { ...notif, read: true } : notif
      )
    );
    
    // Mark as read in API
    try {
      await markNotificationRead(notificationId);
      queryClient.invalidateQueries(['notifications']);
    } catch (error) {
      console.error('Failed to mark notification as read:', error);
    }
  };

  const markAllAsRead = async () => {
    // Update local state immediately
    setNotifications((prev) => prev.map((notif) => ({ ...notif, read: true })));
    
    // Mark all as read in API
    try {
      await markAllNotificationsRead();
      queryClient.invalidateQueries(['notifications']);
    } catch (error) {
      console.error('Failed to mark all notifications as read:', error);
    }
  };

  const clearNotifications = () => {
    setNotifications([]);
  };

  const unreadCount = notifications.filter((n) => !n.read).length;

  const value = {
    socket,
    isConnected,
    notifications,
    unreadCount,
    markAsRead,
    markAllAsRead,
    clearNotifications,
  };

  return <SocketContext.Provider value={value}>{children}</SocketContext.Provider>;
};

