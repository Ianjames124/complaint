import React, { useEffect } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { 
  FileText, 
  Clock, 
  CheckCircle, 
  TrendingUp
} from 'lucide-react';
import { PieChart, Pie, Cell, ResponsiveContainer, Tooltip } from 'recharts';
import api from '../../api/axios';
import CitizenNavbar from '../../components/navbar/CitizenNavbar';
import Card from '../../components/common/Card';
import { SkeletonCard } from '../../components/common/SkeletonLoader';
import { useSocket } from '../../context/SocketContext';

const fetchMyComplaints = async () => {
  const res = await api.get('/citizen/my-complaints.php');
  return res.data;
};

const CitizenDashboard = () => {
  const queryClient = useQueryClient();
  const { socket, isConnected } = useSocket();

  const { data, isLoading } = useQuery({
    queryKey: ['citizen-complaints'],
    queryFn: fetchMyComplaints,
  });

  // Real-time updates
  useEffect(() => {
    if (!socket || !isConnected) return;

    // Listen for status updates
    const handleStatusUpdate = (data) => {
      // Invalidate and refetch complaints
      queryClient.invalidateQueries(['citizen-complaints']);
    };

    socket.on('complaint_status_updated', handleStatusUpdate);

    return () => {
      socket.off('complaint_status_updated', handleStatusUpdate);
    };
  }, [socket, isConnected, queryClient]);

  const complaints = data?.data?.complaints || [];

  const stats = {
    total: complaints.length,
    pending: complaints.filter((c) => c.status === 'Pending').length,
    inProgress: complaints.filter((c) => c.status === 'In Progress').length,
    completed: complaints.filter((c) => c.status === 'Completed').length,
  };

  // Chart data
  const statusData = [
    { name: 'Pending', value: stats.pending, color: '#f59e0b' },
    { name: 'In Progress', value: stats.inProgress, color: '#3b82f6' },
    { name: 'Completed', value: stats.completed, color: '#10b981' },
  ].filter(item => item.value > 0);

  // Recent complaints
  const recentComplaints = complaints.slice(0, 5);

  const statCards = [
    {
      title: 'Total Complaints',
      value: stats.total,
      icon: <FileText className="w-6 h-6" />,
      color: 'text-blue-600 dark:text-blue-400',
      bgColor: 'bg-blue-50 dark:bg-blue-900/20',
    },
    {
      title: 'Pending',
      value: stats.pending,
      icon: <Clock className="w-6 h-6" />,
      color: 'text-amber-600 dark:text-amber-400',
      bgColor: 'bg-amber-50 dark:bg-amber-900/20',
    },
    {
      title: 'In Progress',
      value: stats.inProgress,
      icon: <TrendingUp className="w-6 h-6" />,
      color: 'text-indigo-600 dark:text-indigo-400',
      bgColor: 'bg-indigo-50 dark:bg-indigo-900/20',
    },
    {
      title: 'Completed',
      value: stats.completed,
      icon: <CheckCircle className="w-6 h-6" />,
      color: 'text-emerald-600 dark:text-emerald-400',
      bgColor: 'bg-emerald-50 dark:bg-emerald-900/20',
    },
  ];

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <CitizenNavbar />
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="mb-8">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-2">
            Citizen Dashboard
          </h1>
          <p className="text-gray-600 dark:text-gray-400">
            Overview of your complaints and requests
          </p>
        </div>

        {isLoading ? (
          <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
            {[1, 2, 3, 4].map((i) => (
              <SkeletonCard key={i} />
            ))}
          </div>
        ) : (
          <>
            {/* Stats Cards */}
            <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4 mb-8">
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

            {/* Chart and Recent Complaints */}
            <div className="grid gap-6 lg:grid-cols-3 mb-8">
              {/* Status Chart */}
              {statusData.length > 0 && (
                <Card className="lg:col-span-1">
                  <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
                    Status Distribution
                  </h2>
                  <ResponsiveContainer width="100%" height={250}>
                    <PieChart>
                      <Pie
                        data={statusData}
                        cx="50%"
                        cy="50%"
                        labelLine={false}
                        label={({ name, percent }) => `${name}: ${(percent * 100).toFixed(0)}%`}
                        outerRadius={70}
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
              )}

              {/* Recent Complaints */}
              <Card className={statusData.length > 0 ? 'lg:col-span-2' : 'lg:col-span-3'}>
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
                        recentComplaints.map((c) => (
                          <tr key={c.id} className="table-row">
                            <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                              {c.title || c.subject}
                            </td>
                            <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                              {c.category}
                            </td>
                            <td className="px-4 py-3">
                              <span
                                className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                  c.status === 'Pending'
                                    ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                                    : c.status === 'Completed'
                                    ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                                    : c.status === 'In Progress'
                                    ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300'
                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                }`}
                              >
                                {c.status}
                              </span>
                            </td>
                            <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                              {c.created_at ? new Date(c.created_at).toLocaleDateString() : '-'}
                            </td>
                          </tr>
                        ))
                      ) : (
                        <tr>
                          <td colSpan={4} className="px-4 py-8 text-center text-gray-500 dark:text-gray-400">
                            No complaints yet. Submit your first complaint to get started.
                          </td>
                        </tr>
                      )}
                    </tbody>
                  </table>
                </div>
              </Card>
            </div>
          </>
        )}
      </main>
    </div>
  );
};

export default CitizenDashboard;
