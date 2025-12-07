import React from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '../../api/axios';
import CitizenNavbar from '../../components/navbar/CitizenNavbar';
import Loader from '../../components/common/Loader';

const fetchMyComplaints = async () => {
  const res = await api.get('/citizen/my-complaints.php');
  return res.data;
};

const MyComplaints = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['citizen-complaints-list'],
    queryFn: fetchMyComplaints,
  });

  const complaints = data?.data?.complaints || [];

  return (
    <div className="min-h-screen">
      <CitizenNavbar />
      <main className="mx-auto max-w-6xl px-4 py-6">
        <h1 className="mb-4 text-2xl font-bold text-gray-800">My Complaints</h1>
        {isLoading ? (
          <Loader />
        ) : (
          <div className="rounded-lg bg-white p-4 shadow">
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="bg-gray-100 text-xs uppercase text-gray-500">
                  <tr>
                    <th className="px-3 py-2">Title</th>
                    <th className="px-3 py-2">Category</th>
                    <th className="px-3 py-2">Status</th>
                    <th className="px-3 py-2">Latest Update</th>
                    <th className="px-3 py-2">Created At</th>
                  </tr>
                </thead>
                <tbody>
                  {complaints
                    .filter((c) => !String(c.category || '').startsWith('Request:'))
                    .map((c) => (
                      <tr key={c.id} className="border-b last:border-0">
                        <td className="px-3 py-2">{c.title}</td>
                        <td className="px-3 py-2">{c.category}</td>
                        <td className="px-3 py-2">
                          <span className="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-semibold text-blue-700">
                            {c.status}
                          </span>
                        </td>
                        <td className="px-3 py-2 text-xs text-gray-600">
                          {c.latest_status_update?.status || '-'}
                        </td>
                        <td className="px-3 py-2 text-xs text-gray-500">{c.created_at}</td>
                      </tr>
                    ))}
                  {complaints.length === 0 && (
                    <tr>
                      <td colSpan={5} className="px-3 py-4 text-center text-sm text-gray-500">
                        No complaints found.
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

export default MyComplaints;


