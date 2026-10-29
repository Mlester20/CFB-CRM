/** Soft, large-radius card used by the dashboard widgets. */
export default function Card({ className = '', as: Tag = 'section', children, ...props }) {
    return (
        <Tag
            className={`rounded-2xl bg-white p-5 shadow-[0_2px_16px_-4px_rgba(30,58,138,0.10)] ring-1 ring-slate-100 ${className}`}
            {...props}
        >
            {children}
        </Tag>
    );
}
