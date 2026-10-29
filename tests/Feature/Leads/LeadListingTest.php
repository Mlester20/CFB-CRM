<?php

namespace Tests\Feature\Leads;

use App\Enums\LeadPriority;
use App\Enums\LeadSource;
use App\Enums\LeadStage;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithRbac;
use Tests\TestCase;

class LeadListingTest extends TestCase
{
    use InteractsWithRbac, RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRbac();
        $this->manager = $this->userWithRole('Sales Manager');
    }

    private function props(string $query = ''): array
    {
        return $this->actingAs($this->manager)->get('/leads'.$query)->assertOk()->viewData('page')['props'];
    }

    /** @return list<string> */
    private function names(array $props): array
    {
        return array_column($props['leads']['data'], 'name');
    }

    private function lead(array $attributes): Lead
    {
        return Lead::factory()->ownedBy($this->manager)->create($attributes);
    }

    public function test_search_matches_name_company_and_email(): void
    {
        $this->lead(['name' => 'Maria Santos', 'company' => 'Alpha Trading', 'email' => 'maria@one.ph']);
        $this->lead(['name' => 'Juan Cruz', 'company' => 'Bravo Logistics', 'email' => 'juan@two.ph']);
        $this->lead(['name' => 'Ana Reyes', 'company' => 'Charlie Foods', 'email' => 'ana@three.ph']);

        $this->assertSame(['Maria Santos'], $this->names($this->props('?search=santos')));
        $this->assertSame(['Juan Cruz'], $this->names($this->props('?search=bravo')));
        $this->assertSame(['Ana Reyes'], $this->names($this->props('?search=three.ph')));
        $this->assertSame([], $this->names($this->props('?search=zzz')));
        $this->assertCount(3, $this->props('?search=')['leads']['data']);
    }

    public function test_a_search_with_wildcard_characters_is_taken_literally(): void
    {
        $quiet = ['company' => 'Zzz Corp', 'email' => null];
        $this->lead([...$quiet, 'name' => 'Plain Person']);
        $this->lead([...$quiet, 'name' => '100% Sure']);

        $this->assertSame(['100% Sure'], $this->names($this->props('?search='.urlencode('%'))));
    }

    public function test_priority_source_and_stage_filters_work_alone_and_together(): void
    {
        $this->lead(['name' => 'A', 'priority' => LeadPriority::High, 'source' => LeadSource::Website, 'stage' => LeadStage::New]);
        $this->lead(['name' => 'B', 'priority' => LeadPriority::High, 'source' => LeadSource::Referral, 'stage' => LeadStage::Won]);
        $this->lead(['name' => 'C', 'priority' => LeadPriority::Low, 'source' => LeadSource::Website, 'stage' => LeadStage::Won]);

        $this->assertEqualsCanonicalizing(['A', 'B'], $this->names($this->props('?priority=high')));
        $this->assertEqualsCanonicalizing(['A', 'C'], $this->names($this->props('?source=website')));
        $this->assertEqualsCanonicalizing(['B', 'C'], $this->names($this->props('?stage=won')));
        $this->assertSame(['B'], $this->names($this->props('?priority=high&stage=won')));
        $this->assertSame(['A'], $this->names($this->props('?priority=high&source=website&stage=new')));
    }

    public function test_unknown_filter_values_are_ignored(): void
    {
        $this->lead(['name' => 'A']);
        $props = $this->props('?stage=bogus&priority=bogus&source=bogus&sort=bogus&direction=sideways');

        $this->assertCount(1, $props['leads']['data']);
        $this->assertNull($props['filters']['stage']);
        $this->assertSame('updated', $props['filters']['sort']);
        $this->assertSame('desc', $props['filters']['direction']);
    }

    public function test_a_column_name_in_sort_cannot_be_injected(): void
    {
        $this->lead(['name' => 'A']);

        $props = $this->props('?sort='.urlencode('name; drop table leads').'&direction=asc');

        $this->assertSame('updated', $props['filters']['sort']);
        $this->assertSame(1, Lead::count());
    }

    public function test_sorting_by_name_value_and_updated(): void
    {
        $this->lead(['name' => 'Charlie', 'value' => 100, 'updated_at' => now()->subDays(1)]);
        $this->lead(['name' => 'Alpha', 'value' => 300, 'updated_at' => now()->subDays(3)]);
        $this->lead(['name' => 'Bravo', 'value' => 200, 'updated_at' => now()->subDays(2)]);

        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->names($this->props('?sort=name&direction=asc')));
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->names($this->props('?sort=name&direction=desc')));
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->names($this->props('?sort=value&direction=desc')));
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->names($this->props('?sort=value&direction=asc')));
        // default: most recently updated first
        $this->assertSame(['Charlie', 'Bravo', 'Alpha'], $this->names($this->props()));
        $this->assertSame(['Alpha', 'Bravo', 'Charlie'], $this->names($this->props('?sort=updated&direction=asc')));
    }

    public function test_pagination_is_fifteen_per_page_and_keeps_the_filters(): void
    {
        Lead::factory()->count(20)->ownedBy($this->manager)->create(['priority' => LeadPriority::High]);
        Lead::factory()->count(3)->ownedBy($this->manager)->create(['priority' => LeadPriority::Low]);

        $first = $this->props('?priority=high');
        $this->assertCount(15, $first['leads']['data']);
        $this->assertSame(20, $first['leads']['total']);
        $this->assertSame(2, $first['leads']['last_page']);

        $second = $this->props('?priority=high&page=2');
        $this->assertCount(5, $second['leads']['data']);

        $links = collect($second['leads']['links'])->pluck('url')->filter()->implode(' ');
        $this->assertStringContainsString('priority=high', $links);
    }

    public function test_a_page_past_the_end_redirects_to_the_last_page(): void
    {
        Lead::factory()->count(16)->ownedBy($this->manager)->create();

        $this->actingAs($this->manager)->get('/leads?page=9')->assertRedirect('/leads?page=2');
    }

    public function test_stage_chip_counts_follow_search_priority_and_source_but_not_the_stage(): void
    {
        $quiet = ['company' => 'Zzz Corp', 'email' => null]; // keeps random data from matching the search
        $this->lead([...$quiet, 'name' => 'Anna', 'stage' => LeadStage::New, 'priority' => LeadPriority::High]);
        $this->lead([...$quiet, 'name' => 'Anne', 'stage' => LeadStage::Won, 'priority' => LeadPriority::High]);
        $this->lead([...$quiet, 'name' => 'Anna Two', 'stage' => LeadStage::Won, 'priority' => LeadPriority::Low]);
        $this->lead([...$quiet, 'name' => 'Bob', 'stage' => LeadStage::Lost, 'priority' => LeadPriority::High]);

        $props = $this->props('?search=an&priority=high&stage=won');

        $this->assertSame(['all' => 2, 'new' => 1, 'qualified' => 0, 'proposal' => 0, 'won' => 1, 'lost' => 0], $props['stageCounts']);
        $this->assertSame(['Anne'], $this->names($props)); // the stage filter still narrows the list
        $this->assertSame(4, $props['stats']['total']);    // stat cards ignore filters
    }

    public function test_the_export_follows_the_filters_the_sort_and_defuses_formulas(): void
    {
        $this->lead(['name' => '=HYPERLINK("http://evil.example")', 'company' => 'Zed', 'priority' => LeadPriority::High]);
        $this->lead(['name' => '+cmd', 'company' => 'Yak', 'priority' => LeadPriority::High]);
        $this->lead(['name' => 'Hidden by filter', 'priority' => LeadPriority::Low]);

        $csv = $this->actingAs($this->manager)->get('/leads/export?priority=high&sort=name&direction=asc')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        $this->assertStringContainsString("Name,Company,Email,Phone,Stage,Priority,Source,\"Value (Php)\"", $csv);
        $this->assertStringNotContainsString('Hidden by filter', $csv);
        // formulas are neutralised with a leading apostrophe
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertStringContainsString("'+cmd", $csv);
        $this->assertStringNotContainsString('"=HYPERLINK', $csv);
        $this->assertLessThan(strpos($csv, "'=HYPERLINK"), strpos($csv, "'+cmd"), 'sorted by name ascending');
    }

    public function test_the_filter_options_are_sent(): void
    {
        $options = $this->props()['options'];

        $this->assertSame(['new', 'qualified', 'proposal', 'won', 'lost'], array_column($options['stages'], 'value'));
        $this->assertSame(['low', 'medium', 'high'], array_column($options['priorities'], 'value'));
        $this->assertContains('social_media', array_column($options['sources'], 'value'));
    }
}
