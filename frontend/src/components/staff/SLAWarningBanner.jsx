import React from 'react';
import { AlertTriangle, XCircle } from 'lucide-react';

const SLAWarningBanner = ({ breachedCount, warningCount }) => {
  if (breachedCount === 0 && warningCount === 0) {
    return null;
  }

  return (
    <div className="mb-6 space-y-3">
      {breachedCount > 0 && (
        <div className="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-4 rounded-lg">
          <div className="flex items-center gap-3">
            <XCircle className="w-6 h-6 text-red-600 dark:text-red-400 flex-shrink-0" />
            <div>
              <h3 className="text-sm font-semibold text-red-800 dark:text-red-200">
                SLA Breached: {breachedCount} {breachedCount === 1 ? 'complaint' : 'complaints'}
              </h3>
              <p className="text-sm text-red-700 dark:text-red-300 mt-1">
                These complaints have exceeded their SLA deadline. Please prioritize them immediately.
              </p>
            </div>
          </div>
        </div>
      )}

      {warningCount > 0 && (
        <div className="bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-500 p-4 rounded-lg">
          <div className="flex items-center gap-3">
            <AlertTriangle className="w-6 h-6 text-yellow-600 dark:text-yellow-400 flex-shrink-0" />
            <div>
              <h3 className="text-sm font-semibold text-yellow-800 dark:text-yellow-200">
                SLA Warning: {warningCount} {warningCount === 1 ? 'complaint' : 'complaints'}
              </h3>
              <p className="text-sm text-yellow-700 dark:text-yellow-300 mt-1">
                These complaints are approaching their SLA deadline. Please review them soon.
              </p>
            </div>
          </div>
        </div>
      )}
    </div>
  );
};

export default SLAWarningBanner;

