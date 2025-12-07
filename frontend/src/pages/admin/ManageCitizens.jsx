import React from 'react';
import { useQuery } from '@tanstack/react-query';
import AdminNavbar from '../../components/navbar/AdminNavbar';
import api from '../../api/axios';
import Loader from '../../components/common/Loader';

const fetchCitizens = async () => {
  const res = await api.get('/admin/citizens.php');
  return res.data;
};

const ManageCitizens = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['admin-citizens-list'],
    queryFn: fetchCitizens,
  });

  const citizens = data?.data?.citizens || [];

  return (
    <div className="min-h-screen">
      <AdminNavbar />
      <main className="mx-auto max-w-6xl px-4 py-6">
        <h1 className="mb-4 text-2xl font-bold text-gray-800">Manage Citizens</h1>
        {isLoading ? (
          <Loader />
        ) : (
          <div className="rounded-lg bg-white p-4 shadow">
            <div className="overflow-x-auto">
              <table className="min-w-full text-left text-sm">
                <thead className="bg-gray-100 text-xs uppercase text-gray-500">
                  <tr>
                    <th className="px-3 py-2">Name</th>
                    <th className="px-3 py-2">Email</th>
                    <th className="px-3 py-2">Status</th>
                    <th className="px-3 py-2">Created At</th>
                  </tr>
                </thead>
                <tbody>
                  {citizens.map((c) => (
                    <tr key={c.id} className="border-b last:border-0">
                      <td className="px-3 py-2">{c.full_name}</td>
                      <td className="px-3 py-2 text-xs text-gray-700">{c.email}</td>
                      <td className="px-3 py-2 text-xs">
                        <span
                          className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${
                            c.status === 'active'
                              ? 'bg-emerald-50 text-emerald-700'
                              : 'bg-gray-100 text-gray-600'
                          }`}
                        >
                          {c.status}
                        </span>
                      </td>
                      <td className="px-3 py-2 text-xs text-gray-500">{c.created_at}</td>
                    </tr>
                  ))}
                  {citizens.length === 0 && (
                    <tr>
                      <td colSpan={4} className="px-3 py-4 text-center text-sm text-gray-500">
                        No citizens found.
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

export default ManageCitizens;

