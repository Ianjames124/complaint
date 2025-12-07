import React from 'react';
import CitizenNavbar from '../../components/navbar/CitizenNavbar';
import { useAuth } from '../../context/AuthContext';

const Profile = () => {
  const { user } = useAuth();

  return (
    <div className="min-h-screen">
      <CitizenNavbar />
      <main className="mx-auto max-w-3xl px-4 py-6">
        <h1 className="mb-4 text-2xl font-bold text-gray-800">My Profile</h1>
        <div className="rounded-lg bg-white p-6 shadow">
          <dl className="space-y-3 text-sm">
            <div>
              <dt className="font-medium text-gray-600">Full Name</dt>
              <dd className="text-gray-900">{user?.full_name}</dd>
            </div>
            <div>
              <dt className="font-medium text-gray-600">Email</dt>
              <dd className="text-gray-900">{user?.email}</dd>
            </div>
            <div>
              <dt className="font-medium text-gray-600">Role</dt>
              <dd className="text-gray-900 capitalize">{user?.role}</dd>
            </div>
            <div>
              <dt className="font-medium text-gray-600">Department</dt>
              <dd className="text-gray-900">{user?.department_id || 'N/A'}</dd>
            </div>
          </dl>
        </div>
      </main>
    </div>
  );
};

export default Profile;


