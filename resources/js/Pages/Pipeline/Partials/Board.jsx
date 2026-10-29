import {
    closestCorners,
    DndContext,
    DragOverlay,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { arrayMove, sortableKeyboardCoordinates } from '@dnd-kit/sortable';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import Column from './Column';
import PipelineCard from './PipelineCard';

const isColumn = (cols, id) => cols.some((column) => column.stage === id);
const columnOf = (cols, id) => cols.find((column) => column.stage === id) ?? cols.find((column) => column.cards.some((card) => card.id === id));

function usePrefersReducedMotion() {
    const [reduced, setReduced] = useState(() => window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false);

    useEffect(() => {
        const query = window.matchMedia?.('(prefers-reduced-motion: reduce)');
        if (!query) return undefined;

        const update = () => setReduced(query.matches);
        query.addEventListener('change', update);

        return () => query.removeEventListener('change', update);
    }, []);

    return reduced;
}

/**
 * Takes a card out of its column and puts it at `index` of `stage`, adjusting the
 * counts and (when present) the money totals of the columns involved.
 */
function relocate(cols, id, stage, index) {
    const from = columnOf(cols, id);
    const card = from.cards.find((c) => c.id === id);

    return cols.map((column) => {
        if (column.stage === from.stage && column.stage === stage) {
            const cards = column.cards.filter((c) => c.id !== id);
            cards.splice(Math.min(index, cards.length), 0, card);

            return { ...column, cards };
        }

        if (column.stage === from.stage) {
            return {
                ...column,
                count: column.count - 1,
                ...(column.value !== undefined ? { value: column.value - card.value } : {}),
                cards: column.cards.filter((c) => c.id !== id),
            };
        }

        if (column.stage === stage) {
            const cards = [...column.cards];
            cards.splice(Math.min(index, cards.length), 0, { ...card, stage });

            return {
                ...column,
                count: column.count + 1,
                ...(column.value !== undefined ? { value: column.value + card.value } : {}),
                cards,
            };
        }

        return column;
    });
}

const errorMessage = (error) => {
    switch (error.response?.status) {
        case 403:
            return 'You are not allowed to move deals.';
        case 404:
            return 'That lead is no longer available to you.';
        case 419:
            return 'Your session expired. Reload the page and try again.';
        case 429:
            return 'You are moving cards too fast. Try again in a moment.';
        default:
            return error.response?.data?.message ?? 'Could not move the card. It was put back.';
    }
};

/**
 * The drag-and-drop board. The columns are kept in local state so a drop shows at once;
 * the move is then sent to the server, and on failure the board snaps back and says so.
 */
export default function Board({ board, stages, canMove, filters, filtersActive, hrefFor, onStats, onError }) {
    const reducedMotion = usePrefersReducedMotion();
    const [cols, setCols] = useState(board);
    const [activeId, setActiveId] = useState(null);
    const [overStage, setOverStage] = useState(null);
    const [loadingMore, setLoadingMore] = useState(null);

    const colsRef = useRef(cols);
    colsRef.current = cols;
    const snapshot = useRef(null);
    const queue = useRef(Promise.resolve());

    // A fresh board from the server (filters changed, reload after an error) replaces the local one.
    useEffect(() => {
        setCols(board);
    }, [board]);

    const sensors = useSensors(
        useSensor(PointerSensor, { activationConstraint: { distance: 5 } }),
        useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }),
    );

    const activeCard = activeId === null ? null : columnOf(cols, activeId)?.cards.find((card) => card.id === activeId);
    const reorder = !filtersActive;

    // -- sending a move ---------------------------------------------------------------

    const send = (id, stage, position, before) => {
        // One move at a time, so the server sees them in the order they were made.
        queue.current = queue.current.then(async () => {
            try {
                const { data } = await window.axios.patch(route('pipeline.move', { lead: id }), {
                    stage,
                    position,
                    search: filters.search || undefined,
                    priority: filters.priority || undefined,
                });

                setCols((current) =>
                    current.map((column) => {
                        const fresh = data.columns[column.stage];

                        return fresh ? { ...column, count: fresh.count, ...(fresh.value !== undefined ? { value: fresh.value } : {}) } : column;
                    }),
                );
                onStats(data.stats);
            } catch (error) {
                setCols(before);
                onError(errorMessage(error));
                // Later queued moves were made on top of this one: take the server's word for the board.
                router.reload({ only: ['board', 'stats'] });
            }
        });
    };

    const commit = (id, stage, index) => {
        const before = snapshot.current ?? colsRef.current;
        snapshot.current = null;

        setCols(relocate(before, id, stage, index));
        send(id, stage, index, before);
    };

    // The "Move to" menu: keyboard and touch friendly, always to the top of the column.
    const moveTo = (id, stage) => {
        snapshot.current = colsRef.current;
        commit(id, stage, 0);
    };

    // -- dragging -------------------------------------------------------------------

    const onDragStart = ({ active }) => {
        snapshot.current = colsRef.current;
        setActiveId(active.id);
        setOverStage(columnOf(colsRef.current, active.id)?.stage ?? null);
    };

    const onDragOver = ({ active, over }) => {
        if (!over) {
            setOverStage(null);

            return;
        }

        const target = columnOf(colsRef.current, over.id);
        setOverStage(target?.stage ?? null);

        const from = columnOf(colsRef.current, active.id);
        if (!from || !target || from.stage === target.stage) return;

        // Show the card in the column it is over, so the cards make room for it.
        let index = 0;
        if (reorder) {
            if (isColumn(colsRef.current, over.id)) {
                index = target.cards.length;
            } else {
                const overIndex = target.cards.findIndex((card) => card.id === over.id);
                const below = active.rect.current.translated && active.rect.current.translated.top > over.rect.top + over.rect.height / 2;
                index = overIndex + (below ? 1 : 0);
            }
        }

        setCols((current) => relocate(current, active.id, target.stage, index));
    };

    const onDragCancel = () => {
        if (snapshot.current) setCols(snapshot.current);
        snapshot.current = null;
        setActiveId(null);
        setOverStage(null);
    };

    const onDragEnd = ({ active, over }) => {
        const start = snapshot.current;
        setActiveId(null);
        setOverStage(null);

        if (!start || !over) {
            onDragCancel();

            return;
        }

        const origin = columnOf(start, active.id);
        const live = colsRef.current;
        const target = columnOf(live, over.id);
        const current = columnOf(live, active.id);
        if (!origin || !target || !current) {
            onDragCancel();

            return;
        }

        const oldIndex = origin.cards.findIndex((card) => card.id === active.id);

        if (current.stage === origin.stage) {
            // Reordering inside the column: not with a search or filter on (the order of a partial list means nothing).
            if (!reorder) return onDragCancel();

            const overIndex = isColumn(live, over.id) ? current.cards.length - 1 : current.cards.findIndex((card) => card.id === over.id);
            if (overIndex === oldIndex || overIndex < 0) return onDragCancel();

            snapshot.current = null;
            setCols(start.map((column) => (column.stage === origin.stage ? { ...column, cards: arrayMove(column.cards, oldIndex, overIndex) } : column)));
            send(active.id, origin.stage, overIndex, start);

            return undefined;
        }

        // To another column: the card is already placed there by onDragOver.
        const index = reorder ? current.cards.findIndex((card) => card.id === active.id) : 0;
        commit(active.id, current.stage, index);

        return undefined;
    };

    // -- show more -------------------------------------------------------------------

    const showMore = async (stage) => {
        const column = colsRef.current.find((c) => c.stage === stage);
        setLoadingMore(stage);

        try {
            const { data } = await window.axios.get(route('pipeline.column'), {
                params: { stage, offset: column.cards.length, search: filters.search || undefined, priority: filters.priority || undefined },
            });

            setCols((current) =>
                current.map((c) => {
                    if (c.stage !== stage) return c;
                    const known = new Set(c.cards.map((card) => card.id));

                    return { ...c, has_more: data.has_more, cards: [...c.cards, ...data.cards.filter((card) => !known.has(card.id))] };
                }),
            );
        } catch (error) {
            onError(errorMessage(error));
        } finally {
            setLoadingMore(null);
        }
    };

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCorners}
            onDragStart={onDragStart}
            onDragOver={onDragOver}
            onDragEnd={onDragEnd}
            onDragCancel={onDragCancel}
        >
            <div className="relative -mx-4 flex snap-x snap-mandatory gap-3 overflow-x-auto px-4 pb-4 sm:mx-0 sm:snap-none sm:px-0" data-testid="board">
                {cols.map((column) => (
                    <Column
                        key={column.stage}
                        column={column}
                        stages={stages}
                        canMove={canMove}
                        reorder={reorder}
                        reducedMotion={reducedMotion}
                        highlighted={activeId !== null && overStage === column.stage}
                        loadingMore={loadingMore === column.stage}
                        hrefFor={hrefFor}
                        onMove={moveTo}
                        onShowMore={showMore}
                    />
                ))}
            </div>

            <DragOverlay dropAnimation={reducedMotion ? null : undefined}>
                {activeCard ? <PipelineCard card={activeCard} stages={stages} canMove={false} overlay /> : null}
            </DragOverlay>
        </DndContext>
    );
}
