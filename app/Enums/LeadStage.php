<?php

namespace App\Enums;

enum LeadStage: string
{
    case New = 'new';
    case Qualified = 'qualified';
    case Proposal = 'proposal';
    case Won = 'won';
    case Lost = 'lost';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Stages that still count towards the open pipeline.
     *
     * @return list<string>
     */
    public static function open(): array
    {
        return [self::New->value, self::Qualified->value, self::Proposal->value];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => ['value' => $case->value, 'label' => $case->label()], self::cases());
    }
}
