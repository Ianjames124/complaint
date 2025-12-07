import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { X, Calendar, MapPin, Tag, User, FileText, Image as ImageIcon } from 'lucide-react';
import api from '../../api/axios';
import ImageGallery from './ImageGallery';
import Loader from './Loader';

const fetchComplaintDetails = async (id) => {
  const res = await api.get(`/complaints/get-details.php?id=${id}`);
  return res.data;
};

const ComplaintDetailModal = ({ complaintId, isOpen, onClose }) => {
  const { data, isLoading } = useQuery({
    queryKey: ['complaint-details', complaintId],
    queryFn: () => fetchComplaintDetails(complaintId),
    enabled: isOpen && !!complaintId,
  });

  const complaint = data?.data?.complaint;
  const statusUpdates = data?.data?.status_updates || [];
  const files = data?.data?.files || [];
  const images = data?.data?.images || [];
  const assignment = data?.data?.assignment;

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 overflow-y-auto">
      <div className="flex min-h-screen items-center justify-center p-4">
        {/* Backdrop */}
        <div
          className="fixed inset-0 bg-black/50 transition-opacity"
          onClick={onClose}
        />

        {/* Modal */}
        <div className="relative w-full max-w-4xl bg-white dark:bg-gray-800 rounded-lg shadow-xl max-h-[90vh] overflow-y-auto">
          {/* Header */}
          <div className="sticky top-0 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between z-10">
            <h2 className="text-xl font-bold text-gray-900 dark:text-gray-100">
              Complaint Details
            </h2>
            <button
              onClick={onClose}
              className="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
              aria-label="Close"
            >
              <X className="w-6 h-6" />
            </button>
          </div>

          {/* Content */}
          <div className="p-6">
            {isLoading ? (
              <Loader />
            ) : !complaint ? (
              <div className="text-center py-12">
                <p className="text-gray-500 dark:text-gray-400">Complaint not found</p>
              </div>
            ) : (
              <div className="space-y-6">
                {/* Title and Meta */}
                <div>
                  <h3 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-3">
                    {complaint.title || complaint.subject}
                  </h3>
                  <div className="flex flex-wrap items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                    <span className="flex items-center gap-1">
                      <Calendar className="w-4 h-4" />
                      {new Date(complaint.created_at).toLocaleDateString()}
                    </span>
                    <span className="flex items-center gap-1">
                      <Tag className="w-4 h-4" />
                      {complaint.category}
                    </span>
                    <span
                      className={`px-2 py-1 rounded-full text-xs font-semibold ${
                        complaint.status === 'Pending'
                          ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                          : complaint.status === 'Completed'
                          ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                          : 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300'
                      }`}
                    >
                      {complaint.status}
                    </span>
                  </div>
                </div>

                {/* Description */}
                <div>
                  <h4 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2 flex items-center gap-2">
                    <FileText className="w-5 h-5" />
                    Description
                  </h4>
                  <p className="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
                    {complaint.description}
                  </p>
                </div>

                {/* Location */}
                {complaint.location && (
                  <div>
                    <h4 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2 flex items-center gap-2">
                      <MapPin className="w-5 h-5" />
                      Location
                    </h4>
                    <p className="text-gray-700 dark:text-gray-300">{complaint.location}</p>
                  </div>
                )}

                {/* Images */}
                {images.length > 0 && (
                  <div>
                    <ImageGallery images={images} title="Evidence Images" />
                  </div>
                )}

                {/* Files */}
                {files.length > 0 && (
                  <div>
                    <h4 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3 flex items-center gap-2">
                      <FileText className="w-5 h-5" />
                      Attached Files ({files.length})
                    </h4>
                    <div className="grid grid-cols-2 md:grid-cols-3 gap-4">
                      {files.map((file) => (
                        <div key={file.id} className="border border-gray-200 dark:border-gray-700 rounded-lg p-2">
                          <p className="text-sm text-gray-700 dark:text-gray-300 truncate">
                            {file.file_name || 'File'}
                          </p>
                          <p className="text-xs text-gray-500 dark:text-gray-400">
                            {file.file_type}
                          </p>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {/* Assigned Staff */}
                {assignment && (
                  <div>
                    <h4 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2 flex items-center gap-2">
                      <User className="w-5 h-5" />
                      Assigned Staff
                    </h4>
                    <p className="text-gray-700 dark:text-gray-300">
                      {assignment.staff_name} ({assignment.staff_email})
                    </p>
                  </div>
                )}

                {/* Status Updates */}
                {statusUpdates.length > 0 && (
                  <div>
                    <h4 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-3">
                      Status Timeline
                    </h4>
                    <div className="space-y-2">
                      {statusUpdates.map((update, index) => (
                        <div
                          key={index}
                          className="border-l-2 border-primary-500 pl-4 py-2"
                        >
                          <p className="font-semibold text-gray-900 dark:text-gray-100">
                            {update.status}
                          </p>
                          {update.notes && (
                            <p className="text-sm text-gray-600 dark:text-gray-400">
                              {update.notes}
                            </p>
                          )}
                          <p className="text-xs text-gray-500 dark:text-gray-500 mt-1">
                            {new Date(update.created_at).toLocaleString()}
                          </p>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
};

export default ComplaintDetailModal;

