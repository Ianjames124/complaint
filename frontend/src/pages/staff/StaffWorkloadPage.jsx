import React from 'react';
import { useQuery } from '@tanstack/react-query';
import { AlertTriangle, Clock, CheckCircle, XCircle } from 'lucide-react';
import api from '../../api/axios';
import StaffNavbar from '../../components/navbar/StaffNavbar';
import Card from '../../components/common/Card';
import SLAWarningBanner from '../../components/staff/SLAWarningBanner';
import PriorityBadge from '../../components/common/PriorityBadge';
import SLAStatusBadge from '../../components/common/SLAStatusBadge';
import { SkeletonCard } from '../../components/common/SkeletonLoader';
import { useAuth } from '../../context/AuthContext';

const fetchStaffWorkload = async (staffId) => {
  const res = await api.get('/admin/staff-workload.php', {
    params: { staff_id: staffId }
  });
  return res.data;
};

const fetchAssignedComplaints = async () => {
  const res = await api.get('/staff/assigned-complaints.php');
  return res.data;
};

const StaffWorkloadPage = () => {
  const { user } = useAuth();
  const staffId = user?.id;

  const { data: workloadData, isLoading: workloadLoading } = useQuery({
    queryKey: ['staff-workload', staffId],
    queryFn: () => fetchStaffWorkload(staffId),
    enabled: !!staffId
  });

  const { data: complaintsData, isLoading: complaintsLoading } = useQuery({
    queryKey: ['staff-assigned-complaints'],
    queryFn: fetchAssignedComplaints
  });

  const workload = workloadData?.data?.workloads?.[0];
  const complaints = complaintsData?.data?.complaints || [];

  const emergencyComplaints = complaints.filter(c => c.priority_level === 'Emergency');
  const breachedSLA = complaints.filter(c => c.sla_status === 'Breached');
  const warningSLA = complaints.filter(c => c.sla_status === 'Warning');

  if (workloadLoading || complaintsLoading) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
        <StaffNavbar />
        <div className="container mx-auto px-4 py-8">
          <SkeletonCard />
        </div>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <StaffNavbar />
      <div className="container mx-auto px-4 py-8">
        <div className="mb-6">
          <h1 className="text-3xl font-bold text-gray-900 dark:text-white mb-2">
            My Workload
          </h1>
          <p className="text-gray-600 dark:text-gray-400">
            Overview of your assigned complaints and performance
          </p>
        </div>

        {/* SLA Warnings */}
        {(breachedSLA.length > 0 || warningSLA.length > 0) && (
          <SLAWarningBanner 
            breachedCount={breachedSLA.length}
            warningCount={warningSLA.length}
          />
        )}

        {/* Stats Cards */}
        <div className="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
          <Card>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">Active Cases</p>
                <p className="text-2xl font-bold text-gray-900 dark:text-white">
                  {workload?.active_cases || 0}
                </p>
              </div>
              <Clock className="w-8 h-8 text-primary-500" />
            </div>
          </Card>
          <Card>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">Emergency</p>
                <p className="text-2xl font-bold text-red-600 dark:text-red-400">
                  {emergencyComplaints.length}
                </p>
              </div>
              <AlertTriangle className="w-8 h-8 text-red-500" />
            </div>
          </Card>
          <Card>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">Completed</p>
                <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                  {workload?.completed_cases || 0}
                </p>
              </div>
              <CheckCircle className="w-8 h-8 text-green-500" />
            </div>
          </Card>
          <Card>
            <div className="flex items-center justify-between">
              <div>
                <p className="text-sm text-gray-600 dark:text-gray-400">SLA Breached</p>
                <p className="text-2xl font-bold text-red-600 dark:text-red-400">
                  {breachedSLA.length}
                </p>
              </div>
              <XCircle className="w-8 h-8 text-red-500" />
            </div>
          </Card>
        </div>

        {/* Assigned Complaints */}
        <Card>
          <div className="p-6">
            <h2 className="text-xl font-semibold text-gray-900 dark:text-white mb-4">
              My Assigned Complaints
            </h2>

            {complaints.length === 0 ? (
              <div className="text-center py-12">
                <CheckCircle className="w-16 h-16 text-green-500 mx-auto mb-4" />
                <p className="text-gray-600 dark:text-gray-400">No assigned complaints</p>
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
                        Status
                      </th>
                      <th className="text-left py-3 px-4 text-sm font-semibold text-gray-700 dark:text-gray-300">
                        Assigned
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
                            {complaint.status}
                          </td>
                          <td className="py-3 px-4 text-sm text-gray-600 dark:text-gray-400">
                            {new Date(complaint.assigned_at).toLocaleDateString()}
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
      </div>
    </div>
  );
};

export default StaffWorkloadPage;

