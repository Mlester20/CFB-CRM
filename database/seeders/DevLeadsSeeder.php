<?php

namespace Database\Seeders;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Seeder;

class DevLeadsSeeder extends Seeder
{
    /**
     * About 40 sample leads spread across the dev users and stages.
     * Local environment only, and only when there are no leads yet.
     */
    public function run(): void
    {
        if (! app()->isLocal()) {
            $this->command?->warn('DevLeadsSeeder skipped: the environment is not local.');

            return;
        }

        if (Lead::withTrashed()->exists()) {
            $this->command?->warn('DevLeadsSeeder skipped: leads already exist.');

            return;
        }

        $owners = User::whereIn('email', ['admin@crm.test', 'manager@crm.test', 'rep@crm.test', 'viewer@crm.test'])
            ->pluck('id', 'email');

        if ($owners->count() < 4) {
            $this->command?->warn('DevLeadsSeeder skipped: run DevUsersSeeder first.');

            return;
        }

        // Most leads belong to the rep, so the own/all scope is easy to see.
        $ownerPool = [
            ...array_fill(0, 16, $owners['rep@crm.test']),
            ...array_fill(0, 12, $owners['manager@crm.test']),
            ...array_fill(0, 8, $owners['admin@crm.test']),
            ...array_fill(0, 4, $owners['viewer@crm.test']),
        ];

        $people = [
            ['Maria Santos', 'Santos Trading'], ['Juan Dela Cruz', 'Dela Cruz Logistics'],
            ['Angela Reyes', 'Reyes & Co.'], ['Paolo Garcia', 'Garcia Foods'],
            ['Liza Mendoza', 'Mendoza Retail'], ['Carlo Ramos', 'Ramos Builders'],
            ['Benjie Tan', 'Tan Holdings'], ['Rosa Villanueva', 'Villanueva Corp.'],
            ['Marco Bautista', 'Bautista Steel'], ['Katrina Aquino', 'Aquino Pharmacy'],
            ['Jericho Navarro', 'Navarro Auto Parts'], ['Bea Castillo', 'Castillo Bakeshop'],
            ['Miguel Fernandez', 'Fernandez Shipping'], ['Camille Domingo', 'Domingo Textiles'],
            ['Rafael Torres', 'Torres Construction'], ['Patricia Lim', 'Lim Electronics'],
            ['Dennis Pascual', 'Pascual Farms'], ['Joanna Cruz', 'Cruz Travel & Tours'],
            ['Eric Soriano', 'Soriano Hardware'], ['Nicole Manalo', 'Manalo Events'],
            ['Arnold Salazar', 'Salazar Printing'], ['Grace Valdez', 'Valdez Dental Clinic'],
            ['Francis Ocampo', 'Ocampo Furniture'], ['Trisha Estrada', 'Estrada Coffee Roasters'],
            ['Leo Mercado', 'Mercado Fishery'], ['Hazel Dizon', 'Dizon Boutique'],
            ['Victor Gonzales', 'Gonzales Realty'], ['Alma Pangilinan', 'Pangilinan Education'],
            ['Ronnie Abad', 'Abad Trucking'], ['Sheila Francisco', 'Francisco Catering'],
            ['Oscar Macaraeg', 'Macaraeg Solar'], ['Daisy Guevarra', 'Guevarra Wellness'],
            ['Noel Bernardo', 'Bernardo Packaging'], ['Cherry Lopez', 'Lopez Marketing'],
            ['Gilbert Santiago', 'Santiago Plastics'], ['Mylene Rivera', 'Rivera Laundry'],
            ['Edgar Magno', 'Magno Water Systems'], ['Jasmine Corpuz', 'Corpuz Skincare'],
            ['Romeo Padilla', 'Padilla Engineering'], ['Irene Hernandez', 'Hernandez Bookstore'],
        ];

        $stages = LeadStage::cases();
        $priorities = LeadPriority::cases();
        $sources = LeadSource::cases();

        foreach ($people as $index => [$name, $company]) {
            $slug = strtolower(str_replace([' ', '.', '&', ','], ['.', '', '', ''], $name));
            $created = now()->subDays(rand(1, 90))->subMinutes(rand(0, 1400));

            Lead::factory()->create([
                'name' => $name,
                'company' => $company,
                'email' => $slug.'@example.ph',
                'stage' => $stages[$index % count($stages)],
                'priority' => $priorities[($index * 7) % count($priorities)],
                'source' => $sources[($index * 5) % count($sources)],
                'value' => rand(15, 950) * 1000,
                'owner_id' => $ownerPool[$index % count($ownerPool)],
                'created_at' => $created,
                'updated_at' => $created->copy()->addDays(rand(0, 20))->min(now()),
            ]);
        }
    }
}
