import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { 
  Users, 
  UserPlus, 
  RefreshCw, 
  TrendingUp,
  AlertCircle,
  CheckCircle,
  Clock
} from 'lucide-react';
import api from '../../api/axios';
import AdminNavbar from '../../components/navbar/AdminNavbar';
import Card from '../../components/common/Card';
import Button from '../../components/common/Button';
import AutoAssignToggle from '../../components/admin/AutoAssignToggle';
import ManualAssignModal from '../../components/admin/ManualAssignModal';
import PriorityBadge from '../../components/common/PriorityBadge';
import SLAStatusBadge from '../../components/common/SLAStatusBadge';
import { SkeletonCard } from '../../components/common/SkeletonLoader';
import toast from 'react-hot-toast';

const fetchPendingComplaints = async () => {
  const res = await api.get('/admin/complaints.php', {
    params: { status: 'Pending' }
  });
  return res.data;
};

const AdminAssignmentDashboard = () => {
  const queryClient = useQueryClient();
  const [selectedComplaint, setSelectedComplaint] = useState(null);
  const [isAssignModalOpen, setIsAssignModalOpen] = useState(false);

  const { data: complaintsData, isLoading } = useQuery({
    queryKey: ['admin-complaints-pending'],
    queryFn: fetchPendingComplaints
  });

  const autoAssignMutation = useMutation({
    mutationFn: async (complaintId) => {
      const res = await api.post('/admin/auto-assign.php', {
        complaint_id: complaintId
      });
      return res.data;
    },
    onSuccess: (data, complaintId) => {
      queryClient.invalidateQueries(['admin-complaints-pending']);
      toast.success(`Complaint auto-assigned to ${data.data.staff_name}`);
    },
    onError: (error) => {
      toast.error(error.response?.data?.message || 'Failed to auto-assign');
    }
  });

  const handleAutoAssign = (complaintId) => {
    autoAssignMutation.mutate(complaintId);
  };

  const handleManualAssign = (complaint) => {
    setSelectedComplaint(complaint);
    setIsAssignModalOpen(true);
  };

  const complaints = complaintsData?.data?.complaints || [];
  const pendingCount = complaints.length;
  const emergencyCount = complaints.filter(c => c.priority_level === 'Emergency').length;
  const highPriorityCount = complaints.filter(c => c.priority_level === 'High').length;

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <AdminNavbar />
      <div className="container mx-auto px-4 py-8">
        <div className="mb-6">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
            Assignment Dashboard
          </h1>
          <p className="text-gray-600 dark:text-gray-400">
            Manage complaint assignments and auto-assignment settings
          </p>
        </div>

        {/* Stats Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
          <Card>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">Pending</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-white">{pendingCount}</p>
              </div>
              <Clock className="w-8 h-8 text-yellow-500" />
            </div>
          </Card>
          <Card>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">Emergency</p>
                <p className="text-2xl font-bold text-red-600 dark:text-red-400">{emergencyCount}</p>
              </div>
              <AlertCircle className="w-8 h-8 text-red-500" />
            </div>
          </Card>
          <Card>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">High Priority</p>
                <p className="text-2xl font-bold text-orange-600 dark:text-orange-400">{highPriorityCount}</p>
              </div>
              <TrendingUp className="w-8 h-8 text-orange-500" />
            </div>
          </Card>
          <Card>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">Total</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-white">{complaints.length}</p>
              </div>
              <Users className="w-8 h-8 text-primary-500" />
            </div>
          </Card>
        </div>

        {/* Auto-Assign Settings */}
        <div className="mb-6">
          <AutoAssignToggle />
        </div>

        {/* Pending Complaints Table */}
        <Card>
          <div className="p-6">
            <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-4">
              Pending Complaints
            </h2>

            {isLoading ? (
              <div className="space-y-4">
                {[1, 2, 3].map((i) => (
                  <SkeletonCard key={i} />
                ))}
              </div>
            ) : complaints.length === 0 ? (
              <div className="text-center py-12">
                <CheckCircle className="w-16 h-16 text-green-500 mx-auto mb-4" />
                <p className="text-gray-600 dark:text-gray-400">No pending complaints</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full">
                  <thead>
                    <tr className="border-b border-gray-200 dark:border-gray-700">
                      <th className="text-left py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        ID
                      </th>
                      <th className="text-left py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Title
                      </th>
                      <th className="text-left py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Priority
                      </th>
                      <th className="text-left py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        SLA
                      </th>
                      <th className="text-left py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Category
                      </th>
                      <th className="text-left py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Created
                      </th>
                      <th className="text-left py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {complaints.map((complaint) => {
                      const slaDueAt = complaint.sla_due_at ? new Date(complaint.sla_due_at) : null;
                      const hoursRemaining = slaDueAt 
                        ? (slaDueAt - new Date()) / (1000 * 60 * 60)
                        : null;

                      return (
                        <tr
                          key={complaint.id}
                          className="border-b border-gray-100 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800/50"
                        >
                          <td className="py-3 px-4 text-sm text-gray-900 dark:text-white">
                            #{complaint.id}
                          </td>
                          <td className="py-3 px-4 text-sm text-gray-900 dark:text-white">
                            {complaint.title}
                          </td>
                          <td className="py-3 px-4">
                            <PriorityBadge priority={complaint.priority_level || 'Medium'} size="sm" />
                          </td>
                          <td className="py-3 px-4">
                            <SLAStatusBadge 
                              status={complaint.sla_status || 'On Time'} 
                              hoursRemaining={hoursRemaining}
                              size="sm"
                            />
                          </td>
                          <td className="py-3 px-4 text-sm text-gray-600 dark:text-gray-400">
                            {complaint.category}
                          </td>
                          <td className="py-3 px-4 text-sm text-gray-600 dark:text-gray-400">
                            {new Date(complaint.created_at).toLocaleDateString()}
                          </td>
                          <td className="py-3 px-4">
                            <div className="flex gap-2">
                              <Button
                                size="sm"
                                variant="primary"
                                onClick={() => handleAutoAssign(complaint.id)}
                                disabled={autoAssignMutation.isLoading}
                              >
                                <RefreshCw className="w-4 h-4 mr-1" />
                                Auto
                              </Button>
                              <Button
                                size="sm"
                                variant="outline"
                                onClick={() => handleManualAssign(complaint)}
                              >
                                <UserPlus className="w-4 h-4 mr-1" />
                                Manual
                              </Button>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </Card>

        {/* Manual Assign Modal */}
        {selectedComplaint && (
          <ManualAssignModal
            isOpen={isAssignModalOpen}
            onClose={() => {
              setIsAssignModalOpen(false);
              setSelectedComplaint(null);
            }}
            complaintId={selectedComplaint.id}
            currentStaffId={selectedComplaint.staff_id}
            departmentId={selectedComplaint.department_id}
          />
        )}
      </div>
    </div>
  );
};

export default AdminAssignmentDashboard;

