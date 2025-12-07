import React, { useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import Swal from 'sweetalert2';
import AdminNavbar from '../../components/navbar/AdminNavbar';
import api from '../../api/axios';
import Loader from '../../components/common/Loader';

const fetchRequests = async () => {
  const res = await api.get('/admin/requests.php', {
    params: { status: 'Pending' }, // This matches the database enum
  });
  return res.data;
};

const fetchStaff = async () => {
  const res = await api.get('/admin/staff-list.php');
  return res.data;
};

const AssignRequests = () => {
  const queryClient = useQueryClient();
  const { data: requestsData, isLoading } = useQuery({
    queryKey: ['admin-requests-pending'],
    queryFn: fetchRequests,
  });
  const { data: staffData } = useQuery({
    queryKey: ['admin-staff-for-requests'],
    queryFn: fetchStaff,
  });

  const [assigningId, setAssigningId] = useState(null);

  const requests = requestsData?.data?.requests || [];
  const staff = staffData?.data?.staff || [];

  const handleAssign = async (requestId) => {
    const inputOptions = staff.reduce((acc, s) => {
      acc[s.id] = s.full_name;
      return acc;
    }, {});

    const { value: staffId } = await Swal.fire({
      title: 'Assign request to staff',
      input: 'select',
      inputOptions,
      inputPlaceholder: 'Select staff',
      showCancelButton: true,
    });
    if (!staffId) return;

    setAssigningId(requestId);
    try {
      const res = await api.post('/admin/assign-request.php', {
        complaint_id: requestId,
        staff_id: Number(staffId),
      });
      if (res.data.success) {
        Swal.fire('Assigned', 'Request assigned successfully', 'success');
        queryClient.invalidateQueries(['admin-requests-pending']);
      } else {
        Swal.fire('Error', res.data.message || 'Failed to assign request', 'error');
      }
    } catch (error) {
      console.error('Assign request error:', error);
      const errorMessage = error.response?.data?.message || error.message || 'Unable to assign request';
      Swal.fire('Error', errorMessage, 'error');
    } finally {
      setAssigningId(null);
    }
  };

  return (
    <div className="min-h-screen">
      <AdminNavbar />
      <main className="mx-auto max-w-6xl px-4 py-6">
        <h1 className="mb-4 text-2xl font-bold text-gray-800">Assign Requests</h1>
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
                  {requests.map((r) => (
                    <tr key={r.id} className="border-b last:border-0">
                      <td className="px-3 py-2">{r.title}</td>
                      <td className="px-3 py-2 text-xs text-gray-700">
                        {r.citizen_name}
                        <br />
                        <span className="text-[11px] text-gray-500">{r.citizen_email}</span>
                      </td>
                      <td className="px-3 py-2">
                        <span className="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-700">
                          {r.status}
                        </span>
                      </td>
                      <td className="px-3 py-2 text-xs text-gray-500">{r.created_at}</td>
                      <td className="px-3 py-2">
                        <button
                          type="button"
                          onClick={() => handleAssign(r.id)}
                          disabled={assigningId === r.id}
                          className="rounded bg-slate-800 px-3 py-1 text-xs font-semibold text-white hover:bg-slate-900 disabled:opacity-60"
                        >
                          Assign
                        </button>
                      </td>
                    </tr>
                  ))}
                  {requests.length === 0 && (
                    <tr>
                      <td colSpan={5} className="px-3 py-4 text-center text-sm text-gray-500">
                        No pending requests to assign.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </main>
    </div>
  );
};

export default AssignRequests;

