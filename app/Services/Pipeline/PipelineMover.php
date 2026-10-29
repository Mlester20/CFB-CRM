<?php

namespace App\Services\Pipeline;

use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use App\Policies\LeadPolicy;
use Illuminate\Support\Facades\DB;

/**
 * Moves a card on the Pipeline board: to another column, or within its column.
 *
 * `pipeline_position` is a dense order (0, 1, 2, ...) of ALL the leads of a stage,
 * whoever owns them. A user who sees only their own leads sends `$position` as the
 * index among the cards THEY see; it is translated here to "just before the visible
 * card that is now at that index" (or after the last visible card).
 *
 * Everything happens in one transaction that locks the rows of both columns, so two
 * moves at the same time cannot corrupt the order: the second waits and then works
 * on the order the first left behind.
 */
class PipelineMover
{
    /**
     * @param  int  $position  index among the cards the user sees in the target column
     *                         (the moved card excluded), 0 = top
     * @return array{stage_changed: bool, from: LeadStage, to: LeadStage}
     */
    public function move(Lead $lead, User $user, LeadStage $stage, int $position): array
    {
        return DB::transaction(function () use ($lead, $user, $stage, $position) {
            $oldStage = $lead->stage;

            // Lock both columns (every owner), then re-read the lead under the lock.
            Lead::query()->whereIn('stage', [$oldStage->value, $stage->value])->lockForUpdate()->pluck('id');

            /** @var Lead $fresh */
            $fresh = Lead::query()->lockForUpdate()->findOrFail($lead->getKey());

            // The lead may have been reassigned or moved while we waited for the lock.
            abort_unless($fresh->isVisibleTo($user, LeadPolicy::PIPELINE_VIEW_ALL), 404);

            $from = $fresh->stage;
            $fromPosition = $fresh->pipeline_position;
            $changed = $from !== $stage;

            Lead::withoutPipelineOrdering(function () use ($fresh, $user, $stage, $position, $from, $fromPosition, $changed) {
                // 1. Leave the old column without a gap.
                Lead::query()->where('stage', $from->value)
                    ->where('pipeline_position', '>', $fromPosition)
                    ->decrement('pipeline_position');

                // 2. Where does "index N among the cards I see" land in the whole column?
                $visible = Lead::query()
                    ->visibleTo($user, LeadPolicy::PIPELINE_VIEW_ALL)
                    ->where('stage', $stage->value)
                    ->whereKeyNot($fresh->getKey())
                    ->orderBy('pipeline_position')
                    ->pluck('pipeline_position')
                    ->values();

                $index = min(max($position, 0), $visible->count());
                $target = match (true) {
                    $visible->isEmpty() => 0,
                    $index < $visible->count() => (int) $visible[$index],
                    default => (int) $visible->last() + 1,
                };

                // 3. Open a gap there.
                Lead::query()->where('stage', $stage->value)
                    ->whereKeyNot($fresh->getKey())
                    ->where('pipeline_position', '>=', $target)
                    ->increment('pipeline_position');

                // 4. Put the card in it. A pure reorder is not a change to the lead itself.
                $fresh->stage = $stage;
                $fresh->pipeline_position = $target;

                if ($changed) {
                    $fresh->stage_changed_at = now();
                } else {
                    $fresh->timestamps = false;
                }

                $fresh->save();
            });

            return ['stage_changed' => $changed, 'from' => $from, 'to' => $stage];
        });
    }
}
