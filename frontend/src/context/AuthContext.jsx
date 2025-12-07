import React, { createContext, useContext, useEffect, useState } from 'react';
import api from '../api/axios';

const AuthContext = createContext(null);

export const AuthProvider = ({ children }) => {
  const [user, setUser] = useState(null);
  const [token, setToken] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const stored = localStorage.getItem('auth');
    if (stored) {
      try {
        const parsed = JSON.parse(stored);
        setUser(parsed.user || null);
        setToken(parsed.token || null);
      } catch {
        // ignore
      }
    }
    setLoading(false);
  }, []);

  const login = async (email, password) => {
    try {
      const res = await api.post('/auth/login.php', { email, password });
      
      // Check for successful response
      if (res.data?.success && res.data?.data) {
        const payload = {
          user: res.data.data.user,
          token: res.data.data.token,
        };
        
        // Validate that we have both user and token
        if (!payload.user || !payload.token) {
          console.error('Login response missing user or token:', res.data);
          return { success: false, message: 'Invalid response from server' };
        }
        
        setUser(payload.user);
        setToken(payload.token);
        localStorage.setItem('auth', JSON.stringify(payload));
        return res.data;
      } else {
        // Return error response from server
        const errorMessage = res.data?.message || 'Login failed';
        console.error('Login failed:', res.data);
        return { success: false, message: errorMessage };
      }
    } catch (error) {
      // Handle network errors or API errors
      console.error('Login error:', error);
      
      // Extract error details
      const statusCode = error.response?.status;
      const errorData = error.response?.data;
      const errorMessage = errorData?.message || error.message || 'Login failed';
      
      // Handle specific error codes
      if (statusCode === 401) {
        // 401 Unauthorized - invalid credentials
        return { 
          success: false, 
          message: errorMessage || 'Invalid email or password',
          code: 'UNAUTHORIZED'
        };
      } else if (statusCode === 403) {
        // 403 Forbidden - account not active
        return { 
          success: false, 
          message: errorMessage || 'Account is not active',
          code: 'FORBIDDEN'
        };
      } else if (statusCode === 400) {
        // 400 Bad Request - validation error
        return { 
          success: false, 
          message: errorMessage || 'Invalid request',
          code: 'BAD_REQUEST'
        };
      } else if (statusCode === 429) {
        // 429 Too Many Requests - rate limit exceeded
        const retryAfter = errorData?.retry_after_minutes || errorData?.retry_after || 5;
        return { 
          success: false, 
          message: errorMessage || `Too many login attempts. Please wait ${retryAfter} minute(s) before trying again.`,
          code: 'RATE_LIMIT',
          retry_after: errorData?.retry_after || 300
        };
      } else if (!error.response) {
        // Network error
        return { 
          success: false, 
          message: 'Network error. Please check your connection.',
          code: 'NETWORK_ERROR'
        };
      }
      
      // Generic error
      return { 
        success: false, 
        message: errorMessage,
        code: 'UNKNOWN_ERROR'
      };
    }
  };

  const register = async (full_name, email, password) => {
    try {
      const res = await api.post('/auth/register.php', { full_name, email, password });
      
      // Check for successful response
      if (res.data?.success) {
        return res.data;
      } else {
        // Return error response from server
        const errorMessage = res.data?.message || 'Registration failed';
        console.error('Registration failed:', res.data);
        return { success: false, message: errorMessage };
      }
    } catch (error) {
      // Handle network errors or API errors
      console.error('Registration error:', error);
      
      // Extract error details
      const statusCode = error.response?.status;
      const errorData = error.response?.data;
      const errorMessage = errorData?.message || error.message || 'Registration failed';
      
      // Handle specific error codes
      if (statusCode === 400) {
        // 400 Bad Request - validation error
        return { 
          success: false, 
          message: errorMessage || 'Invalid input. Please check your information.',
          code: 'BAD_REQUEST'
        };
      } else if (statusCode === 429) {
        // 429 Too Many Requests - rate limit
        return { 
          success: false, 
          message: errorMessage || 'Too many registration attempts. Please try again later.',
          code: 'RATE_LIMIT'
        };
      } else if (!error.response) {
        // Network error
        return { 
          success: false, 
          message: 'Network error. Please check your connection.',
          code: 'NETWORK_ERROR'
        };
      }
      
      // Generic error
      return { 
        success: false, 
        message: errorMessage,
        code: 'UNKNOWN_ERROR'
      };
    }
  };

  const logout = () => {
    setUser(null);
    setToken(null);
    localStorage.removeItem('auth');
  };

  const value = {
    user,
    token,
    loading,
    login,
    register,
    logout,
    isAuthenticated: !!user && !!token,
    role: user?.role || null,
  };

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
};

export const useAuth = () => useContext(AuthContext);


