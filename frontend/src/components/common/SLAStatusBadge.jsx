import React from 'react';
import { Clock, AlertTriangle, XCircle } from 'lucide-react';

const SLAStatusBadge = ({ status, hoursRemaining, size = 'md' }) => {
  const statusConfig = {
    'On Time': {
      label: 'On Time',
      color: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
      icon: Clock,
      iconColor: 'text-green-600 dark:text-green-400'
    },
    'Warning': {
      label: 'Warning',
      color: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
      icon: AlertTriangle,
      iconColor: 'text-yellow-600 dark:text-yellow-400'
    },
    'Breached': {
      label: 'Breached',
      color: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
      icon: XCircle,
      iconColor: 'text-red-600 dark:text-red-400'
    }
  };

  const config = statusConfig[status] || statusConfig['On Time'];
  const Icon = config.icon;
  const sizeClasses = {
    sm: 'text-xs px-2 py-0.5',
    md: 'text-sm px-2.5 py-1',
    lg: 'text-base px-3 py-1.5'
  };

  const formatTime = (hours) => {
    if (hours < 0) return 'Overdue';
    if (hours < 1) return `${Math.round(hours * 60)}m`;
    if (hours < 24) return `${Math.round(hours)}h`;
    return `${Math.round(hours / 24)}d`;
  };

  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full font-semibold ${config.color} ${sizeClasses[size]}`}
      title={hoursRemaining !== undefined ? `${formatTime(hoursRemaining)} remaining` : status}
    >
      <Icon className={`w-3.5 h-3.5 ${config.iconColor}`} />
      <span>{config.label}</span>
      {hoursRemaining !== undefined && (
        <span className="text-xs opacity-75">({formatTime(hoursRemaining)})</span>
      )}
    </span>
  );
};

export default SLAStatusBadge;

