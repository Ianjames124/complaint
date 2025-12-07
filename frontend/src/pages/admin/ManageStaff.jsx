import React, { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import AdminNavbar from '../../components/navbar/AdminNavbar';
import api from '../../api/axios';
import Card from '../../components/common/Card';
import { SkeletonTable } from '../../components/common/SkeletonLoader';
import SearchBar from '../../components/common/SearchBar';
import FilterDropdown from '../../components/common/FilterDropdown';
import Pagination from '../../components/common/Pagination';
import { Users } from 'lucide-react';

const fetchStaff = async () => {
  const res = await api.get('/admin/staff-list.php');
  return res.data;
};

const ManageStaff = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['admin-staff-list'],
    queryFn: fetchStaff,
  });

  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 10;

  const staff = data?.data?.staff || [];

  // Filter and search
  const filteredStaff = useMemo(() => {
    return staff.filter((s) => {
      const matchesSearch =
        s.full_name?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        s.email?.toLowerCase().includes(searchTerm.toLowerCase());
      const matchesStatus = statusFilter === 'all' || s.status === statusFilter;
      return matchesSearch && matchesStatus;
    });
  }, [staff, searchTerm, statusFilter]);

  // Pagination
  const totalPages = Math.ceil(filteredStaff.length / itemsPerPage);
  const paginatedStaff = filteredStaff.slice(
    (currentPage - 1) * itemsPerPage,
    currentPage * itemsPerPage
  );

  const statusOptions = [
    { value: 'all', label: 'All Status' },
    { value: 'active', label: 'Active' },
    { value: 'disabled', label: 'Disabled' },
  ];

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <AdminNavbar />
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="mb-8">
          <div className="flex items-center gap-3 mb-2">
            <Users className="w-8 h-8 text-primary-600 dark:text-primary-400" />
            <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
              Manage Staff
            </h1>
          </div>
          <p className="text-gray-600 dark:text-gray-400">
            View and manage all staff members in the system
          </p>
        </div>

        {isLoading ? (
          <SkeletonTable rows={5} cols={5} />
        ) : (
          <>
            {/* Filters and Search */}
            <Card className="mb-6">
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <SearchBar
                  value={searchTerm}
                  onChange={setSearchTerm}
                  placeholder="Search by name or email..."
                />
                <FilterDropdown
                  label="Filter by Status"
                  value={statusFilter}
                  onChange={setStatusFilter}
                  options={statusOptions}
                />
              </div>
            </Card>

            {/* Table */}
            <Card>
              <div className="overflow-x-auto">
                <table className="min-w-full">
                  <thead>
                    <tr className="border-b border-gray-200 dark:border-gray-700">
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Name
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Email
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Department ID
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Status
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Created At
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                    {paginatedStaff.length > 0 ? (
                      paginatedStaff.map((s) => (
                        <tr key={s.id} className="table-row">
                          <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                            {s.full_name}
                          </td>
                          <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            {s.email}
                          </td>
                          <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            {s.department_id || '-'}
                          </td>
                          <td className="px-4 py-3">
                            <span
                              className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${
                                s.status === 'active'
                                  ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300'
                                  : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                              }`}
                            >
                              {s.status}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            {s.created_at ? new Date(s.created_at).toLocaleDateString() : '-'}
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan={5} className="px-4 py-12 text-center">
                          <div className="flex flex-col items-center gap-2">
                            <Users className="w-12 h-12 text-gray-400 dark:text-gray-600" />
                            <p className="text-gray-500 dark:text-gray-400 font-medium">
                              No staff members found
                            </p>
                            <p className="text-sm text-gray-400 dark:text-gray-500">
                              {searchTerm || statusFilter !== 'all'
                                ? 'Try adjusting your search or filters'
                                : 'No staff accounts have been created yet'}
                            </p>
                          </div>
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              {/* Pagination */}
              {totalPages > 1 && (
                <div className="mt-6">
                  <Pagination
                    currentPage={currentPage}
                    totalPages={totalPages}
                    onPageChange={setCurrentPage}
                  />
                </div>
              )}

              {/* Results Count */}
              <div className="mt-4 text-sm text-gray-600 dark:text-gray-400">
                Showing {paginatedStaff.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0} to{' '}
                {Math.min(currentPage * itemsPerPage, filteredStaff.length)} of{' '}
                {filteredStaff.length} staff members
              </div>
            </Card>
          </>
        )}
      </main>
    </div>
  );
};

export default ManageStaff;
