import Avatar from '@/Components/Dashboard/Avatar';
import ChartCard from '@/Components/Dashboard/ChartCard';
import ComingSoon from '@/Components/Dashboard/ComingSoon';
import { EmptyState, Skeleton } from '@/Components/Dashboard/States';

export default function TopContacts({ data }) {
    return (
        <ChartCard
            title="Top Contacts"
            caption="Your key contacts"
            action={
                data && data.items.length > 0 ? (
                    <ComingSoon
                        tooltip="Contacts module coming soon"
                        variant="ghost"
                        align="end"
                        className="px-3! py-1.5! text-xs whitespace-nowrap"
                    >
                        View all
                    </ComingSoon>
                ) : null
            }
        >
            {data === null ? (
                <Skeleton className="h-10 w-full" />
            ) : data.items.length === 0 ? (
                <EmptyState>No contacts yet</EmptyState>
            ) : (
                <ul className="flex items-center -space-x-2 rtl:space-x-reverse" aria-label="Top contacts">
                    {data.items.map((contact) => (
                        <li key={contact.id} title={contact.name}>
                            <Avatar name={contact.name} className="ring-2 ring-white" />
                            <span className="sr-only">{contact.name}</span>
                        </li>
                    ))}
                    {data.more > 0 && (
                        <li>
                            <span className="inline-flex h-10 w-10 items-center justify-center rounded-full bg-slate-100 text-xs font-bold text-slate-600 ring-2 ring-white">
                                <span aria-hidden="true">+{data.more}</span>
                                <span className="sr-only">and {data.more} more contacts</span>
                            </span>
                        </li>
                    )}
                </ul>
            )}
        </ChartCard>
    );
}
