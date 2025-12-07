import { useSocket } from '../context/SocketContext';

/**
 * Custom hook to access socket functionality
 * 
 * @returns {Object} Socket context value with:
 *   - socket: Socket.io instance
 *   - isConnected: Boolean connection status
 *   - notifications: Array of notifications
 *   - unreadCount: Number of unread notifications
 *   - markAsRead: Function to mark notification as read
 *   - markAllAsRead: Function to mark all as read
 *   - clearNotifications: Function to clear all notifications
 */
const useSocketHook = () => {
  return useSocket();
};

export default useSocketHook;

