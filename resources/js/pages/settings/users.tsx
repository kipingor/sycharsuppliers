import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Checkbox } from '@/components/ui/checkbox';
import { Table, TableHead, TableBody, TableRow, TableHeader, TableCell } from '@/components/ui/table';
import Modal from "@/components/ui/modal";
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from "@/components/ui/select";
import HeadingSmall from '@/components/heading-small';
import { Badge } from '@/components/ui/badge';
import { Trash2, Edit, Plus, UserPlus, Shield } from 'lucide-react';

const breadcrumbs = [
  { title: 'User Management', href: '/settings/users' },
];

interface User {
  id: number;
  name: string;
  email: string;
  status: string;
  roles: string[];
  permissions: string[];
  created_at: string;
}

interface Props {
  users: User[];
  roles: string[];
  permissions: string[];
}

export default function UserManagement({ users, roles, permissions }: Props) {
  const [search, setSearch] = useState('');
  const [showModal, setShowModal] = useState(false);
  const [editingUser, setEditingUser] = useState<User | null>(null);

  const { data, setData, post, patch, delete: destroy, processing, errors, reset } = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as string[],
    permissions: [] as string[],
  });

  const filteredUsers = users.filter((user) =>
    user.name.toLowerCase().includes(search.toLowerCase()) ||
    user.email.toLowerCase().includes(search.toLowerCase())
  );

  const openCreateModal = () => {
    reset();
    setEditingUser(null);
    setShowModal(true);
  };

  const openEditModal = (user: User) => {
    setEditingUser(user);
    setData({
      name: user.name,
      email: user.email,
      password: '',
      password_confirmation: '',
      roles: user.roles,
      permissions: user.permissions,
    });
    setShowModal(true);
  };

  const closeModal = () => {
    setShowModal(false);
    setEditingUser(null);
    reset();
  };

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    
    if (editingUser) {
      patch(route('settings.users.update', editingUser.id), {
        onSuccess: () => closeModal(),
      });
    } else {
      post(route('settings.users.store'), {
        onSuccess: () => closeModal(),
      });
    }
  };

  const handleDelete = (userId: number) => {
    if (confirm('Are you sure you want to delete this user?')) {
      destroy(route('settings.users.destroy', userId));
    }
  };

  const handleRoleToggle = (role: string) => {
    setData('roles', data.roles.includes(role)
      ? data.roles.filter(r => r !== role)
      : [...data.roles, role]
    );
  };

  const handlePermissionToggle = (permission: string) => {
    setData('permissions', data.permissions.includes(permission)
      ? data.permissions.filter(p => p !== permission)
      : [...data.permissions, permission]
    );
  };

  return (
    <AppLayout breadcrumbs={breadcrumbs}>
      <Head title="User Management" />
      <SettingsLayout>
        <HeadingSmall
          title="User Management"
          description="Manage users, roles, and permissions."
        />

        <div className="flex items-center justify-between gap-4 mb-6">
          <Input
            placeholder="Search users..."
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="max-w-sm"
          />
          <Button onClick={openCreateModal}>
            <UserPlus className="h-4 w-4 mr-2" />
            Add User
          </Button>
        </div>

        <div className="rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Name</TableHead>
                <TableHead>Email</TableHead>
                <TableHead>Roles</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Created</TableHead>
                <TableHead>Actions</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {filteredUsers.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center py-6 text-muted-foreground">
                    No users found
                  </TableCell>
                </TableRow>
              ) : (
                filteredUsers.map((user) => (
                  <TableRow key={user.id}>
                    <TableCell className="font-medium">{user.name}</TableCell>
                    <TableCell>{user.email}</TableCell>
                    <TableCell>
                      <div className="flex flex-wrap gap-1">
                        {user.roles && user.roles.length > 0 ? (
                          user.roles.map((role) => (
                            <Badge key={role} variant="secondary">
                              {role}
                            </Badge>
                          ))
                        ) : (
                          <span className="text-sm text-muted-foreground">No roles</span>
                        )}
                      </div>
                    </TableCell>
                    <TableCell>
                      <Badge variant={user.status === 'Active' ? 'default' : 'outline'}>
                        {user.status}
                      </Badge>
                    </TableCell>
                    <TableCell>{user.created_at}</TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => openEditModal(user)}
                        >
                          <Edit className="h-4 w-4" />
                        </Button>
                        <Button
                          variant="ghost"
                          size="sm"
                          onClick={() => handleDelete(user.id)}
                          className="text-red-500 hover:text-red-700"
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </div>

        {/* User Modal */}
        {showModal && (
          <Modal 
            onClose={closeModal} 
            title={editingUser ? 'Edit User' : 'Add User'}
          >
            <form onSubmit={handleSubmit} className="space-y-4">
              {/* Name */}
              <div>
                <Label htmlFor="name">Name *</Label>
                <Input
                  id="name"
                  value={data.name}
                  onChange={(e) => setData('name', e.target.value)}
                  placeholder="Full name"
                  className="mt-1"
                />
                {errors.name && <p className="mt-1 text-sm text-red-600">{errors.name}</p>}
              </div>

              {/* Email */}
              <div>
                <Label htmlFor="email">Email *</Label>
                <Input
                  id="email"
                  type="email"
                  value={data.email}
                  onChange={(e) => setData('email', e.target.value)}
                  placeholder="Email address"
                  className="mt-1"
                />
                {errors.email && <p className="mt-1 text-sm text-red-600">{errors.email}</p>}
              </div>

              {/* Password */}
              <div>
                <Label htmlFor="password">
                  Password {!editingUser && '*'}
                  {editingUser && <span className="text-sm font-normal text-muted-foreground"> (leave blank to keep current)</span>}
                </Label>
                <Input
                  id="password"
                  type="password"
                  value={data.password}
                  onChange={(e) => setData('password', e.target.value)}
                  placeholder="Enter password"
                  className="mt-1"
                />
                {errors.password && <p className="mt-1 text-sm text-red-600">{errors.password}</p>}
              </div>

              {/* Password Confirmation */}
              <div>
                <Label htmlFor="password_confirmation">Confirm Password {!editingUser && '*'}</Label>
                <Input
                  id="password_confirmation"
                  type="password"
                  value={data.password_confirmation}
                  onChange={(e) => setData('password_confirmation', e.target.value)}
                  placeholder="Confirm password"
                  className="mt-1"
                />
              </div>

              {/* Roles */}
              <div>
                <Label className="flex items-center gap-2 mb-2">
                  <Shield className="h-4 w-4" />
                  Roles
                </Label>
                <div className="space-y-2 border rounded-md p-3">
                  {roles.map((role) => (
                    <div key={role} className="flex items-center gap-2">
                      <Checkbox
                        id={`role-${role}`}
                        checked={data.roles.includes(role)}
                        onCheckedChange={() => handleRoleToggle(role)}
                      />
                      <label
                        htmlFor={`role-${role}`}
                        className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                      >
                        {role}
                      </label>
                    </div>
                  ))}
                </div>
              </div>

              {/* Permissions */}
              <div>
                <Label className="mb-2 block">Permissions</Label>
                <div className="space-y-2 border rounded-md p-3 max-h-48 overflow-y-auto">
                  {permissions.map((permission) => (
                    <div key={permission} className="flex items-center gap-2">
                      <Checkbox
                        id={`permission-${permission}`}
                        checked={data.permissions.includes(permission)}
                        onCheckedChange={() => handlePermissionToggle(permission)}
                      />
                      <label
                        htmlFor={`permission-${permission}`}
                        className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                      >
                        {permission}
                      </label>
                    </div>
                  ))}
                </div>
              </div>

              {/* Actions */}
              <div className="flex justify-end gap-2 pt-4">
                <Button type="button" variant="outline" onClick={closeModal}>
                  Cancel
                </Button>
                <Button type="submit" disabled={processing}>
                  {processing ? 'Saving...' : (editingUser ? 'Update User' : 'Create User')}
                </Button>
              </div>
            </form>
          </Modal>
        )}
      </SettingsLayout>
    </AppLayout>
  );
}