import React, { useState, useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import PriorityBadge from './PriorityBadge';

const PrioritySelector = ({ value, onChange, className = '' }) => {
  const { data: priorityData, isLoading } = useQuery({
    queryKey: ['priority-levels'],
    queryFn: async () => {
      const res = await api.get('/admin/get-priority-levels.php');
      return res.data.data.priority_levels;
    }
  });

  const priorityLevels = priorityData || [
    { value: 'Low', label: 'Low Priority', sla_hours: 72 },
    { value: 'Medium', label: 'Medium Priority', sla_hours: 48 },
    { value: 'High', label: 'High Priority', sla_hours: 24 },
    { value: 'Emergency', label: 'Emergency 🚨', sla_hours: 4 }
  ];

  if (isLoading) {
    return (
      <div className={`animate-pulse h-10 bg-gray-200 dark:bg-gray-700 rounded ${className}`}></div>
    );
  }

  return (
    <div className={className}>
      <label className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
        Priority Level
      </label>
      <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
        {priorityLevels.map((priority) => (
          <button
            key={priority.value}
            type="button"
            onClick={() => onChange(priority.value)}
            className={`p-3 rounded-lg border-2 transition-all ${
              value === priority.value
                ? 'border-primary-500 bg-primary-50 dark:bg-primary-900/20'
                : 'border-gray-200 dark:border-gray-700 hover:border-gray-300 dark:hover:border-gray-600'
            }`}
          >
            <PriorityBadge priority={priority.value} size="sm" />
            <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">
              SLA: {priority.sla_hours}h
            </p>
          </button>
        ))}
      </div>
    </div>
  );
};

export default PrioritySelector;

