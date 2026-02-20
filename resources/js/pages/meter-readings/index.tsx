import { Head, usePage, router, Link } from "@inertiajs/react";
import AppLayout from "@/layouts/app-layout";
import { type BreadcrumbItem, type SharedData } from "@/types";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { PlusCircle, EllipsisVertical, Eye, Pencil, Trash, TrendingUp, Download } from "lucide-react";
import { 
    Table, 
    TableBody, 
    TableCell, 
    TableHead, 
    TableRow 
} from "@/components/table";
import { 
    DropdownMenuItem, 
    DropdownMenuContent, 
    DropdownMenuTrigger, 
    DropdownMenu 
} from "@/components/ui/dropdown-menu";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { debounce } from "lodash";
import Pagination from "@/components/pagination";
import { Badge } from "@/components/ui/badge";

interface Meter {
    id: number;
    meter_number: string;
    meter_name: string;
}

interface MeterReading {
    id: number;
    meter_id: number;
    reading: number;
    reading_value: number;  // Alias
    reading_date: string;
    reading_type: 'actual' | 'estimated' | 'calculated';
    notes?: string;
    consumption: number;
    meter: {
        id: number;
        meter_number: string;
        meter_name: string;
        account?: {
            id: number;
            name: string;
            account_number: string;
        };
    };
    reader?: {
        id: number;
        name: string;
    };
    created_at: string;
}

interface MeterReadingsPageProps {
    readings: {
        data: MeterReading[];
        links: any[];
    };
    filters: {
        search?: string;
        meter_id?: number;
        reading_type?: string;
        from_date?: string;
        to_date?: string;
    };
    meters: Array<{
        id: number;
        meter_number: string;
        meter_name: string;
    }>;
    can: {
        create: boolean;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Meter Readings', href: route('meter-readings.index') },
];

const readingTypes = [
    { value: '', label: 'All Types' },
    { value: 'regular', label: 'Regular' },
    { value: 'estimated', label: 'Estimated' },
    { value: 'correction', label: 'Correction' },
];

export default function MeterReadings() {
    const { readings, filters, meters, can, auth } = usePage<SharedData & MeterReadingsPageProps>().props;
    const [search, setSearch] = useState<string>(filters.search || "");
    const [selectedMeter, setSelectedMeter] = useState<string>(filters.meter_id?.toString() || "");
    const [selectedType, setSelectedType] = useState<string>(filters.reading_type || "");

    const handleSearch = debounce((query: string) => {
        router.get(route('meter-readings.index'), { 
            search: query,
            meter_id: selectedMeter || undefined,
            reading_type: selectedType || undefined,
        }, {
            only: ['readings'],
            replace: true,
            preserveState: true,
        });
    }, 300);

    const handleFilterChange = (key: string, value: string) => {
        const params: any = { search };
        
        if (key === 'meter_id') {
            setSelectedMeter(value);
            if (value) params.meter_id = value;
        } else if (key === 'reading_type') {
            setSelectedType(value);
            if (value) params.reading_type = value;
        }

        if (selectedMeter && key !== 'meter_id') params.meter_id = selectedMeter;
        if (selectedType && key !== 'reading_type') params.reading_type = selectedType;

        router.get(route('meter-readings.index'), params, {
            only: ['readings'],
            replace: true,
            preserveState: true,
        });
    };

    const handleDelete = (id: number) => {
        if (confirm("Are you sure you want to delete this reading? This action cannot be undone.")) {
            router.delete(route('meter-readings.destroy', id), {
                preserveScroll: true,
            });
        }
    };

    const readingTypeClasses: Record<string, string> = {
        regular: "bg-blue-400/20 text-blue-700",
        estimated: "bg-yellow-400/20 text-yellow-700",
        correction: "bg-purple-400/20 text-purple-700",
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Meter Readings" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl p-4">
                <div className="flex justify-between items-center mb-4">
                    <div>
                        <h1 className="text-2xl font-bold flex items-center gap-2">
                            <TrendingUp className="h-6 w-6" />
                            Meter Readings
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Track and manage water meter readings
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="outline"
                            onClick={() => router.get(route('meter-readings.export'))}
                        >
                            <Download size={16} className="mr-2" /> Export
                        </Button>
                        {can.create && (
                            <Link href={route('meter-readings.create')}>
                                <Button className="flex items-center gap-2">
                                    <PlusCircle size={18} /> Record Reading
                                </Button>
                            </Link>
                        )}
                    </div>
                </div>

                {/* Filters */}
                <div className="flex gap-2 mb-4">
                    <Input
                        type="text"
                        placeholder="Search readings..."
                        value={search}
                        onChange={(e) => {
                            setSearch(e.target.value);
                            handleSearch(e.target.value);
                        }}
                        className="max-w-xs"
                    />
                    <Select
                        value={selectedMeter}
                        onValueChange={(value) => handleFilterChange('meter_id', value)}
                    >
                        <SelectTrigger className="w-[200px]">
                            <SelectValue placeholder="All Meters" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="All">All Meters</SelectItem>
                            {meters.map((meter) => (
                                <SelectItem key={meter.id} value={meter.id.toString()}>
                                    {meter.meter_number}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {/* <Select
                        value={selectedType}
                        onValueChange={(value) => handleFilterChange('reading_type', value)}
                    >
                        <SelectTrigger className="w-[180px]">
                            <SelectValue placeholder="Reading Type" />
                        </SelectTrigger>
                        <SelectContent>
                            {readingTypes.map((type) => (
                                <SelectItem key={type.value} value={type.value}>
                                    {type.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select> */}
                </div>

                {readings.data.length === 0 ? (
                    <div className="text-center py-12">
                        <TrendingUp className="mx-auto h-12 w-12 text-gray-400" />
                        <p className="text-gray-500 dark:text-gray-400 mt-4">No readings found.</p>
                        {can.create && (
                            <Link href={route('meter-readings.create')}>
                                <Button className="mt-4">Record Your First Reading</Button>
                            </Link>
                        )}
                    </div>
                ) : (
                    <>
                        <Table>
                            <TableHead>
                                <TableRow>
                                    {["ID", "Meter", "Date", "Reading", "Consumption", "Type", "Actions"].map((header) => (
                                        <TableCell key={header} className="text-left font-medium">
                                            {header}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            </TableHead>
                            <TableBody>
                                {readings.data.map((reading) => (
                                    <TableRow key={reading.id}>
                                        <TableCell className="text-xs">{reading.id}</TableCell>
                                        <TableCell className="text-xs">
                                            <div>
                                                <Link
                                                    href={route('meters.show', reading.meter_id)}
                                                    className="font-medium text-blue-600 hover:underline"
                                                >
                                                    {reading.meter?.meter_number || 'N/A'}
                                                </Link>
                                                {reading.meter?.meter_name && (
                                                    <p className="text-gray-500 text-xs">
                                                        {reading.meter.meter_name}
                                                    </p>
                                                )}
                                            </div>
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {new Date(reading.reading_date).toLocaleDateString()}
                                        </TableCell>
                                        <TableCell className="text-xs font-medium">
                                            {reading.reading_value} m³
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {reading.consumption !== undefined && reading.consumption !== null
                                                ? `${reading.consumption} m³`
                                                : '-'}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            <Badge className={readingTypeClasses[reading.reading_type]}>
                                                {reading.reading_type}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button variant="ghost" size="sm">
                                                        <EllipsisVertical size={16} />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem asChild>
                                                        <Link href={route('meter-readings.show', reading.id)}>
                                                            <Eye size={16} className="mr-2" /> View Details
                                                        </Link>
                                                    </DropdownMenuItem>
                                                    
                                                        <DropdownMenuItem asChild>
                                                            <Link href={route('meter-readings.edit', reading.id)}>
                                                                <Pencil size={16} className="mr-2" /> Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem 
                                                            onClick={() => handleDelete(reading.id)}
                                                            className="text-red-600"
                                                        >
                                                            <Trash size={16} className="mr-2" /> Delete
                                                        </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>

                        <Pagination links={readings.links} />
                    </>
                )}
            </div>
        </AppLayout>
    );
}