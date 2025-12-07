import React, { useState, useMemo } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate } from 'react-router-dom';
import { FileText, Eye } from 'lucide-react';
import api from '../../api/axios';
import CitizenNavbar from '../../components/navbar/CitizenNavbar';
import Card from '../../components/common/Card';
import { SkeletonTable } from '../../components/common/SkeletonLoader';
import SearchBar from '../../components/common/SearchBar';
import FilterDropdown from '../../components/common/FilterDropdown';
import Pagination from '../../components/common/Pagination';
import Button from '../../components/common/Button';

const fetchMyComplaints = async () => {
  const res = await api.get('/citizen/my-complaints.php');
  return res.data;
};

const CitizenComplaintHistory = () => {
  const { data, isLoading } = useQuery({
    queryKey: ['citizen-complaints-list'],
    queryFn: fetchMyComplaints,
  });

  const navigate = useNavigate();
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [dateFilter, setDateFilter] = useState('all');
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 10;

  const complaints = data?.data?.complaints || [];
  
  // Filter out requests (they have category starting with "Request:")
  const actualComplaints = complaints.filter(
    (c) => !String(c.category || '').startsWith('Request:')
  );

  // Filter and search
  const filteredComplaints = useMemo(() => {
    return actualComplaints.filter((c) => {
      const matchesSearch =
        (c.title || '').toLowerCase().includes(searchTerm.toLowerCase()) ||
        (c.description || '').toLowerCase().includes(searchTerm.toLowerCase()) ||
        (c.category || '').toLowerCase().includes(searchTerm.toLowerCase());
      
      const matchesStatus = statusFilter === 'all' || c.status === statusFilter;
      
      const matchesDate = (() => {
        if (dateFilter === 'all') return true;
        const complaintDate = new Date(c.created_at);
        const now = new Date();
        const daysAgo = parseInt(dateFilter);
        
        if (isNaN(daysAgo)) return true;
        
        const filterDate = new Date(now);
        filterDate.setDate(filterDate.getDate() - daysAgo);
        return complaintDate >= filterDate;
      })();
      
      return matchesSearch && matchesStatus && matchesDate;
    });
  }, [actualComplaints, searchTerm, statusFilter, dateFilter]);

  // Pagination
  const totalPages = Math.ceil(filteredComplaints.length / itemsPerPage);
  const paginatedComplaints = filteredComplaints.slice(
    (currentPage - 1) * itemsPerPage,
    currentPage * itemsPerPage
  );

  const statusOptions = [
    { value: 'all', label: 'All Status' },
    { value: 'Pending', label: 'Pending' },
    { value: 'Assigned', label: 'Assigned' },
    { value: 'In Progress', label: 'In Progress' },
    { value: 'Completed', label: 'Completed' },
    { value: 'Closed', label: 'Closed' },
  ];

  const dateOptions = [
    { value: 'all', label: 'All Time' },
    { value: '7', label: 'Last 7 Days' },
    { value: '30', label: 'Last 30 Days' },
    { value: '90', label: 'Last 90 Days' },
  ];

  const getStatusColor = (status) => {
    const colors = {
      Pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
      Assigned: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
      'In Progress': 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
      Completed: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
      Closed: 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300',
    };
    return colors[status] || colors.Pending;
  };

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      <CitizenNavbar />
      <main className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <div className="mb-8">
          <div className="flex items-center gap-3 mb-2">
            <FileText className="w-8 h-8 text-primary-600 dark:text-primary-400" />
            <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100">
              Complaint History
            </h1>
          </div>
          <p className="text-gray-600 dark:text-gray-400">
            View and track all your submitted complaints
          </p>
        </div>

        {isLoading ? (
          <SkeletonTable rows={5} cols={6} />
        ) : (
          <>
            {/* Filters and Search */}
            <Card className="mb-6">
              <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <SearchBar
                  value={searchTerm}
                  onChange={setSearchTerm}
                  placeholder="Search by title, description, or category..."
                />
                <FilterDropdown
                  label="Filter by Status"
                  value={statusFilter}
                  onChange={setStatusFilter}
                  options={statusOptions}
                />
                <FilterDropdown
                  label="Filter by Date"
                  value={dateFilter}
                  onChange={setDateFilter}
                  options={dateOptions}
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
                        ID
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Title
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Category
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Status
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Date Submitted
                      </th>
                      <th className="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        Actions
                      </th>
                    </tr>
                  </thead>
                  <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                    {paginatedComplaints.length > 0 ? (
                      paginatedComplaints.map((complaint) => (
                        <tr key={complaint.id} className="table-row">
                          <td className="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">
                            #{complaint.id}
                          </td>
                          <td className="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                            {complaint.title || complaint.subject}
                          </td>
                          <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            {complaint.category}
                          </td>
                          <td className="px-4 py-3">
                            <span
                              className={`inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusColor(
                                complaint.status
                              )}`}
                            >
                              {complaint.status}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-sm text-gray-600 dark:text-gray-400">
                            {complaint.created_at
                              ? new Date(complaint.created_at).toLocaleDateString()
                              : '-'}
                          </td>
                          <td className="px-4 py-3">
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => navigate(`/citizen/complaint/${complaint.id}`)}
                            >
                              <Eye className="w-4 h-4 mr-1" />
                              View Details
                            </Button>
                          </td>
                        </tr>
                      ))
                    ) : (
                      <tr>
                        <td colSpan={6} className="px-4 py-12 text-center">
                          <div className="flex flex-col items-center gap-2">
                            <FileText className="w-12 h-12 text-gray-400 dark:text-gray-600" />
                            <p className="text-gray-500 dark:text-gray-400 font-medium">
                              No complaints found
                            </p>
                            <p className="text-sm text-gray-400 dark:text-gray-500">
                              {searchTerm || statusFilter !== 'all' || dateFilter !== 'all'
                                ? 'Try adjusting your search or filters'
                                : 'Submit your first complaint to get started'}
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
                Showing {paginatedComplaints.length > 0 ? (currentPage - 1) * itemsPerPage + 1 : 0} to{' '}
                {Math.min(currentPage * itemsPerPage, filteredComplaints.length)} of{' '}
                {filteredComplaints.length} complaint(s)
              </div>
            </Card>
          </>
        )}
      </main>
    </div>
  );
};

export default CitizenComplaintHistory;

