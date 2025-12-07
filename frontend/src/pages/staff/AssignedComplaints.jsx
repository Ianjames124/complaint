import React, { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import Swal from 'sweetalert2';
import api from '../../api/axios';
import StaffNavbar from '../../components/navbar/StaffNavbar';
import Loader from '../../components/common/Loader';
import ComplaintDetailModal from '../../components/common/ComplaintDetailModal';
import { Eye } from 'lucide-react';

const fetchAssigned = async () => {
  const res = await api.get('/staff/assigned-complaints.php');
  return res.data;
};

const AssignedComplaints = () => {
  const queryClient = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ['staff-assigned-list'],
    queryFn: fetchAssigned,
  });
  const [updatingId, setUpdatingId] = useState(null);

  const complaints = data?.data?.complaints || [];
  const [selectedComplaintId, setSelectedComplaintId] = useState(null);

  const updateStatus = async (complaintId, status) => {
    const { value: notes } = await Swal.fire({
      title: 'Add notes (optional)',
      input: 'textarea',
      inputPlaceholder: 'Enter progress notes...',
      showCancelButton: true,
    });
    setUpdatingId(complaintId);
    try {
      const res = await api.post('/staff/update-progress.php', {
        complaint_id: complaintId,
        status,
        notes: notes || '',
      });
      if (res.data.success) {
        Swal.fire('Updated', 'Status updated successfully', 'success');
        queryClient.invalidateQueries(['staff-assigned-list']);
      } else {
        Swal.fire('Error', res.data.message || 'Failed to update status', 'error');
      }
    } catch {
      Swal.fire('Error', 'Unable to update status', 'error');
    } finally {
      setUpdatingId(null);
    }
  };

  return (
    <div className="min-h-screen">
      <StaffNavbar />
      <main className="mx-auto max-w-6xl px-4 py-6">
        <h1 className="mb-4 text-2xl font-bold text-gray-800">Assigned Complaints</h1>
        {isLoading ? (
          <Loader />
        ) : (
          <div className="rounded-lg bg-white p-4 shadow">
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="bg-gray-100 text-xs uppercase text-gray-500">
                  <tr>
                    <th className="px-3 py-2">Title</th>
                    <th className="px-3 py-2">Citizen</th>
                    <th className="px-3 py-2">Status</th>
                    <th className="px-3 py-2">Assigned At</th>
                    <th className="px-3 py-2">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {complaints.map((c) => (
                    <tr key={c.id} className="border-b last:border-0">
                      <td className="px-3 py-2">{c.title}</td>
                      <td className="px-3 py-2 text-xs text-gray-700">
                        {c.citizen_name}
                        <br />
                        <span className="text-[11px] text-gray-500">{c.citizen_email}</span>
                      </td>
                      <td className="px-3 py-2">
                        <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700">
                          {c.status}
                        </span>
                      </td>
                      <td className="px-3 py-2 text-xs text-gray-500">{c.assigned_at}</td>
                      <td className="px-3 py-2 space-x-2">
                        <button
                          type="button"
                          onClick={() => setSelectedComplaintId(c.id)}
                          className="rounded bg-blue-600 px-3 py-1 text-xs font-semibold text-white hover:bg-blue-700"
                          title="View Details"
                        >
                          <Eye className="w-4 h-4 inline mr-1" />
                          View
                        </button>
                        <button
                          type="button"
                          onClick={() => updateStatus(c.id, 'In Progress')}
                          disabled={updatingId === c.id}
                          className="rounded bg-indigo-600 px-3 py-1 text-xs font-semibold text-white hover:bg-indigo-700 disabled:opacity-60"
                        >
                          In Progress
                        </button>
                        <button
                          type="button"
                          onClick={() => updateStatus(c.id, 'Completed')}
                          disabled={updatingId === c.id}
                          className="rounded bg-emerald-600 px-3 py-1 text-xs font-semibold text-white hover:bg-emerald-700 disabled:opacity-60"
                        >
                          Completed
                        </button>
                      </td>
                    </tr>
                  ))}
                  {complaints.length === 0 && (
                    <tr>
                      <td colSpan={5} className="px-3 py-4 text-center text-sm text-gray-500">
                        No complaints assigned to you.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </main>

      {/* Complaint Detail Modal */}
      <ComplaintDetailModal
        complaintId={selectedComplaintId}
        isOpen={!!selectedComplaintId}
        onClose={() => setSelectedComplaintId(null)}
      />
    </div>
  );
};

export default AssignedComplaints;


