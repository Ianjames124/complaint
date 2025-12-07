import React from 'react';

export const SkeletonCard = () => (
  <div className="card animate-pulse">
    <div className="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-4" />
    <div className="h-8 bg-gray-200 dark:bg-gray-700 rounded w-1/2" />
  </div>
);

export const SkeletonTable = ({ rows = 5, cols = 4 }) => (
  <div className="card overflow-hidden">
    <div className="animate-pulse">
      <div className="h-12 bg-gray-200 dark:bg-gray-700 mb-2" />
      {Array.from({ length: rows }).map((_, i) => (
        <div key={i} className="h-16 bg-gray-100 dark:bg-gray-800 mb-2 rounded" />
      ))}
    </div>
  </div>
);

export const SkeletonText = ({ className = '' }) => (
  <div className={`h-4 bg-gray-200 dark:bg-gray-700 rounded animate-pulse ${className}`} />
);

export default SkeletonCard;

