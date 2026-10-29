<?php

namespace App\Models;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\Concerns\OwnedByUser;
use Database\Factories\LeadFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    /** @use HasFactory<LeadFactory> */
    use HasFactory, OwnedByUser, SoftDeletes;

    /** The permission module for the own/all scope ("leads.view-all"). */
    protected static string $ownershipModule = 'leads';

    /**
     * owner_id is not mass assignable: it is set explicitly, and only a user
     * with leads.view-all may choose it.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'company',
        'email',
        'phone',
        'stage',
        'priority',
        'source',
        'value',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage' => LeadStage::class,
            'priority' => LeadPriority::class,
            'source' => LeadSource::class,
            'value' => 'decimal:2',
            'saims_customer_id' => 'integer',
            'pipeline_position' => 'integer',
            'stage_changed_at' => 'datetime',
        ];
    }

    private static bool $pipelineHooksOff = false;

    /**
     * Keeps the Pipeline column order right whenever a lead is created, deleted or
     * changes stage OUTSIDE the board (for example in the Leads form):
     *   - a new lead goes to the top of its column;
     *   - a lead that changes stage leaves its old column without a gap and lands on
     *     top of the new one, and stage_changed_at is set;
     *   - a deleted lead closes its gap.
     * The Pipeline move does all of this itself, inside one transaction, with these hooks off.
     */
    protected static function booted(): void
    {
        static::creating(function (Lead $lead) {
            if (static::$pipelineHooksOff) {
                return;
            }

            $lead->stage_changed_at ??= now();
            static::query()->where('stage', $lead->stage->value)->increment('pipeline_position');
            $lead->pipeline_position = 0;
        });

        static::updating(function (Lead $lead) {
            if (static::$pipelineHooksOff || ! $lead->isDirty('stage')) {
                return;
            }

            $from = $lead->getOriginal('stage');
            $from = $from instanceof LeadStage ? $from->value : $from;

            static::query()->where('stage', $from)
                ->where('pipeline_position', '>', (int) $lead->getOriginal('pipeline_position'))
                ->decrement('pipeline_position');
            static::query()->where('stage', $lead->stage->value)->increment('pipeline_position');

            $lead->pipeline_position = 0;
            $lead->stage_changed_at = now();
        });

        static::deleted(function (Lead $lead) {
            if (static::$pipelineHooksOff) {
                return;
            }

            static::query()->where('stage', $lead->stage->value)
                ->where('pipeline_position', '>', $lead->pipeline_position)
                ->decrement('pipeline_position');
        });
    }

    /** Runs $callback without the automatic column ordering (the Pipeline move orders the columns itself). */
    public static function withoutPipelineOrdering(callable $callback): mixed
    {
        $before = static::$pipelineHooksOff;
        static::$pipelineHooksOff = true;

        try {
            return $callback();
        } finally {
            static::$pipelineHooksOff = $before;
        }
    }

    /**
     * Name, company or email contains $search. "!" is the LIKE escape character, which works
     * the same on MySQL and SQLite, so a typed % or _ is taken literally.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeMatching(Builder $query, string $search): Builder
    {
        $like = '%'.strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

        return $query->whereRaw(
            "(name like ? escape '!' or company like ? escape '!' or email like ? escape '!')",
            [$like, $like, $like],
        );
    }
}
