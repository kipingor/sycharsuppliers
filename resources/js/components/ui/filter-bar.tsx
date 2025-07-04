import { useState, useEffect } from 'react';
import { usePage, router } from '@inertiajs/react';
import { usePrevious } from 'react-use';
import { Select, SelectTrigger, SelectValue, SelectContent, SelectItem } from '@/components/ui/select';
import pickBy from 'lodash/pickBy';
import { ChevronDown } from 'lucide-react';
import FieldGroup from '@/components/ui/field-group';
import { Input } from '@/components/ui/input';

export default function FilterBar() {
    const { filters } = usePage<{
        filters: { role?: string; search?: string; trashed?: string };
    }>().props;

    const [opened, setOpened] = useState(false);

    const [values, setValues] = useState({
        role: filters.role || '', // role is used only on users page
        search: filters.search || '',
        trashed: filters.trashed || ''
    });

    const prevValues = usePrevious(values);


    function reset() {
        setValues({
            role: '',
            search: '',
            trashed: ''
        });
    }

    useEffect(() => {
        // https://reactjs.org/docs/hooks-faq.html#how-to-get-the-previous-props-or-state
        if (prevValues) {
            const query = Object.keys(pickBy(values)).length ? pickBy(values) : {};

            router.get(route(route().current() as string), query, {
                replace: true,
                preserveState: true
            });
        }
    }, [values]);

    function handleChange(
        e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement>
    ) {
        const name = e.target.name;
        const value = e.target.value;

        setValues(values => ({
            ...values,
            [name]: value
        }));

        if (opened) setOpened(false);
    }

    return (
        <div className="flex items-center w-full max-w-md mr-4">
            <div className="relative flex bg-white rounded shadow">
                <div
                    style={{ top: '100%' }}
                    className={`absolute ${opened ? '' : 'hidden'}`}
                >
                    <div
                        onClick={() => setOpened(false)}
                        className="fixed inset-0 z-20 bg-black opacity-25"
                    />
                    <div className="relative z-30 w-64 px-4 py-6 mt-2 bg-white rounded shadow-lg space-y-4">
                        {filters.hasOwnProperty('role') && (
                            <FieldGroup label="Role" name="role">
                                <Select value={values.role} onValueChange={(value) => handleChange({ target: { name: 'role', value } } as React.ChangeEvent<HTMLSelectElement>)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select role" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="">All</SelectItem>
                                        <SelectItem value="user">User</SelectItem>
                                        <SelectItem value="owner">Owner</SelectItem>
                                    </SelectContent>
                                </Select>
                            </FieldGroup>
                        )}
                        <FieldGroup label="Trashed" name="trashed">
                            <Select value={values.trashed} onValueChange={(value) => handleChange({ target: { name: 'trashed', value } } as React.ChangeEvent<HTMLSelectElement>)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Select trashed" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">All</SelectItem>
                                    <SelectItem value="with">With Trashed</SelectItem>
                                    <SelectItem value="only">Only Trashed</SelectItem>
                                </SelectContent>
                            </Select>
                        </FieldGroup>
                    </div>
                </div>
                <button
                    onClick={() => setOpened(true)}
                    className="px-4 border-r rounded-l md:px-6 hover:bg-gray-100 focus:outline-none focus:border-white focus:ring-2 focus:ring-indigo-400 focus:z-10"
                >
                    <div className="flex items-center">
                        <span className="hidden text-gray-700 md:inline">Filter</span>
                        <ChevronDown size={14} strokeWidth={3} className="md:ml-2" />
                    </div>
                </button>
                <Input
                    name="search"
                    placeholder="Search…"
                    autoComplete="off"
                    value={values.search}
                    onChange={handleChange}
                    className="border-0 rounded-l-none focus:ring-2"
                />
            </div>
            <button
                onClick={reset}
                className="ml-3 text-sm text-gray-600 hover:text-gray-700 focus:text-indigo-700 focus:outline-none"
                type="button"
            >
                Reset
            </button>
        </div>
    );
}