import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { 
  BarChart, 
  Bar, 
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  Legend, 
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell,
  LineChart,
  Line
} from 'recharts';
import { TrendingUp, Award, Clock, CheckCircle } from 'lucide-react';
import api from '../../api/axios';
import AdminNavbar from '../../components/navbar/AdminNavbar';
import Card from '../../components/common/Card';
import { SkeletonCard } from '../../components/common/SkeletonLoader';

const fetchStaffPerformance = async (period) => {
  const res = await api.get('/admin/staff-performance.php', {
    params: { period }
  });
  return res.data;
};

const COLORS = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];

const StaffPerformance = () => {
  const [period, setPeriod] = useState('month');
  const [selectedStaff, setSelectedStaff] = useState(null);

  const { data, isLoading } = useQuery({
    queryKey: ['staff-performance', period],
    queryFn: () => fetchStaffPerformance(period)
  });

  const staffList = data?.data?.staff_list || [];
  const staff = selectedStaff || staffList[0];

  // Prepare chart data
  const completionData = staffList.slice(0, 10).map(s => ({
    name: s.staff_name,
    completed: s.completed_count,
    pending: s.pending_count
  }));

  const slaData = staffList.slice(0, 10).map(s => ({
    name: s.staff_name,
    compliance: s.sla_compliance_percentage || 0
  }));

  const statusDistribution = staff ? [
    { name: 'Completed', value: staff.completed_count },
    { name: 'Pending', value: staff.pending_count },
    { name: 'Closed', value: staff.closed_count }
  ].filter(item => item.value > 0) : [];

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
        <AdminNavbar />
        <div className="container mx-auto px-4 py-8">
          <SkeletonCard />
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <AdminNavbar />
      <div className="container mx-auto px-4 py-8">
        <div className="mb-6 flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
              Staff Performance
            </h1>
            <p className="text-gray-600 dark:text-gray-400">
              Track staff performance metrics and statistics
            </p>
          </div>
          <select
            value={period}
            onChange={(e) => setPeriod(e.target.value)}
            className="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
          >
            <option value="week">Last Week</option>
            <option value="month">Last Month</option>
            <option value="year">Last Year</option>
            <option value="all">All Time</option>
          </select>
        </div>

        {/* Staff Selector */}
        {staffList.length > 0 && (
          <Card className="mb-6">
            <div className="p-4">
              <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Select Staff Member
              </label>
              <select
                value={selectedStaff?.staff_id || staffList[0]?.staff_id || ''}
                onChange={(e) => {
                  const staffId = parseInt(e.target.value);
                  setSelectedStaff(staffList.find(s => s.staff_id === staffId) || null);
                }}
                className="w-full md:w-64 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
              >
                {staffList.map((s) => (
                  <option key={s.staff_id} value={s.staff_id}>
                    {s.staff_name} ({s.department_name || 'No Department'})
                  </option>
                ))}
              </select>
            </div>
          </Card>
        )}

        {/* Individual Staff Stats */}
        {staff && (
          <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <Card>
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600 dark:text-gray-400">Total Assigned</p>
                  <p className="text-2xl font-bold text-gray-900 dark:text-white">
                    {staff.total_assigned}
                  </p>
                </div>
                <TrendingUp className="w-8 h-8 text-primary-500" />
              </div>
            </Card>
            <Card>
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600 dark:text-gray-400">Completed</p>
                  <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                    {staff.completed_count}
                  </p>
                </div>
                <CheckCircle className="w-8 h-8 text-green-500" />
              </div>
            </Card>
            <Card>
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600 dark:text-gray-400">Avg Resolution</p>
                  <p className="text-2xl font-bold text-blue-600 dark:text-blue-400">
                    {staff.avg_resolution_hours 
                      ? `${Math.round(staff.avg_resolution_hours)}h`
                      : 'N/A'}
                  </p>
                </div>
                <Clock className="w-8 h-8 text-blue-500" />
              </div>
            </Card>
            <Card>
              <div className="flex items-center justify-between">
                <div>
                  <p className="text-sm text-gray-600 dark:text-gray-400">SLA Compliance</p>
                  <p className="text-2xl font-bold text-purple-600 dark:text-purple-400">
                    {staff.sla_compliance_percentage}%
                  </p>
                </div>
                <Award className="w-8 h-8 text-purple-500" />
              </div>
            </Card>
          </div>
        )}

        {/* Charts */}
        <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
          {/* Completion Chart */}
          <Card>
            <div className="p-6">
              <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Completion Statistics
              </h3>
              <ResponsiveContainer width="100%" height={300}>
                <BarChart data={completionData}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="name" angle={-45} textAnchor="end" height={100} />
                  <YAxis />
                  <Tooltip />
                  <Legend />
                  <Bar dataKey="completed" fill="#10b981" name="Completed" />
                  <Bar dataKey="pending" fill="#f59e0b" name="Pending" />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </Card>

          {/* SLA Compliance Chart */}
          <Card>
            <div className="p-6">
              <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                SLA Compliance
              </h3>
              <ResponsiveContainer width="100%" height={300}>
                <LineChart data={slaData}>
                  <CartesianGrid strokeDasharray="3 3" />
                  <XAxis dataKey="name" angle={-45} textAnchor="end" height={100} />
                  <YAxis domain={[0, 100]} />
                  <Tooltip />
                  <Legend />
                  <Line type="monotone" dataKey="compliance" stroke="#8b5cf6" name="Compliance %" />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </Card>
        </div>

        {/* Status Distribution Pie Chart */}
        {staff && statusDistribution.length > 0 && (
          <Card>
            <div className="p-6">
              <h3 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Status Distribution - {staff.staff_name}
              </h3>
              <ResponsiveContainer width="100%" height={300}>
                <PieChart>
                  <Pie
                    data={statusDistribution}
                    cx="50%"
                    cy="50%"
                    labelLine={false}
                    label={({ name, percent }) => `${name} ${(percent * 100).toFixed(0)}%`}
                    outerRadius={80}
                    fill="#8884d8"
                    dataKey="value"
                  >
                    {statusDistribution.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={COLORS[index % COLORS.length]} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            </div>
          </Card>
        )}
      </div>
    </div>
  );
};

export default StaffPerformance;

