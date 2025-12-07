import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import {
  LineChart,
  Line,
  BarChart,
  Bar,
  PieChart,
  Pie,
  Cell,
  XAxis,
  YAxis,
  CartesianGrid,
  Tooltip,
  Legend,
  ResponsiveContainer
} from 'recharts';
import {
  TrendingUp,
  Clock,
  Users,
  Building2,
  FileText,
  RefreshCw
} from 'lucide-react';
import api from '../../api/axios';
import AdminNavbar from '../../components/navbar/AdminNavbar';
import Card from '../../components/common/Card';
import { SkeletonCard } from '../../components/common/SkeletonLoader';
import Button from '../../components/common/Button';

// API fetch functions
const fetchDailyComplaints = async (days = 30) => {
  const res = await api.get('/admin/analytics/stats-daily-complaints.php', {
    params: { days }
  });
  return res.data;
};

const fetchResolutionTime = async (days = 30) => {
  const res = await api.get('/admin/analytics/stats-resolution-time.php', {
    params: { days }
  });
  return res.data;
};

const fetchStaffPerformance = async (days = 30) => {
  const res = await api.get('/admin/analytics/stats-staff-performance.php', {
    params: { days }
  });
  return res.data;
};

const fetchDepartmentLoad = async () => {
  const res = await api.get('/admin/analytics/stats-department-load.php');
  return res.data;
};

const fetchStatusBreakdown = async () => {
  const res = await api.get('/admin/analytics/stats-status-breakdown.php');
  return res.data;
};

const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#06b6d4', '#84cc16', '#f97316', '#6366f1'];

const AdminAnalytics = () => {
  const [timeRange, setTimeRange] = useState(30);

  // Fetch all analytics data with auto-refresh every 1 minute
  const { data: dailyData, isLoading: dailyLoading, refetch: refetchDaily } = useQuery({
    queryKey: ['analytics-daily', timeRange],
    queryFn: () => fetchDailyComplaints(timeRange),
    refetchInterval: 60000, // Auto-refresh every 1 minute
  });

  const { data: resolutionData, isLoading: resolutionLoading, refetch: refetchResolution } = useQuery({
    queryKey: ['analytics-resolution', timeRange],
    queryFn: () => fetchResolutionTime(timeRange),
    refetchInterval: 60000,
  });

  const { data: staffData, isLoading: staffLoading, refetch: refetchStaff } = useQuery({
    queryKey: ['analytics-staff', timeRange],
    queryFn: () => fetchStaffPerformance(timeRange),
    refetchInterval: 60000,
  });

  const { data: departmentData, isLoading: departmentLoading, refetch: refetchDepartment } = useQuery({
    queryKey: ['analytics-department'],
    queryFn: fetchDepartmentLoad,
    refetchInterval: 60000,
  });

  const { data: statusData, isLoading: statusLoading, refetch: refetchStatus } = useQuery({
    queryKey: ['analytics-status'],
    queryFn: fetchStatusBreakdown,
    refetchInterval: 60000,
  });

  const handleRefresh = () => {
    refetchDaily();
    refetchResolution();
    refetchStaff();
    refetchDepartment();
    refetchStatus();
  };

  const isLoading = dailyLoading || resolutionLoading || staffLoading || departmentLoading || statusLoading;

  const dailyChartData = dailyData?.data?.data || [];
  const resolutionChartData = resolutionData?.data?.data || [];
  const staffChartData = staffData?.data?.data || [];
  const departmentChartData = departmentData?.data?.data || [];
  const statusChartData = statusData?.data?.data || [];

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <AdminNavbar />
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Header */}
        <div className="mb-8 flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
              Analytics Dashboard
            </h1>
            <p className="text-gray-600 dark:text-gray-400">
              Comprehensive insights into complaints, performance, and system metrics
            </p>
          </div>
          <div className="flex items-center gap-4">
            <select
              value={timeRange}
              onChange={(e) => setTimeRange(Number(e.target.value))}
              className="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            >
              <option value={7}>Last 7 days</option>
              <option value={30}>Last 30 days</option>
              <option value={90}>Last 90 days</option>
              <option value={365}>Last year</option>
            </select>
            <Button
              variant="outline"
              onClick={handleRefresh}
              disabled={isLoading}
            >
              <RefreshCw className={`w-4 h-4 mr-2 ${isLoading ? 'animate-spin' : ''}`} />
              Refresh
            </Button>
          </div>
        </div>

        {/* Charts Grid */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
          {/* Daily Complaints Line Chart */}
          <Card className="p-6">
            <div className="flex items-center gap-3 mb-4">
              <TrendingUp className="w-5 h-5 text-primary-600 dark:text-primary-400" />
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
                Daily Complaints
              </h2>
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
              Number of complaints submitted per day
            </p>
            {dailyLoading ? (
              <div className="h-64 flex items-center justify-center">
                <div className="animate-pulse text-gray-400">Loading chart...</div>
              </div>
            ) : dailyChartData.length === 0 ? (
              <div className="h-64 flex items-center justify-center text-gray-400">
                No data available
              </div>
            ) : (
              <ResponsiveContainer width="100%" height={300}>
                <LineChart data={dailyChartData}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-gray-300 dark:stroke-gray-700" />
                  <XAxis 
                    dataKey="name" 
                    className="text-xs"
                    stroke="#6b7280"
                  />
                  <YAxis 
                    stroke="#6b7280"
                  />
                  <Tooltip 
                    contentStyle={{
                      backgroundColor: 'rgba(255, 255, 255, 0.95)',
                      border: '1px solid #e5e7eb',
                      borderRadius: '8px'
                    }}
                  />
                  <Legend />
                  <Line 
                    type="monotone" 
                    dataKey="value" 
                    stroke="#3b82f6" 
                    strokeWidth={2}
                    dot={{ fill: '#3b82f6', r: 4 }}
                    name="Complaints"
                  />
                </LineChart>
              </ResponsiveContainer>
            )}
          </Card>

          {/* Resolution Time Bar Chart */}
          <Card className="p-6">
            <div className="flex items-center gap-3 mb-4">
              <Clock className="w-5 h-5 text-primary-600 dark:text-primary-400" />
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
                Average Resolution Time
              </h2>
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
              Average hours to resolve by category
            </p>
            {resolutionLoading ? (
              <div className="h-64 flex items-center justify-center">
                <div className="animate-pulse text-gray-400">Loading chart...</div>
              </div>
            ) : resolutionChartData.length === 0 ? (
              <div className="h-64 flex items-center justify-center text-gray-400">
                No data available
              </div>
            ) : (
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={resolutionChartData}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-gray-300 dark:stroke-gray-700" />
                  <XAxis 
                    dataKey="name" 
                    angle={-45}
                    textAnchor="end"
                    height={100}
                    className="text-xs"
                    stroke="#6b7280"
                  />
                  <YAxis 
                    stroke="#6b7280"
                    label={{ value: 'Hours', angle: -90, position: 'insideLeft' }}
                  />
                  <Tooltip 
                    contentStyle={{
                      backgroundColor: 'rgba(255, 255, 255, 0.95)',
                      border: '1px solid #e5e7eb',
                      borderRadius: '8px'
                    }}
                  />
                  <Legend />
                  <Bar dataKey="value" fill="#10b981" name="Avg Hours" />
                </BarChart>
              </ResponsiveContainer>
            )}
          </Card>

          {/* Staff Performance Bar Chart */}
          <Card className="p-6">
            <div className="flex items-center gap-3 mb-4">
              <Users className="w-5 h-5 text-primary-600 dark:text-primary-400" />
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
                Staff Performance
              </h2>
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
              Number of resolved complaints per staff member
            </p>
            {staffLoading ? (
              <div className="h-64 flex items-center justify-center">
                <div className="animate-pulse text-gray-400">Loading chart...</div>
              </div>
            ) : staffChartData.length === 0 ? (
              <div className="h-64 flex items-center justify-center text-gray-400">
                No data available
              </div>
            ) : (
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={staffChartData}>
                  <CartesianGrid strokeDasharray="3 3" className="stroke-gray-300 dark:stroke-gray-700" />
                  <XAxis 
                    dataKey="name" 
                    angle={-45}
                    textAnchor="end"
                    height={100}
                    className="text-xs"
                    stroke="#6b7280"
                  />
                  <YAxis 
                    stroke="#6b7280"
                  />
                  <Tooltip 
                    contentStyle={{
                      backgroundColor: 'rgba(255, 255, 255, 0.95)',
                      border: '1px solid #e5e7eb',
                      borderRadius: '8px'
                    }}
                  />
                  <Legend />
                  <Bar dataKey="value" fill="#8b5cf6" name="Resolved" />
                  <Bar dataKey="active" fill="#f59e0b" name="Active" />
                </BarChart>
              </ResponsiveContainer>
            )}
          </Card>

          {/* Department Load Pie Chart */}
          <Card className="p-6">
            <div className="flex items-center gap-3 mb-4">
              <Building2 className="w-5 h-5 text-primary-600 dark:text-primary-400" />
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
                Department Load
              </h2>
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
              Pending complaints distribution by department
            </p>
            {departmentLoading ? (
              <div className="h-64 flex items-center justify-center">
                <div className="animate-pulse text-gray-400">Loading chart...</div>
              </div>
            ) : departmentChartData.length === 0 ? (
              <div className="h-64 flex items-center justify-center text-gray-400">
                No data available
              </div>
            ) : (
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={departmentChartData}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                    outerRadius={100}
                    fill="#8884d8"
                    dataKey="value"
                  >
                    {departmentChartData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.color || COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip 
                    contentStyle={{
                      backgroundColor: 'rgba(255, 255, 255, 0.95)',
                      border: '1px solid #e5e7eb',
                      borderRadius: '8px'
                    }}
                  />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            )}
          </Card>

          {/* Status Breakdown Pie Chart */}
          <Card className="p-6 lg:col-span-2">
            <div className="flex items-center gap-3 mb-4">
              <FileText className="w-5 h-5 text-primary-600 dark:text-primary-400" />
              <h2 className="text-xl font-semibold text-gray-900 dark:text-white">
                Status Breakdown
              </h2>
            </div>
            <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
              Distribution of complaints by status (Pending/Resolved/Rejected)
            </p>
            {statusLoading ? (
              <div className="h-64 flex items-center justify-center">
                <div className="animate-pulse text-gray-400">Loading chart...</div>
              </div>
            ) : statusChartData.length === 0 ? (
              <div className="h-64 flex items-center justify-center text-gray-400">
                No data available
              </div>
            ) : (
              <ResponsiveContainer width="100%" height={350}>
                <PieChart>
                  <Pie
                    data={statusChartData}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ name, value, percent }) => `${name}: ${value} (${(percent * 100).toFixed(1)}%)`}
                    outerRadius={120}
                    fill="#8884d8"
                    dataKey="value"
                  >
                    {statusChartData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.color || COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip 
                    contentStyle={{
                      backgroundColor: 'rgba(255, 255, 255, 0.95)',
                      border: '1px solid #e5e7eb',
                      borderRadius: '8px'
                    }}
                  />
                  <Legend />
                </PieChart>
              </ResponsiveContainer>
            )}
          </Card>
        </div>
      </main>
    </div>
  );
};

export default AdminAnalytics;

