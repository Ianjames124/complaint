import React, { useEffect } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { useParams, useNavigate } from 'react-router-dom';
import { ArrowLeft, Calendar, MapPin, Tag, User, FileText } from 'lucide-react';
import api from '../../api/axios';
import CitizenNavbar from '../../components/navbar/CitizenNavbar';
import Card from '../../components/common/Card';
import Button from '../../components/common/Button';
import ComplaintTimeline from '../../components/citizen/ComplaintTimeline';
import ReceiptDownloadButton from '../../components/citizen/ReceiptDownloadButton';
import { SkeletonCard } from '../../components/common/SkeletonLoader';
import ImageGallery from '../../components/common/ImageGallery';
import { useSocket } from '../../context/SocketContext';

const fetchComplaintDetails = async (id) => {
  const res = await api.get(`/citizen/get-complaint-details.php?id=${id}`);
  return res.data;
};

const CitizenComplaintDetails = () => {
  const { id } = useParams();
  const navigate = useNavigate();
  const queryClient = useQueryClient();
  const { socket, isConnected } = useSocket();

  const { data, isLoading } = useQuery({
    queryKey: ['citizen-complaint-details', id],
    queryFn: () => fetchComplaintDetails(id),
    enabled: !!id,
  });

  const complaint = data?.data?.complaint;
  const statusUpdates = data?.data?.status_updates || [];
  const files = data?.data?.files || [];
  const images = data?.data?.images || [];
  const assignment = data?.data?.assignment;

  // Real-time updates for status changes
  useEffect(() => {
    if (!socket || !isConnected || !id) return;

    const handleStatusUpdate = (data) => {
      // Only update if this is the current complaint
      if (data.complaint_id === parseInt(id)) {
        // Invalidate and refetch complaint details
        queryClient.invalidateQueries(['citizen-complaint-details', id]);
      }
    };

    socket.on('complaint_status_updated', handleStatusUpdate);

    return () => {
      socket.off('complaint_status_updated', handleStatusUpdate);
    };
  }, [socket, isConnected, id, queryClient]);

  if (isLoading) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
        <CitizenNavbar />
        <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <SkeletonCard />
        </main>
      </div>
    );
  }

  if (!complaint) {
    return (
      <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
        <CitizenNavbar />
        <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <Card>
            <div className="text-center py-12">
              <p className="text-gray-500 dark:text-gray-400">Complaint not found</p>
              <Button
                variant="primary"
                onClick={() => navigate('/citizen/my-complaints')}
                className="mt-4"
              >
                Back to Complaints
              </Button>
            </div>
          </Card>
        </main>
      </div>
    );
  }

  const baseUrl = 'http://localhost/complaint/backend/';

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <CitizenNavbar />
      <main className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        {/* Back Button */}
        <Button
          variant="secondary"
          onClick={() => navigate('/citizen/my-complaints')}
          className="mb-6"
        >
          <ArrowLeft className="w-4 h-4 mr-2" />
          Back to Complaints
        </Button>

        {/* Complaint Details */}
        <Card className="mb-6">
          <div className="flex items-start justify-between mb-6">
            <div>
              <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                {complaint.title || complaint.subject}
              </h1>
              <div className="flex items-center gap-4 text-sm text-gray-600 dark:text-gray-400">
                <span className="flex items-center gap-1">
                  <Calendar className="w-4 h-4" />
                  {new Date(complaint.created_at).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                  })}
                </span>
                <span className="flex items-center gap-1">
                  <Tag className="w-4 h-4" />
                  {complaint.category}
                </span>
              </div>
            </div>
            <ReceiptDownloadButton complaintId={complaint.id} />
          </div>

          {/* Status Badge */}
          <div className="mb-6">
            <span
              className={`inline-flex px-3 py-1 text-sm font-semibold rounded-full ${
                complaint.status === 'Pending'
                  ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300'
                  : complaint.status === 'Completed'
                  ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                  : complaint.status === 'In Progress'
                  ? 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300'
                  : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
              }`}
            >
              {complaint.status}
            </span>
          </div>

          {/* Description */}
          <div className="mb-6">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2 flex items-center gap-2">
              <FileText className="w-5 h-5" />
              Description
            </h2>
            <p className="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">
              {complaint.description}
            </p>
          </div>

          {/* Location */}
          {complaint.location && (
            <div className="mb-6">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2 flex items-center gap-2">
                <MapPin className="w-5 h-5" />
                Location
              </h2>
              <p className="text-gray-700 dark:text-gray-300">{complaint.location}</p>
            </div>
          )}

          {/* Assigned Staff */}
          {assignment && (
            <div className="mb-6">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-2 flex items-center gap-2">
                <User className="w-5 h-5" />
                Assigned Staff
              </h2>
              <p className="text-gray-700 dark:text-gray-300">
                {assignment.staff_name} ({assignment.staff_email})
              </p>
              <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Assigned on {new Date(assignment.assigned_at).toLocaleDateString()}
              </p>
            </div>
          )}
        </Card>

        {/* Images Gallery */}
        {images.length > 0 && (
          <Card className="mb-6">
            <ImageGallery images={images} title="Evidence Images" />
          </Card>
        )}

        {/* Timeline */}
        <Card className="mb-6">
          <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
            Status Timeline
          </h2>
          <ComplaintTimeline status={complaint.status} statusUpdates={statusUpdates} />
        </Card>

        {/* Files */}
        {files.length > 0 && (
          <Card>
            <h2 className="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">
              Attached Files ({files.length})
            </h2>
            <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
              {files.map((file) => (
                <div key={file.id} className="relative group">
                  {file.file_type === 'image' ? (
                    <img
                      src={baseUrl + file.file_path}
                      alt={file.file_name || 'Attachment'}
                      className="w-full h-32 object-cover rounded-lg border border-gray-200 dark:border-gray-700"
                      onError={(e) => {
                        e.target.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="100" height="100"%3E%3Crect fill="%23ddd" width="100" height="100"/%3E%3Ctext fill="%23999" x="50%25" y="50%25" text-anchor="middle" dy=".3em" font-size="12"%3EImage%3C/text%3E%3C/svg%3E';
                      }}
                    />
                  ) : (
                    <div className="w-full h-32 bg-gray-200 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                      <FileText className="w-8 h-8 text-gray-400" />
                    </div>
                  )}
                  <a
                    href={baseUrl + file.file_path}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="absolute inset-0 flex items-center justify-center bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity duration-200 rounded-lg"
                  >
                    <span className="text-white text-sm font-medium">View</span>
                  </a>
                  <p className="mt-1 text-xs text-gray-600 dark:text-gray-400 truncate">
                    {file.file_name || 'File'}
                  </p>
                </div>
              ))}
            </div>
          </Card>
        )}
      </main>
    </div>
  );
};

export default CitizenComplaintDetails;

