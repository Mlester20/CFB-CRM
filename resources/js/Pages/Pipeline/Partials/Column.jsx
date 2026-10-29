import { Button } from '@/Components/Ui';
import { formatNumber, formatPhpCompact } from '@/Utils/format';
import { SortableContext, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { useDroppable } from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import PipelineCard from './PipelineCard';

// Static class names so Tailwind can see them.
const tones = {
    new: { border: 'border-t-sky-500', dot: 'bg-sky-500' },
    qualified: { border: 'border-t-violet-500', dot: 'bg-violet-500' },
    proposal: { border: 'border-t-amber-500', dot: 'bg-amber-500' },
    won: { border: 'border-t-green-500', dot: 'bg-green-500' },
    lost: { border: 'border-t-rose-500', dot: 'bg-rose-500' },
};

function SortableCard({ card, stages, canMove, reorder, reducedMotion, hrefFor, onMove }) {
    const sortable = useSortable({
        id: card.id,
        // With a search or filter on, a card can only be dropped on a column, never between
        // cards: the column is then the only drop target, so the card goes to its top.
        disabled: canMove ? { droppable: !reorder } : true,
        data: { stage: card.stage },
        transition: reducedMotion ? null : { duration: 180, easing: 'cubic-bezier(0.25, 1, 0.5, 1)' },
    });

    const { setNodeRef, transform, transition, isDragging } = sortable;

    return (
        <li ref={setNodeRef} style={{ transform: CSS.Transform.toString(transform), transition }} className="list-none">
            <PipelineCard
                card={card}
                href={hrefFor(card)}
                stages={stages}
                canMove={canMove}
                onMove={onMove}
                sortable={sortable}
                dragging={isDragging}
            />
        </li>
    );
}

/**
 * One column: colored top border, dot, stage name, count pill, the total (money
 * only when the server sent it), the cards and "Show more".
 */
export default function Column({
    column,
    stages,
    canMove,
    reorder,
    reducedMotion,
    highlighted,
    loadingMore,
    hrefFor,
    onMove,
    onShowMore,
}) {
    const { setNodeRef } = useDroppable({ id: column.stage, disabled: !canMove });
    const tone = tones[column.stage];
    const hasValue = column.value !== undefined;
    const headingId = `column-${column.stage}`;

    return (
        <section
            aria-labelledby={headingId}
            className={`flex w-[82%] shrink-0 snap-start flex-col rounded-2xl border-t-4 bg-slate-100/70 ring-1 transition-colors motion-reduce:transition-none sm:w-auto sm:min-w-64 sm:flex-1 ${
                tone.border
            } ${highlighted ? 'bg-primary-50 ring-2 ring-primary-400' : 'ring-slate-200/70'}`}
        >
            <header className="px-3 pb-2 pt-3">
                <div className="flex items-center gap-2">
                    <span className={`h-2.5 w-2.5 shrink-0 rounded-full ${tone.dot}`} aria-hidden="true" />
                    <h2 id={headingId} className="truncate text-sm font-bold text-gray-900">
                        {column.label}
                    </h2>
                    <span className="rounded-full bg-white px-2 text-xs font-bold text-slate-600 ring-1 ring-slate-200">
                        {formatNumber(column.count)}
                    </span>
                </div>
                {hasValue && (
                    <p className="mt-1 text-xs font-medium text-gray-500" title={`${column.value}`}>
                        {formatPhpCompact(column.value)}
                    </p>
                )}
            </header>

            <div ref={setNodeRef} className="min-h-24 flex-1 px-2 pb-2">
                <SortableContext items={column.cards.map((card) => card.id)} strategy={verticalListSortingStrategy}>
                    <ul className="space-y-2" aria-label={`${column.label} leads`}>
                        {column.cards.map((card) => (
                            <SortableCard
                                key={card.id}
                                card={card}
                                stages={stages}
                                canMove={canMove}
                                reorder={reorder}
                                reducedMotion={reducedMotion}
                                hrefFor={hrefFor}
                                onMove={onMove}
                            />
                        ))}
                    </ul>
                </SortableContext>

                {column.cards.length === 0 && (
                    <p className="rounded-xl border border-dashed border-slate-300 px-3 py-6 text-center text-xs text-gray-500">
                        {highlighted ? 'Drop here' : 'No leads'}
                    </p>
                )}

                {column.has_more && (
                    <Button variant="secondary" className="mt-2 w-full" onClick={() => onShowMore(column.stage)} disabled={loadingMore}>
                        {loadingMore ? 'Loading…' : 'Show more'}
                    </Button>
                )}
            </div>
        </section>
    );
}
