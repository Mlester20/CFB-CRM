<?php

namespace App\Policies;

class LeadPolicy extends OwnedModelPolicy
{
    protected function module(): string
    {
        return 'leads';
    }
}
