import React, { useState, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/axios';
import toast from 'react-hot-toast';
import { X, Users, AlertCircle } from 'lucide-react';
import Modal from '../common/Modal';
import Button from '../common/Button';

const ManualAssignModal = ({ isOpen, onClose, complaintId, currentStaffId, departmentId }) => {
  const queryClient = useQueryClient();
  const [selectedStaffId, setSelectedStaffId] = useState('');
  const [reason, setReason] = useState('');
  const [allowCrossDepartment, setAllowCrossDepartment] = useState(false);

  const { data: staffData, isLoading } = useQuery({
    queryKey: ['department-staff', departmentId, allowCrossDepartment],
    queryFn: async () => {
      const params = allowCrossDepartment ? { include_all: true } : { department_id: departmentId };
      const res = await api.get('/admin/get-department-staff.php', { params });
      return res.data.data.staff;
    },
    enabled: isOpen
  });

  const assignMutation = useMutation({
    mutationFn: async (data) => {
      const res = await api.post('/admin/manual-assign.php', data);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries(['admin-complaints']);
      queryClient.invalidateQueries(['admin-complaints-pending']);
      toast.success('Complaint assigned successfully');
      onClose();
      setSelectedStaffId('');
      setReason('');
    },
    onError: (error) => {
      toast.error(error.response?.data?.message || 'Failed to assign complaint');
    }
  });

  useEffect(() => {
    if (currentStaffId) {
      setSelectedStaffId(currentStaffId.toString());
    }
  }, [currentStaffId]);

  const handleSubmit = (e) => {
    e.preventDefault();
    if (!selectedStaffId) {
      toast.error('Please select a staff member');
      return;
    }

    assignMutation.mutate({
      complaint_id: complaintId,
      staff_id: parseInt(selectedStaffId),
      reason: reason,
      allow_cross_department: allowCrossDepartment
    });
  };

  if (!isOpen) return null;

  return (
    <Modal isOpen={isOpen} onClose={onClose}>
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-2xl w-full max-h-[90vh] overflow-y-auto">
        <div className="sticky top-0 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between">
          <h2 className="text-xl font-semibold text-gray-900 dark:text-white flex items-center gap-2">
            <Users className="w-5 h-5" />
            Manual Assignment
          </h2>
          <button
            onClick={onClose}
            className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-6">
          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Select Staff Member
            </label>
            {isLoading ? (
              <div className="animate-pulse h-10 bg-gray-200 dark:bg-gray-700 rounded"></div>
            ) : (
              <select
                value={selectedStaffId}
                onChange={(e) => setSelectedStaffId(e.target.value)}
                className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                required
              >
                <option value="">-- Select Staff --</option>
                {staffData?.map((staff) => (
                  <option key={staff.id} value={staff.id}>
                    {staff.full_name} ({staff.department_name || 'No Department'}) - {staff.active_cases} active cases
                  </option>
                ))}
              </select>
            )}
          </div>

          {departmentId && (
            <div className="flex items-start gap-2 p-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
              <AlertCircle className="w-5 h-5 text-yellow-600 dark:text-yellow-400 mt-0.5 flex-shrink-0" />
              <div className="flex-1">
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={allowCrossDepartment}
                    onChange={(e) => setAllowCrossDepartment(e.target.checked)}
                    className="rounded border-gray-300 text-primary-600 focus:ring-primary-500"
                  />
                  <span className="text-sm text-gray-700 dark:text-gray-300">
                    Allow cross-department assignment
                  </span>
                </label>
                <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
                  Enable this to assign to staff from other departments
                </p>
              </div>
            </div>
          )}

          <div>
            <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
              Reason for Assignment (Optional)
            </label>
            <textarea
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              rows={3}
              className="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              placeholder="Enter reason for this assignment..."
            />
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
            <Button
              type="button"
              variant="outline"
              onClick={onClose}
              disabled={assignMutation.isLoading}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              variant="primary"
              disabled={assignMutation.isLoading || !selectedStaffId}
            >
              {assignMutation.isLoading ? 'Assigning...' : 'Assign Complaint'}
            </Button>
          </div>
        </form>
      </div>
    </Modal>
  );
};

export default ManualAssignModal;

