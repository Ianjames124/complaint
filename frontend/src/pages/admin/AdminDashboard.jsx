import React, { useEffect } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { 
  FileText, 
  Clock, 
  CheckCircle, 
  Users, 
  TrendingUp,
  AlertCircle
} from 'lucide-react';
import { PieChart, Pie, Cell, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip, Legend, ResponsiveContainer } from 'recharts';
import api from '../../api/axios';
import AdminNavbar from '../../components/navbar/AdminNavbar';
import Card from '../../components/common/Card';
import { SkeletonCard } from '../../components/common/SkeletonLoader';
import { useSocket } from '../../context/SocketContext';

const fetchComplaints = async () => {
  const res = await api.get('/admin/complaints.php');
  return res.data;
};

const fetchRequests = async () => {
  const res = await api.get('/admin/requests.php');
  return res.data;
};

const fetchStaff = async () => {
  const res = await api.get('/admin/staff-list.php');
  return res.data;
};

const AdminDashboard = () => {
  const queryClient = useQueryClient();
  const { socket, isConnected } = useSocket();

  const { data: complaintsData, isLoading: complaintsLoading } = useQuery({
    queryKey: ['admin-complaints'],
    queryFn: fetchComplaints,
  });

  const { data: requestsData, isLoading: requestsLoading } = useQuery({
    queryKey: ['admin-requests'],
    queryFn: fetchRequests,
  });

  const { data: staffData, isLoading: staffLoading } = useQuery({
    queryKey: ['admin-staff-list'],
    queryFn: fetchStaff,
  });

  // Real-time updates
  useEffect(() => {
    if (!socket || !isConnected) return;

    // Listen for new complaints
    const handleNewComplaint = (data) => {
      // Invalidate and refetch complaints
      queryClient.invalidateQueries(['admin-complaints']);
    };

    // Listen for status updates
    const handleStatusUpdate = (data) => {
      // Invalidate queries to refresh data
      queryClient.invalidateQueries(['admin-complaints']);
      queryClient.invalidateQueries(['admin-requests']);
    };

    socket.on('new_complaint', handleNewComplaint);
    socket.on('complaint_status_updated', handleStatusUpdate);

    return () => {
      socket.off('new_complaint', handleNewComplaint);
      socket.off('complaint_status_updated', handleStatusUpdate);
    };
  }, [socket, isConnected, queryClient]);

  const complaints = complaintsData?.data?.complaints || [];
  const requests = requestsData?.data?.requests || [];
  const staff = staffData?.data?.staff || [];

  const stats = {
    totalComplaints: complaints.length,
    pendingComplaints: complaints.filter((c) => c.status === 'Pending').length,
    assignedComplaints: complaints.filter((c) => c.status === 'Assigned').length,
    inProgressComplaints: complaints.filter((c) => c.status === 'In Progress').length,
    completedComplaints: complaints.filter((c) => c.status === 'Completed').length,
    totalRequests: requests.length,
    pendingRequests: requests.filter((r) => r.status === 'Pending').length,
    totalStaff: staff.length,
  };

  // Chart data
  const statusData = [
    { name: 'Pending', value: stats.pendingComplaints, color: '#f59e0b' },
    { name: 'Assigned', value: stats.assignedComplaints, color: '#3b82f6' },
    { name: 'In Progress', value: stats.inProgressComplaints, color: '#8b5cf6' },
    { name: 'Completed', value: stats.completedComplaints, color: '#10b981' },
  ];

  // Recent complaints for table
  const recentComplaints = complaints.slice(0, 5);

  const isLoading = complaintsLoading || requestsLoading || staffLoading;

  const statCards = [
    {
      title: 'Total Complaints',
      value: stats.totalComplaints,
      icon: <FileText className="w-6 h-6" />,
      color: 'text-blue-600 dark:text-blue-400',
      bgColor: 'bg-blue-50 dark:bg-blue-900/20',
    },
    {
      title: 'Pending',
      value: stats.pendingComplaints,
      icon: <Clock className="w-6 h-6" />,
      color: 'text-amber-600 dark:text-amber-400',
      bgColor: 'bg-amber-50 dark:bg-amber-900/20',
    },
    {
      title: 'In Progress',
      value: stats.inProgressComplaints,
      icon: <TrendingUp className="w-6 h-6" />,
      color: 'text-indigo-600 dark:text-indigo-400',
      bgColor: 'bg-indigo-50 dark:bg-indigo-900/20',
    },
    {
      title: 'Completed',
      value: stats.completedComplaints,
      icon: <CheckCircle className="w-6 h-6" />,
      color: 'text-emerald-600 dark:text-emerald-400',
      bgColor: 'bg-emerald-50 dark:bg-emerald-900/20',
    },
    {
      title: 'Total Requests',
      value: stats.totalRequests,
      icon: <AlertCircle className="w-6 h-6" />,
      color: 'text-purple-600 dark:text-purple-400',
      bgColor: 'bg-purple-50 dark:bg-purple-900/20',
    },
    {
      title: 'Staff Members',
      value: stats.totalStaff,
      icon: <Users className="w-6 h-6" />,
      color: 'text-pink-600 dark:text-pink-400',
      bgColor: 'bg-pink-50 dark:bg-pink-900/20',
    },
  ];

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <AdminNavbar />
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">
            Admin Dashboard
          </h1>
          <p className="text-gray-600 dark:text-gray-400">
            Overview of complaints, requests, and system statistics
          </p>
        </div>

        {isLoading ? (
          <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
            {[1, 2, 3, 4, 5, 6].map((i) => (
              <SkeletonCard key={i} />
            ))}
          </div>
        ) : (
          <>
            {/* Stats Cards */}
            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-3 mb-8">
              {statCards.map((stat, index) => (
                <Card key={index} hover className="p-6">
                  <div className="flex items-center justify-between">
                    <div>
                      <p className="text-sm font-medium text-gray-600 dark:text-gray-400 mb-1">
                        {stat.title}
                      </p>
                      <p className={`text-3xl font-bold ${stat.color}`}>
                        {stat.value}
                      </p>
                    </div>
                    <div className={`p-3 rounded-lg ${stat.bgColor} ${stat.color}`}>
                      {stat.icon}
                    </div>
                  </div>
                </Card>
              ))}
            </div>

            {/* Charts Section */}
            <div className="grid gap-6 lg:grid-cols-2 mb-8">
              {/* Status Distribution Pie Chart */}
              <Card>
                <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                  Complaints by Status
                </h2>
                <ResponsiveContainer width="100%" height={300}>
                  <PieChart>
                    <Pie
                      data={statusData}
                      cx="50%"
                      cy="50%"
                      labelLine={false}
                      label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(0)}%`}
                      outerRadius={80}
                      fill="#8884d8"
                      dataKey="value"
                    >
                      {statusData.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={entry.color} />
                      ))}
                    </Pie>
                    <Tooltip />
                  </PieChart>
                </ResponsiveContainer>
              </Card>

              {/* Status Bar Chart */}
              <Card>
                <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                  Status Overview
                </h2>
                <ResponsiveContainer width="100%" height={300}>
                  <BarChart data={statusData}>
                    <CartesianGrid strokeDasharray="3 3" className="stroke-gray-300 dark:stroke-gray-700" />
                    <XAxis dataKey="name" className="text-gray-600 dark:text-gray-400" />
                    <YAxis className="text-gray-600 dark:text-gray-400" />
                    <Tooltip 
                      contentStyle={{ 
                        backgroundColor: 'var(--tw-bg-white)',
                        border: '1px solid var(--tw-border-gray-300)',
                        borderRadius: '8px'
                      }}
                    />
                    <Bar dataKey="value" radius={[8, 8, 0, 0]}>
                      {statusData.map((entry, index) => (
                        <Cell key={`cell-${index}`} fill={entry.color} />
                      ))}
                    </Bar>
                  </BarChart>
                </ResponsiveContainer>
              </Card>
            </div>

            {/* Recent Complaints Table */}
            <Card>
              <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                Recent Complaints
              </h2>
              <div className="overflow-x-auto">
                <table className="min-w-full">
                  <thead>
                    <tr className="border-b border-gray-200 dark:border-gray-700">
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Title
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Category
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Status
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Created
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                    {recentComplaints.length > 0 ? (
                      recentComplaints.map((complaint) => (
                        <tr key={complaint.id} className="table-row">
                          <td className="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                            {complaint.title}
                          </td>
                          <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            {complaint.category}
                          </td>
                          <td className="px-4 py-3">
                            <span
                              className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                complaint.status === 'Pending'
                                  ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                                  : complaint.status === 'Completed'
                                  ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                                  : complaint.status === 'In Progress'
                                  ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300'
                                  : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                              }`}
                            >
                              {complaint.status}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            {new Date(complaint.created_at).toLocaleDateString()}
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan={4} className="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                          No complaints found
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>
            </Card>
          </>
        )}
      </main>
    </div>
  );
};

export default AdminDashboard;
