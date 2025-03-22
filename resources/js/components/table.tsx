import React from "react";

export const Table: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <div className="inline-block min-w-full align-middle sm:px-[--gutter] overflow-x-auto">
    <table className="w-full min-w-full text-left text-sm/6 text-[--color-foreground] dark:text-[--color-foreground]">
      {children}
    </table>
  </div>
);

export const TableHead: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <thead className="text-[--color-muted-foreground] dark:text-[--color-muted-foreground]">
    {children}
  </thead>
);

export const TableBody: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <tbody>{children}</tbody>
);

export const TableRow: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <tr className="hover:bg-[--color-accent]/[2.5%] dark:hover:bg-[--color-accent]/[2.5%] focus-within:outline-2 focus-within:outline-offset-3 focus-within:outline-[--color-ring] dark:focus-within:bg-[--color-accent]/[2.5%]">
    {children}
  </tr>
);

export const TableHeader: React.FC<{ children: React.ReactNode }> = ({ children }) => (
  <th className="border-b border-[--color-border] px-4 py-2 font-medium dark:border-[--color-border] sm:first:pl-1 sm:last:pr-1">
    {children}
  </th>
);

export const TableCell: React.FC<{ children: React.ReactNode; className?: string }> = ({
  children,
  className = "",
}) => (
  <td
    className={`relative border-b border-[--color-border] dark:border-[--color-border] py-4 sm:first:pl-1 sm:last:pr-1 ${className}`}
  >
    {children}
  </td>
);