import React from "react";

interface TableProps {
    headers: string[];
    data: (string | number | React.ReactNode)[][];
}

const Table: React.FC<TableProps> = ({ headers, data }) => {
    return (
        <div className="inline-block min-w-full align-middle sm:px-(--gutter) overflow-x-auto">
            <table className="w-full min-w-full text-left text-sm/6 text-zinc-950 dark:text-white">
                <thead className="text-zinc-500 dark:text-zinc-400">
                    <tr className="">
                        {headers.map((header, index) => (
                            <th key={index} className="border-b border-zinc-950/10 px-4 py-2 font-medium first:pl-(--gutter, --spacing(2)) last:pr-(--gutter, spacing(2)) dark:border-b-white/10 sm:first:pl-1 sm:last:pr-1">
                                {header}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {data.length > 0 ? (
                        data.map((row, rowIndex) => (
                            <tr key={rowIndex} className="has-[[data-rowolink][data-focus]]:outline-2 has-[[data-rowolink][data-focus]]:-ouline-oofset-3 has-[[data-rowolink][data-focus]]:outline-blue-500 dark:focus-within:bg-white/[2.5%] hover:bg-zinc-960/[2.5%] dark:hover:bg-white/[2.5%]">
                                {row.map((cell, cellIndex) => (
                                    <td key={cellIndex} className="relative first:pl-(--gutter, --spacing(2)) last:pr-(--gutter, spacing(2)) border-b border-zinc-950/5 dark:border-white/5 py-4 sm:first:pl-1 sm:last:pr-1">
                                        {cell}
                                    </td>
                                ))}
                            </tr>
                        ))
                    ) : (
                        <tr>
                            <td colSpan={headers.length} className="p-4 text-center text-zinc-500">
                                No data available
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
};

export default Table;
