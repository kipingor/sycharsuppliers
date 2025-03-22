import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Table, TableHead, TableBody, TableRow, TableHeader, TableCell } from '@/components/table';
import Modal from "@/components/ui/modal";
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from "@/components/ui/select";
import HeadingSmall from '@/components/heading-small';

const breadcrumbs = [
  { title: 'User Management', href: '/settings/users' },
];

export default function UserManagement({ users, roles, permissions }) {
  const [selectedUsers, setSelectedUsers] = useState([]);
  const [search, setSearch] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [selectedUser, setSelectedUser] = useState(null);

  const filteredUsers = users.filter((user) =>
    user.name.toLowerCase().includes(search.toLowerCase()) ||
    user.email.toLowerCase().includes(search.toLowerCase())
  );

  const handleSelectUser = (id) => {
    setSelectedUsers((prev) =>
      prev.includes(id) ? prev.filter((userId) => userId !== id) : [...prev, id]
    );
  };

  const openUserModal = (user) => {
    setSelectedUser(user);
    setShowModal(true);
  };

  const closeUserModal = () => {
    setSelectedUser(null);
    setShowModal(false);
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="User Management" />
      <SettingsLayout>
        <HeadingSmall
          title="User Management"
          description="Manage users, roles, and permissions."
        />

        <div className="flex items-center justify-between mb-4">
          <Input
            placeholder="Search users..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
          <Button onClick={() => openUserModal(null)}>Add User</Button>
        </div>

        <Table>
        <TableHead>
          <TableRow>
            <TableHeader>
              <input type="checkbox" />
            </TableHeader>
            <TableHeader>Name</TableHeader>
            <TableHeader>Email</TableHeader>
            <TableHeader>Role</TableHeader>
            <TableHeader>Status</TableHeader>
            <TableHeader>Actions</TableHeader>
          </TableRow>
        </TableHead>
        <TableBody>
          {users.map((user) => (
            <TableRow key={user.id}>
              <TableCell>
                <input type="checkbox" value={user.id} />
              </TableCell>
              <TableCell>{user.name}</TableCell>
              <TableCell>{user.email}</TableCell>
              <TableCell>{user.role}</TableCell>
              <TableCell>{user.status}</TableCell>
              <TableCell>
                <button className="text-blue-500" onClick={() => editUser(user.id)}>Edit</button>
                <button className="text-red-500 ml-2" onClick={() => deleteUser(user.id)}>Delete</button>
              </TableCell>
            </TableRow>
          ))}
        </TableBody>
      </Table>

        {/* User Modal */}
        {showModal && (
          <Modal onClose={closeUserModal} title={selectedUser ? 'Edit User' : 'Add User'}>
          <form>
            <div className="space-y-4">
              <div>
                <Label>Name</Label>
                <Input defaultValue={selectedUser?.name || ''} placeholder="Full name" />
              </div>
              <div>
                <Label>Email</Label>
                <Input defaultValue={selectedUser?.email || ''} placeholder="Email address" />
              </div>
              <div>
                <Label>Role</Label>
                <Select defaultValue={selectedUser?.role || ''} onValueChange={(value) => setSelectedRole(value)}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select a role" />
                  </SelectTrigger>
                  <SelectContent>
                    {roles.map((role) => (
                      <SelectItem key={role} value={role}>
                        {role}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div>
                <Label>Permissions</Label>
                {permissions.map((permission) => (
                  <div key={permission} className="flex items-center">
                    <Checkbox defaultChecked={selectedUser?.permissions.includes(permission)} />
                    <span className="ml-2">{permission}</span>
                  </div>
                ))}
              </div>
              <Button type="submit">Save</Button>
            </div>
          </form>
        </Modal>
        )}
      </SettingsLayout>
    </AppLayout>
  );
}
