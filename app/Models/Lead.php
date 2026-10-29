<?php

namespace App\Models;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\Concerns\OwnedByUser;
use Database\Factories\LeadFactory;
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
        ];
    }
}
