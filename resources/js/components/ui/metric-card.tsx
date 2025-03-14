import React from 'react';

interface MetricCardProps {
  title: string;
  value: string | number;
}

const MetricCard: React.FC<MetricCardProps> = ({ title, value }) => {
  return (
    <div>
      <hr className="w-full border-t border-zinc-950/10 dark:border-white/10" />
      <div className="mt-6 text-lg font-medium sm:text-sm/6">{title}</div>
      <div className="mt-3 text-3xl font-semibold sm:text-2xl/8">{value}</div>
    </div>
  );
};

export default MetricCard;