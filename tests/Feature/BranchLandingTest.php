<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\BranchService;
use App\Models\SalonSetting;
use App\Models\Service;
use App\Models\Stylist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The public landing page with several branches. */
class BranchLandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SalonSetting::current();
    }

    public function test_with_one_branch_the_page_looks_as_before(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('Find your nearest branch')
            ->assertDontSee('Book at this branch')
            ->assertSee('Visit us');
    }

    public function test_several_branches_get_a_section_with_book_links_and_hours(): void
    {
        Branch::findOrFail(1)->update(['name' => 'Makati', 'address' => '1 Ayala Ave', 'open_time' => '09:00', 'close_time' => '19:00']);
        $b = Branch::create(['name' => 'Cebu', 'address' => '2 Colon St', 'phone' => '032 555 0100', 'open_time' => '10:00', 'close_time' => '20:00']);

        $this->get('/')->assertOk()
            ->assertSee('Find your nearest branch')
            ->assertSee('Makati')->assertSee('1 Ayala Ave')
            ->assertSee('Cebu')->assertSee('2 Colon St')->assertSee('10:00 AM – 8:00 PM')
            ->assertSee('/app/register?branch='.$b->id, false)
            ->assertSee('Get directions')
            ->assertSee('#branches', false)
            ->assertDontSee('Visit us')->assertSee('Get in touch');
    }

    public function test_a_switched_off_branch_is_not_shown(): void
    {
        Branch::create(['name' => 'Open One']);
        Branch::create(['name' => 'Closed One', 'active' => false]);

        $this->get('/')->assertOk()->assertSee('Open One')->assertDontSee('Closed One');
    }

    public function test_prices_say_from_when_branches_differ(): void
    {
        $second = Branch::create(['name' => 'Second']);
        $cut = Service::create(['name' => 'Cut', 'price' => 500, 'duration_minutes' => 30]);
        Service::create(['name' => 'Wash', 'price' => 200, 'duration_minutes' => 20]);
        BranchService::create(['branch_id' => $second->id, 'service_id' => $cut->id, 'price' => 650]);

        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/from<\/span>₱500/', $html);   // Cut differs between branches
        $this->assertStringContainsString('₱200', $html);                       // Wash is the same everywhere
        $this->assertDoesNotMatchRegularExpression('/from<\/span>₱200/', $html);
    }

    public function test_a_service_no_open_branch_offers_is_left_off(): void
    {
        $second = Branch::create(['name' => 'Second']);
        $color = Service::create(['name' => 'Rare Color', 'price' => 900, 'duration_minutes' => 90]);
        Service::create(['name' => 'Cut', 'price' => 500, 'duration_minutes' => 30]);
        foreach ([1, $second->id] as $branchId) {
            BranchService::create(['branch_id' => $branchId, 'service_id' => $color->id, 'available' => false]);
        }

        $this->get('/')->assertOk()->assertDontSee('Rare Color')->assertSee('Cut');
    }

    public function test_the_team_shows_each_stylists_branch(): void
    {
        $second = Branch::create(['name' => 'Cebu']);
        Stylist::create(['name' => 'Ben Tan', 'role' => 'Barber', 'active' => true, 'branch_id' => $second->id]);

        $this->get('/')->assertOk()->assertSee('Ben Tan');
        $this->assertStringContainsString('Cebu', $this->get('/')->getContent());
    }
}
