import React from 'react';

const PriorityBadge = ({ priority, size = 'md' }) => {
  const priorityConfig = {
    Low: {
      label: 'Low',
      color: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
      icon: '📋'
    },
    Medium: {
      label: 'Medium',
      color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
      icon: '⚠️'
    },
    High: {
      label: 'High',
      color: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
      icon: '🔴'
    },
    Emergency: {
      label: 'Emergency',
      color: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
      icon: '🚨'
    }
  };

  const config = priorityConfig[priority] || priorityConfig.Medium;
  const sizeClasses = {
    sm: 'text-xs px-2 py-0.5',
    md: 'text-sm px-2.5 py-1',
    lg: 'text-base px-3 py-1.5'
  };

  return (
    <span
      className={`inline-flex items-center gap-1 rounded-full font-semibold ${config.color} ${sizeClasses[size]}`}
    >
      <span>{config.icon}</span>
      <span>{config.label}</span>
    </span>
  );
};

export default PriorityBadge;

