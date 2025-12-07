import React from 'react';
import { Route, Routes, Navigate } from 'react-router-dom';
import LandingPage from '../pages/LandingPage';
import LoginForm from '../components/auth/LoginForm';
import RegisterForm from '../components/auth/RegisterForm';
import ProtectedRoute from '../components/common/ProtectedRoute';
import CitizenDashboard from '../pages/citizen/CitizenDashboard';
import SubmitComplaint from '../pages/citizen/SubmitComplaint';
import SubmitRequest from '../pages/citizen/SubmitRequest';
import MyComplaints from '../pages/citizen/MyComplaints';
import CitizenComplaintHistory from '../pages/citizen/CitizenComplaintHistory';
import CitizenComplaintDetails from '../pages/citizen/CitizenComplaintDetails';
import MyRequests from '../pages/citizen/MyRequests';
import CitizenProfile from '../pages/citizen/CitizenProfile';
import StaffDashboard from '../pages/staff/StaffDashboard';
import AssignedComplaints from '../pages/staff/AssignedComplaints';
import AssignedRequests from '../pages/staff/AssignedRequests';
import AdminDashboard from '../pages/admin/AdminDashboard';
import ManageCitizens from '../pages/admin/ManageCitizens';
import ManageStaff from '../pages/admin/ManageStaff';
import CreateStaff from '../pages/admin/CreateStaff';
import AssignComplaints from '../pages/admin/AssignComplaints';
import AssignRequests from '../pages/admin/AssignRequests';
import AdminAssignmentDashboard from '../pages/admin/AdminAssignmentDashboard';
import StaffPerformance from '../pages/admin/StaffPerformance';
import AdminAnalytics from '../pages/admin/AdminAnalytics';
import StaffWorkloadPage from '../pages/staff/StaffWorkloadPage';

const AppRouter = () => {
  return (
    <Routes>
      <Route path="/" element={<LandingPage />} />
      <Route path="/login" element={<LoginForm />} />
      <Route path="/register" element={<RegisterForm />} />

      {/* Citizen */}
      <Route element={<ProtectedRoute allowedRoles={['citizen']} />}>
        <Route path="/citizen" element={<CitizenDashboard />} />
        <Route path="/citizen/submit-complaint" element={<SubmitComplaint />} />
        <Route path="/citizen/submit-request" element={<SubmitRequest />} />
        <Route path="/citizen/my-complaints" element={<CitizenComplaintHistory />} />
        <Route path="/citizen/complaint/:id" element={<CitizenComplaintDetails />} />
        <Route path="/citizen/my-requests" element={<MyRequests />} />
        <Route path="/citizen/profile" element={<CitizenProfile />} />
      </Route>

      {/* Staff */}
      <Route element={<ProtectedRoute allowedRoles={['staff']} />}>
        <Route path="/staff" element={<StaffDashboard />} />
        <Route path="/staff/assigned-complaints" element={<AssignedComplaints />} />
        <Route path="/staff/assigned-requests" element={<AssignedRequests />} />
        <Route path="/staff/workload" element={<StaffWorkloadPage />} />
      </Route>

      {/* Admin */}
      <Route element={<ProtectedRoute allowedRoles={['admin']} />}>
        <Route path="/admin" element={<AdminDashboard />} />
        <Route path="/admin/manage-citizens" element={<ManageCitizens />} />
        <Route path="/admin/manage-staff" element={<ManageStaff />} />
        <Route path="/admin/create-staff" element={<CreateStaff />} />
        <Route path="/admin/assign-complaints" element={<AssignComplaints />} />
        <Route path="/admin/assign-requests" element={<AssignRequests />} />
        <Route path="/admin/assignment-dashboard" element={<AdminAssignmentDashboard />} />
        <Route path="/admin/staff-performance" element={<StaffPerformance />} />
        <Route path="/admin/analytics" element={<AdminAnalytics />} />
      </Route>

      <Route path="*" element={<Navigate to="/login" replace />} />
    </Routes>
  );
};

export default AppRouter;


