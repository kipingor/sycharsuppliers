import { router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

interface PaginationProps {
  links: PaginationItem[];
}

interface PaginationItem {
  url: null | string;
  label: string;
  active: boolean;
}

export default function Pagination({ links = [] }: PaginationProps) {
  // Hide pagination if there are only 3 links (no previous/next pages)
  if (links.length === 3) return null;

  return (
    <div className="mt-4 flex justify-center gap-2">
      {links.map((link, index) => (
        <Button
          key={index}
          variant={link.active ? 'default' : 'outline'}
          onClick={() => link.url && router.get(link.url)}
          disabled={!link.url}
          dangerouslySetInnerHTML={{ __html: link.label }}
        />
      ))}
    </div>
  );
}