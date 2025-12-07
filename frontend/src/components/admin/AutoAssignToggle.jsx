import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '../../api/axios';
import toast from 'react-hot-toast';
import { Settings } from 'lucide-react';

const AutoAssignToggle = () => {
  const queryClient = useQueryClient();
  const [assignmentMethod, setAssignmentMethod] = useState('workload');

  const { data: settings, isLoading } = useQuery({
    queryKey: ['auto-assign-settings'],
    queryFn: async () => {
      const res = await api.get('/admin/auto-assign-settings.php');
      return res.data.data.settings;
    }
  });

  const updateSettingsMutation = useMutation({
    mutationFn: async (newSettings) => {
      const res = await api.post('/admin/auto-assign-settings.php', newSettings);
      return res.data;
    },
    onSuccess: () => {
      queryClient.invalidateQueries(['auto-assign-settings']);
      toast.success('Auto-assign settings updated');
    },
    onError: () => {
      toast.error('Failed to update settings');
    }
  });

  React.useEffect(() => {
    if (settings) {
      setAssignmentMethod(settings.assignment_method || 'workload');
    }
  }, [settings]);

  const enabled = settings?.auto_assign_enabled === '1';

  const handleToggle = (newEnabled) => {
    updateSettingsMutation.mutate({
      auto_assign_enabled: newEnabled ? '1' : '0',
      assignment_method: assignmentMethod
    });
  };

  const handleMethodChange = (method) => {
    setAssignmentMethod(method);
    updateSettingsMutation.mutate({
      auto_assign_enabled: enabled ? '1' : '0',
      assignment_method: method
    });
  };

  if (isLoading) {
    return (
      <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-4 animate-pulse">
        <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/3"></div>
      </div>
    );
  }

  return (
    <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
      <div className="flex items-center gap-3 mb-4">
        <Settings className="w-5 h-5 text-gray-600 dark:text-gray-400" />
        <h3 className="text-lg font-semibold text-gray-900 dark:text-white">
          Auto-Assignment Settings
        </h3>
      </div>

      <div className="space-y-4">
        <div className="flex items-center justify-between">
          <div>
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300">
              Enable Auto-Assignment
            </label>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
              Automatically assign new complaints to staff
            </p>
          </div>
          <button
            type="button"
            onClick={() => handleToggle(!enabled)}
            className={`${
              enabled ? 'bg-primary-600' : 'bg-gray-200 dark:bg-gray-700'
            } relative inline-flex h-6 w-11 items-center rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2`}
          >
            <span
              className={`${
                enabled ? 'translate-x-6' : 'translate-x-1'
              } inline-block h-4 w-4 transform rounded-full bg-white transition-transform`}
            />
          </button>
        </div>

        {enabled && (
          <div className="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
            <label className="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2 block">
              Assignment Method
            </label>
            <div className="flex gap-3">
              <button
                onClick={() => handleMethodChange('workload')}
                className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                  assignmentMethod === 'workload'
                    ? 'bg-primary-600 text-white'
                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                }`}
              >
                Lowest Workload
              </button>
              <button
                onClick={() => handleMethodChange('round_robin')}
                className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                  assignmentMethod === 'round_robin'
                    ? 'bg-primary-600 text-white'
                    : 'bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-200 dark:hover:bg-gray-600'
                }`}
              >
                Round Robin
              </button>
            </div>
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
              {assignmentMethod === 'workload'
                ? 'Assigns to staff with the fewest active cases'
                : 'Assigns in rotation to distribute evenly'}
            </p>
          </div>
        )}
      </div>
    </div>
  );
};

export default AutoAssignToggle;

