import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import toast from 'react-hot-toast';
import api from '../../api/axios';
import AdminNavbar from '../../components/navbar/AdminNavbar';
import Card from '../../components/common/Card';
import Button from '../../components/common/Button';
import { UserPlus, Loader2 } from 'lucide-react';

const CreateStaff = () => {
  const [form, setForm] = useState({
    full_name: '',
    email: '',
    department_id: '',
    password: '',
  });
  const [loading, setLoading] = useState(false);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const navigate = useNavigate();

  // Check authentication status on component mount
  useEffect(() => {
    const checkAuth = () => {
      try {
        const stored = localStorage.getItem('auth');
        if (!stored) {
          throw new Error('No auth data found');
        }

        const { token, user } = JSON.parse(stored);
        if (!token) {
          throw new Error('No token found');
        }

        if (token && user) {
          setIsAuthenticated(true);
        } else {
          throw new Error('Invalid auth data');
        }
      } catch (error) {
        console.error('Auth check failed:', error);
        localStorage.removeItem('auth');
        toast.error('Please log in to continue');
        navigate('/login', { state: { from: '/admin/create-staff' } });
      } finally {
        setIsLoading(false);
      }
    };

    checkAuth();
  }, [navigate]);

  const handleChange = (e) => {
    setForm((prev) => ({ ...prev, [e.target.name]: e.target.value }));
  };

  // Generate a random password
  const generateRandomPassword = () => {
    const length = 12;
    const charset = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+~`|}{[]:;?><,./-=";
    let password = "";
    for (let i = 0; i < length; i++) {
      password += charset.charAt(Math.floor(Math.random() * charset.length));
    }
    return password;
  };

  const handleGeneratePassword = () => {
    const newPassword = generateRandomPassword();
    setForm((prev) => ({ ...prev, password: newPassword }));
    toast.success('Random password generated');
  };

  const handleSubmit = async (e) => {
    e.preventDefault();
    setLoading(true);
    
    // Client-side validation
    const errors = [];
    if (!form.full_name.trim()) errors.push('Full name is required');
    if (!form.email.trim()) {
      errors.push('Email is required');
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
      errors.push('Please enter a valid email address');
    }
    
    if (form.password && form.password.length < 8) {
      errors.push('Password must be at least 8 characters long');
    }
    
    if (form.department_id && isNaN(Number(form.department_id))) {
      errors.push('Department ID must be a number');
    }
    
    if (errors.length > 0) {
      setLoading(false);
      errors.forEach(error => toast.error(error));
      return;
    }
    
    try {
      const payload = {
        full_name: form.full_name.trim(),
        email: form.email.trim().toLowerCase(),
        department_id: form.department_id ? Number(form.department_id) : null,
        password: form.password || generateRandomPassword(),
      };
      
      const response = await api.post('/admin/create-staff.php', payload, {
        headers: {
          'Content-Type': 'application/json',
        },
        withCredentials: true
      });

      const data = response.data;
      
      if (!data.success) {
        throw new Error(data.message || 'Failed to create staff');
      }

      toast.success(data.message || 'Staff created successfully');
      
      // Reset form
      setForm({ full_name: '', email: '', department_id: '', password: '' });
      
    } catch (error) {
      console.error('Error creating staff:', {
        message: error.message,
        response: error.response?.data,
        status: error.response?.status,
      });
      
      let errorMessage = error.message || 'Failed to create staff';
      
      // Handle different error cases
      if (error.response) {
        if (error.response.status === 401) {
          errorMessage = 'Your session has expired. Please log in again.';
          localStorage.removeItem('auth');
          navigate('/login');
        } else if (error.response.status === 403) {
          errorMessage = 'You do not have permission to perform this action.';
          if (error.response.data?.message) {
            errorMessage += ` ${error.response.data.message}`;
          }
        } else if (error.response.data?.message) {
          errorMessage = error.response.data.message;
        }
      }
      
      toast.error(errorMessage);
      
    } finally {
      setLoading(false);
    }
  };

  // Show loading state while checking auth
  if (isLoading) {
    return (
      <div className="flex justify-center items-center h-screen bg-gray-50 dark:bg-gray-900">
        <Loader2 className="w-8 h-8 animate-spin text-primary-600" />
      </div>
    );
  }

  // Redirect to login if not authenticated
  if (!isAuthenticated) {
    return null; // The useEffect will handle the navigation
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <AdminNavbar />
      <main className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="mb-8">
          <div className="flex items-center gap-3 mb-2">
            <UserPlus className="w-8 h-8 text-primary-600 dark:text-primary-400" />
            <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
              Create Staff Member
            </h1>
          </div>
          <p className="text-gray-600 dark:text-gray-400">
            Add a new staff member to the system
          </p>
        </div>

        <Card>
          <form onSubmit={handleSubmit} className="space-y-6">
            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Full Name <span className="text-red-500">*</span>
              </label>
              <input
                type="text"
                name="full_name"
                value={form.full_name}
                onChange={handleChange}
                required
                className="input-field"
                disabled={loading}
                placeholder="Enter full name"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Email <span className="text-red-500">*</span>
              </label>
              <input
                type="email"
                name="email"
                value={form.email}
                onChange={handleChange}
                required
                className="input-field"
                disabled={loading}
                placeholder="staff@example.com"
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Department ID (Optional)
              </label>
              <input
                type="number"
                name="department_id"
                value={form.department_id}
                onChange={handleChange}
                className="input-field"
                disabled={loading}
                placeholder="Enter department ID"
              />
            </div>

            <div>
              <div className="flex items-center justify-between mb-2">
                <label className="block text-sm font-medium text-gray-700 dark:text-gray-300">
                  Password (Optional)
                </label>
                <button
                  type="button"
                  onClick={handleGeneratePassword}
                  className="text-sm text-primary-600 dark:text-primary-400 hover:underline"
                >
                  Generate Random
                </button>
              </div>
              <input
                type="password"
                name="password"
                value={form.password}
                onChange={handleChange}
                className="input-field"
                disabled={loading}
                placeholder="Leave blank to generate random password"
              />
              <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                If left blank, a random password will be generated
              </p>
            </div>

            <div className="flex gap-4 pt-4">
              <Button
                type="submit"
                variant="primary"
                disabled={loading}
                className="flex-1"
              >
                {loading ? (
                  <>
                    <Loader2 className="w-4 h-4 mr-2 animate-spin" />
                    Creating...
                  </>
                ) : (
                  <>
                    <UserPlus className="w-4 h-4 mr-2" />
                    Create Staff
                  </>
                )}
              </Button>
              <Button
                type="button"
                variant="secondary"
                onClick={() => navigate('/admin/manage-staff')}
                disabled={loading}
              >
                Cancel
              </Button>
            </div>
          </form>
        </Card>
      </main>
    </div>
  );
};

export default CreateStaff;
