import axios from 'axios';

const API_BASE_URL = 'http://localhost/complaint/backend/api';

const api = axios.create({
  baseURL: API_BASE_URL,
  withCredentials: true, // Enable sending cookies with requests
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
});

// Request interceptor to add auth token to requests
api.interceptors.request.use(
  (config) => {
    const stored = localStorage.getItem('auth');
    if (stored) {
      try {
        const { token } = JSON.parse(stored);
        if (token) {
          config.headers.Authorization = `Bearer ${token}`;
        }
      } catch (error) {
        console.error('Error parsing auth data:', error);
      }
    }
    return config;
  },
  (error) => {
    return Promise.reject(error);
  }
);

// Response interceptor to handle errors
api.interceptors.response.use(
  (response) => response,
  (error) => {
    const { status, data } = error.response || {};
    
    // Handle 401 Unauthorized
    if (status === 401) {
      // Clear any existing auth data
      localStorage.removeItem('auth');
      
      // Redirect to login page if not already there
      if (!window.location.pathname.includes('/login')) {
        window.location.href = '/login';
      }
    }
    
    // Handle 403 Forbidden
    if (status === 403) {
      console.error('Access denied. You do not have permission to perform this action.');
      // Don't redirect, just log - let the component handle it
    }
    
    // Handle 400 Bad Request - log validation errors
    if (status === 400) {
      console.error('Bad Request:', data?.message || 'Invalid request');
    }
    
    // Handle 500 Internal Server Error
    if (status === 500) {
      console.error('Server Error:', data?.message || 'An error occurred on the server');
      // Log additional error details in development
      if (import.meta.env.DEV && data?.error_code) {
        console.error('Error Code:', data.error_code);
        if (data.file && data.line) {
          console.error('Error Location:', data.file, 'Line:', data.line);
        }
      }
    }
    
    // Handle 429 Too Many Requests
    if (status === 429) {
      const retryAfter = data?.retry_after_minutes || Math.ceil((data?.retry_after || 300) / 60);
      console.warn('Rate limit exceeded:', data?.message || `Too many requests. Please wait ${retryAfter} minute(s).`);
      // The error will be handled by the component that made the request
    }
    
    // Handle network errors
    if (!error.response) {
      console.error('Network Error:', error.message);
    }
    
    return Promise.reject(error);
  }
);

export default api;


