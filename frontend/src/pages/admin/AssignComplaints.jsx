import React, { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import Swal from 'sweetalert2';
import api from '../../api/axios';
import AdminNavbar from '../../components/navbar/AdminNavbar';
import Loader from '../../components/common/Loader';
import ComplaintDetailModal from '../../components/common/ComplaintDetailModal';
import { Eye } from 'lucide-react';

const fetchComplaints = async () => {
  const res = await api.get('/admin/complaints.php', {
    params: { status: 'Pending' },
  });
  return res.data;
};

const fetchStaff = async () => {
  const res = await api.get('/admin/staff-list.php');
  return res.data;
};

const AssignComplaints = () => {
  const queryClient = useQueryClient();
  const { data, isLoading } = useQuery({
    queryKey: ['admin-complaints-pending'],
    queryFn: fetchComplaints,
  });
  const { data: staffData } = useQuery({
    queryKey: ['admin-staff-list'],
    queryFn: fetchStaff,
  });
  const staffList = staffData?.data?.staff || [];
  const [assigningId, setAssigningId] = useState(null);
  const [selectedComplaintId, setSelectedComplaintId] = useState(null);

  const complaints = data?.data?.complaints || [];

  const handleAssign = async (complaintId) => {
    const inputOptions = staffList.reduce((acc, s) => {
      acc[s.id] = s.full_name;
      return acc;
    }, {});

    const { value: staffId } = await Swal.fire({
      title: 'Assign to staff',
      input: 'select',
      inputOptions,
      inputPlaceholder: 'Select staff',
      showCancelButton: true,
    });
    if (!staffId) return;

    setAssigningId(complaintId);
    try {
      const res = await api.post('/admin/assign.php', {
        complaint_id: complaintId,
        staff_id: Number(staffId),
      });
      if (res.data.success) {
        Swal.fire('Assigned', 'Complaint assigned successfully', 'success');
        queryClient.invalidateQueries(['admin-complaints-pending']);
      } else {
        Swal.fire('Error', res.data.message || 'Failed to assign complaint', 'error');
      }
    } catch (error) {
      console.error('Assign complaint error:', error);
      const errorMessage = error.response?.data?.message || error.message || 'Unable to assign complaint';
      Swal.fire('Error', errorMessage, 'error');
    } finally {
      setAssigningId(null);
    }
  };

  return (
    <div className="min-h-screen">
      <AdminNavbar />
      <main className="mx-auto max-w-6xl px-4 py-6">
        <h1 className="mb-4 text-2xl font-bold text-gray-800">Assign Complaints</h1>
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
                    <th className="px-3 py-2">Created At</th>
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
                        <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">
                          {c.status}
                        </span>
                      </td>
                      <td className="px-3 py-2 text-xs text-gray-500">{c.created_at}</td>
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
                          onClick={() => handleAssign(c.id)}
                          disabled={assigningId === c.id}
                          className="rounded bg-slate-800 px-3 py-1 text-xs font-semibold text-white hover:bg-slate-900 disabled:opacity-60"
                        >
                          Assign
                        </button>
                      </td>
                    </tr>
                  ))}
                  {complaints.length === 0 && (
                    <tr>
                      <td colSpan={5} className="px-3 py-4 text-center text-sm text-gray-500">
                        No pending complaints to assign.
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

export default AssignComplaints;


