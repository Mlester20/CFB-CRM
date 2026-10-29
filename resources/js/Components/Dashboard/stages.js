// Static class names so Tailwind can see them. One color per pipeline stage.
export const stageColors = {
    New: { dot: 'bg-sky-500', bar: 'bg-sky-500' },
    Qualified: { dot: 'bg-indigo-500', bar: 'bg-indigo-500' },
    Proposal: { dot: 'bg-amber-500', bar: 'bg-amber-500' },
    Won: { dot: 'bg-emerald-500', bar: 'bg-emerald-500' },
    Lost: { dot: 'bg-rose-500', bar: 'bg-rose-500' },
};

export const fallbackStageColor = { dot: 'bg-slate-400', bar: 'bg-slate-400' };
