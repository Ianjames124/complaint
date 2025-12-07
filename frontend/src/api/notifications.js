import api from './axios';

/**
 * Fetch notifications from API
 */
export const fetchNotifications = async (unreadOnly = false, limit = 50) => {
  const res = await api.get('/notifications/get-notifications.php', {
    params: {
      unread_only: unreadOnly,
      limit
    }
  });
  return res.data;
};

/**
 * Mark notification as read
 */
export const markNotificationRead = async (notificationId) => {
  const res = await api.post('/notifications/mark-read.php', {
    notification_id: notificationId
  });
  return res.data;
};

/**
 * Mark all notifications as read
 */
export const markAllNotificationsRead = async () => {
  const res = await api.post('/notifications/mark-read.php', {
    mark_all: true
  });
  return res.data;
};

