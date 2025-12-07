import React from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import CitizenNavbar from '../../components/navbar/CitizenNavbar';
import Loader from '../../components/common/Loader';

// Treat complaints whose category starts with "Request:" as requests.

const fetchMyComplaints = async () => {
  const res = await api.get('/citizen/my-complaints.php');
  return res.data;
};

const MyRequests = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['citizen-requests-list'],
    queryFn: fetchMyComplaints,
  });

  const all = data?.data?.complaints || [];
  const requests = all.filter((c) => String(c.category || '').startsWith('Request:'));

  return (
    <div className="min-h-screen">
      <CitizenNavbar />
      <main className="mx-auto max-w-6xl px-4 py-6">
        <h1 className="mb-4 text-2xl font-bold text-gray-800">My Requests</h1>
        {isLoading ? (
          <Loader />
        ) : (
          <div className="rounded-lg bg-white p-4 shadow">
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="bg-gray-100 text-xs uppercase text-gray-500">
                  <tr>
                    <th className="px-3 py-2">Title</th>
                    <th className="px-3 py-2">Request Type</th>
                    <th className="px-3 py-2">Status</th>
                    <th className="px-3 py-2">Latest Update</th>
                    <th className="px-3 py-2">Created At</th>
                  </tr>
                </thead>
                <tbody>
                  {requests.map((c) => {
                    const type = String(c.category || '').replace(/^Request:\s*/i, '');
                    return (
                      <tr key={c.id} className="border-b last:border-0">
                        <td className="px-3 py-2">{c.title}</td>
                        <td className="px-3 py-2">{type || 'Request'}</td>
                        <td className="px-3 py-2">
                          <span className="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-semibold text-indigo-700">
                            {c.status}
                          </span>
                        </td>
                        <td className="px-3 py-2 text-xs text-gray-600">
                          {c.latest_status_update?.status || '-'}
                        </td>
                        <td className="px-3 py-2 text-xs text-gray-500">{c.created_at}</td>
                      </tr>
                    );
                  })}
                  {requests.length === 0 && (
                    <tr>
                      <td colSpan={5} className="px-3 py-4 text-center text-sm text-gray-500">
                        No requests found.
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

export default MyRequests;


