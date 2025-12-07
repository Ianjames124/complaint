import React from 'react';
import { useQuery } from '@tanstack/react-query';
import StaffNavbar from '../../components/navbar/StaffNavbar';
import api from '../../api/axios';
import Loader from '../../components/common/Loader';

const fetchAssignedRequests = async () => {
  const res = await api.get('/staff/assigned-requests.php');
  return res.data;
};

const AssignedRequests = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['staff-assigned-requests'],
    queryFn: fetchAssignedRequests,
  });

  const requests = data?.data?.requests || [];

  return (
    <div className="min-h-screen">
      <StaffNavbar />
      <main className="mx-auto max-w-6xl px-4 py-6">
        <h1 className="mb-4 text-2xl font-bold text-gray-800">Assigned Requests</h1>
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
                        <span className="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                          {r.status}
                        </span>
                      </td>
                      <td className="px-3 py-2 text-xs text-gray-500">{r.assigned_at}</td>
                    </tr>
                  ))}
                  {requests.length === 0 && (
                    <tr>
                      <td colSpan={4} className="px-3 py-4 text-center text-sm text-gray-500">
                        No requests assigned to you.
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

export default AssignedRequests;

