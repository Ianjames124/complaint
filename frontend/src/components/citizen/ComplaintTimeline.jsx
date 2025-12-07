import React from 'react';
import { CheckCircle, Clock, XCircle, AlertCircle } from 'lucide-react';

const ComplaintTimeline = ({ status, statusUpdates = [] }) => {
  const statusSteps = [
    { key: 'Pending', label: 'Submitted', icon: Clock, color: 'amber' },
    { key: 'Assigned', label: 'Assigned', icon: AlertCircle, color: 'blue' },
    { key: 'In Progress', label: 'In Progress', icon: Clock, color: 'indigo' },
    { key: 'Completed', label: 'Resolved', icon: CheckCircle, color: 'emerald' },
    { key: 'Closed', label: 'Closed', icon: XCircle, color: 'gray' },
  ];

  // Find current status index
  const currentIndex = statusSteps.findIndex(step => step.key === status);
  const activeIndex = currentIndex >= 0 ? currentIndex : 0;

  return (
    <div className="relative">
      <div className="space-y-4">
        {statusSteps.map((step, index) => {
          const Icon = step.icon;
          const isActive = index <= activeIndex;
          const isCurrent = index === activeIndex;
          
          return (
            <div key={step.key} className="flex items-start gap-4">
              {/* Icon */}
              <div className={`flex-shrink-0 w-10 h-10 rounded-full flex items-center justify-center transition-all duration-300 ${
                isActive
                  ? step.color === 'amber' 
                    ? 'bg-amber-100 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400'
                    : step.color === 'blue'
                    ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400'
                    : step.color === 'indigo'
                    ? 'bg-indigo-100 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400'
                    : step.color === 'emerald'
                    ? 'bg-emerald-100 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400'
                    : 'bg-gray-100 dark:bg-gray-700 text-gray-400'
                  : 'bg-gray-100 dark:bg-gray-700 text-gray-400'
              }`}>
                <Icon className={`w-5 h-5 ${isActive ? 'animate-pulse' : ''}`} />
              </div>
              
              {/* Content */}
              <div className="flex-1 pb-8">
                <div className={`flex items-center gap-2 mb-1 ${
                  isActive ? 'text-gray-900 dark:text-gray-100' : 'text-gray-500 dark:text-gray-400'
                }`}>
                  <h3 className={`font-semibold ${isCurrent ? 'text-lg' : 'text-base'}`}>
                    {step.label}
                  </h3>
                  {isCurrent && (
                    <span className={`px-2 py-0.5 text-xs font-semibold rounded-full bg-${step.color}-100 dark:bg-${step.color}-900/30 text-${step.color}-700 dark:text-${step.color}-300`}>
                      Current
                    </span>
                  )}
                </div>
                
                {/* Status update details */}
                {isActive && statusUpdates.length > 0 && (
                  <div className="mt-2 space-y-1">
                    {statusUpdates
                      .filter(update => update.status === step.key)
                      .map((update, idx) => (
                        <div key={idx} className="text-sm text-gray-600 dark:text-gray-400">
                          {update.notes && (
                            <p className="italic">"{update.notes}"</p>
                          )}
                          {update.created_at && (
                            <p className="text-xs text-gray-500 dark:text-gray-500">
                              {new Date(update.created_at).toLocaleString()}
                              {update.updated_by_name && ` by ${update.updated_by_name}`}
                            </p>
                          )}
                        </div>
                      ))}
                  </div>
                )}
              </div>
              
              {/* Connector Line */}
              {index < statusSteps.length - 1 && (
                <div className={`absolute left-5 w-0.5 h-full ${
                  isActive
                    ? step.color === 'amber'
                      ? 'bg-amber-300 dark:bg-amber-700'
                      : step.color === 'blue'
                      ? 'bg-blue-300 dark:bg-blue-700'
                      : step.color === 'indigo'
                      ? 'bg-indigo-300 dark:bg-indigo-700'
                      : step.color === 'emerald'
                      ? 'bg-emerald-300 dark:bg-emerald-700'
                      : 'bg-gray-200 dark:bg-gray-700'
                    : 'bg-gray-200 dark:bg-gray-700'
                }`} style={{ marginTop: '2.5rem', height: 'calc(100% - 2.5rem)' }} />
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
};

export default ComplaintTimeline;

