<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Partial page refresh (public/js/partial.js). The browser sends forms and same-page links with fetch() and swaps
 * only the areas marked data-region. The server side of that contract: the script is loaded, every page that
 * changes in place exposes the same named areas on every request, and the pop-up says whether it must be open.
 */
class PartialRefreshTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_layout_loads_the_partial_refresh_script_and_it_exists(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('companies.index'))
            ->assertOk()->assertSee('js/partial.js', false);

        $this->assertFileExists(public_path('js/partial.js'));
    }

    public function test_logout_and_login_forms_opt_out_of_partial_refresh(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('companies.index'))
            ->assertSee('action="'.route('logout').'" class="who" data-no-ajax', false);

        auth()->logout();
        $this->get('/login')->assertOk()->assertSee('data-no-ajax', false);
    }

    public function test_list_pages_expose_a_results_area_and_a_flash_area(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['companies.index', 'contacts.index', 'tickets.index', 'admin.users.index', 'templates.index',
                  'admin.modules.index', 'interactions.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk()
                ->assertSee('data-region="results"', false, "$route has no results area");
        }

        $this->actingAs($admin)->get(route('companies.index'))->assertSee('data-region="flash"', false);
    }

    public function test_the_admin_dashboard_refreshes_its_three_blocks_separately(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.dashboard'))->assertOk()
            ->assertSee('data-region="cards"', false)
            ->assertSee('data-region="queue"', false)
            ->assertSee('data-region="workload"', false);
    }

    public function test_the_flash_message_arrives_inside_the_flash_area(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->withSession(['status' => 'Saved it.'])->get(route('companies.index'))
            ->assertSee('<div data-region="flash"><div class="flash">Saved it.</div></div>', false);
    }

    public function test_a_failed_popup_submit_comes_back_marked_open_with_its_errors(): void
    {
        $admin = User::factory()->admin()->create();

        // fetch() follows the redirect-back, so the page it receives is the list with the errors and old input.
        $this->actingAs($admin)->from(route('companies.index'))->followingRedirects()
            ->post(route('companies.store'), ['_modal' => 'company', 'name' => ''])
            ->assertOk()
            ->assertSee('<dialog id="company-modal"', false)
            ->assertSee('data-open', false)
            ->assertSee('The name field is required.');
    }

    public function test_the_popup_is_not_marked_open_on_a_normal_visit(): void
    {
        $admin = User::factory()->admin()->create();

        $html = $this->actingAs($admin)->get(route('companies.index'))->getContent();

        $this->assertMatchesRegularExpression('/<dialog id="company-modal"(?![^>]*data-open)[^>]*>/', $html);
    }

    public function test_new_query_string_marks_the_create_popup_open(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('companies.index', ['new' => 1]))
            ->assertSee('data-open', false);
    }

    public function test_the_contact_page_keeps_the_log_area_even_when_the_contact_is_inactive(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::factory()->create();
        $contact = \App\Models\Contact::factory()->create(['company_id' => $company->company_id, 'is_active' => 0]);

        $this->actingAs($admin)->get(route('contacts.show', $contact))->assertOk()
            ->assertSee('data-region="contact-side"', false)
            ->assertSee('data-region="log"', false)
            ->assertSee('data-region="conversations"', false);
    }

    public function test_list_tables_are_marked_for_the_data_grid_and_keep_their_plain_table(): void
    {
        $admin = User::factory()->admin()->create();
        Company::factory()->create();

        foreach (['companies.index', 'contacts.index', 'tickets.index', 'admin.users.index', 'templates.index', 'interactions.index'] as $route) {
            $this->actingAs($admin)->get(route($route))->assertOk()
                ->assertSee('<table class="list" data-grid>', false, "$route table is not marked for the grid");
        }
    }

    public function test_the_grid_sources_exist(): void
    {
        $this->assertFileExists(resource_path('js/grid.jsx'));
        $this->assertStringContainsString('table[data-grid]', file_get_contents(resource_path('js/app.js')));
        $this->assertStringContainsString('partial:updated', file_get_contents(public_path('js/partial.js')));
    }

    public function test_filter_bars_have_no_search_button_outside_noscript_and_a_search_box_width(): void
    {
        $admin = User::factory()->admin()->create();

        foreach (['companies.index', 'contacts.index', 'tickets.index', 'interactions.index', 'templates.index', 'admin.users.index'] as $route) {
            $html = $this->actingAs($admin)->get(route($route))->getContent();
            $withoutNoscript = preg_replace('#<noscript>.*?</noscript>#s', '', $html);

            $this->assertStringNotContainsString('>Search</button>', $withoutNoscript, "$route still has a Search button");
            $this->assertStringNotContainsString('>Filter</button>', $withoutNoscript, "$route still has a Filter button");
            $this->assertStringContainsString('<noscript><button class="btn" type="submit">', $html, "$route has no no-JavaScript fallback");
        }

        $this->assertStringContainsString('.filters input[name=q]{width:250px}', $this->actingAs($admin)->get(route('companies.index'))->getContent());
        $this->assertStringContainsString('autoSubmit', file_get_contents(public_path('js/partial.js')));
    }

    public function test_users_and_templates_lists_are_paginated(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(22)->create(['full_name' => 'Zed Agent']);
        \App\Models\MessageTemplate::factory()->count(22)->create();

        $users = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $this->assertSame(20, substr_count($users->getContent(), '<tr  id="user-') + substr_count($users->getContent(), '<tr class="" id="user-'));
        $users->assertSee('Page 1 of 2');
        $this->get(route('admin.users.index', ['page' => 2]))->assertSee('Page 2 of 2');

        $this->get(route('templates.index'))->assertSee('Page 1 of 2');
        $this->get(route('templates.index', ['page' => 2]))->assertSee('Page 2 of 2');
    }

    public function test_a_new_agent_on_a_later_page_is_shown_after_create(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(22)->create(['full_name' => 'Aaa Agent']);

        // "Zzz" sorts last, so it lands on page 2 and the redirect goes there.
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'full_name' => 'Zzz New Agent', 'email' => 'zzz@example.test', 'password' => 'Secret-pass-123',
            'password_confirmation' => 'Secret-pass-123', 'all_companies' => 1,
        ])->assertRedirect(route('admin.users.index', ['page' => 2]));
    }

    public function test_the_grid_pages_on_screen_and_the_server_bar_is_only_a_fallback(): void
    {
        $admin = User::factory()->admin()->create();
        Company::factory()->count(3)->create();

        // One page of rows: the data grid shows its own pager, so no server bar.
        $this->actingAs($admin)->get(route('companies.index'))->assertOk()->assertDontSee('class="pager"', false);

        // More rows than the server sends per page: the fallback bar appears.
        config(['crc.per_page' => 2]);
        $this->get(route('companies.index'))->assertSee('class="pager"', false)->assertSee('Page 1 of 2')
            ->assertSee('Showing 1&ndash;2 of 3', false);

        $grid = file_get_contents(resource_path('js/grid.jsx'));
        $this->assertStringContainsString('pagination={true}', $grid);
        $this->assertStringContainsString('paginationPageSize={pageSize}', $grid);
        $this->assertSame(200, (int) (require config_path('crc.php'))['per_page']);
    }

    public function test_every_page_has_the_footer(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('companies.index'))
            ->assertSee('<footer class="site">', false)->assertSee('Customer Relationship Console');

        auth()->logout();
        $this->get('/login')->assertSee('<footer class="site">', false);
    }

    public function test_a_placeholder_holds_each_grid_place_so_the_raw_table_never_flashes(): void
    {
        $admin = User::factory()->admin()->create();
        Company::factory()->count(3)->create();

        // With rows: placeholder (with the row count) directly before the table, which CSS hides when JavaScript is on.
        $html = $this->actingAs($admin)->get(route('companies.index'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<div class="grid-skeleton" data-total="3"[^>]*>.*?<table class="list" data-grid>/s', $html);
        $this->assertStringContainsString('html.js .grid-skeleton ~ table[data-grid]{display:none}', $html);
        $this->assertStringContainsString('html:not(.js) .grid-skeleton{display:none}', $html);
        $this->assertStringContainsString('main:has(.grid-skeleton:not([data-page-size]))', $html);   // same page width while the placeholder is measured

        // Empty list: no placeholder, so the "No ... found" table stays visible.
        $empty = $this->get(route('companies.index', ['q' => 'no-such-company-xyz']))->getContent();
        $this->assertStringNotContainsString('class="grid-skeleton"', $empty);
        $this->assertStringContainsString('No companies found.', $empty);
    }

    public function test_grid_pages_load_the_size_script_first_and_request_the_grid_code_from_the_head(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertFileExists(public_path('js/grid-size.js'));
        $this->assertStringContainsString('window.crcGridLayout', file_get_contents(public_path('js/grid-size.js')));
        $this->assertStringContainsString('window.crcGridLayout', file_get_contents(resource_path('js/grid.jsx')));

        if (! file_exists(public_path('build/manifest.json')) && ! file_exists(public_path('hot'))) {
            $this->markTestSkipped('The front-end build is not present, so no assets are emitted.');
        }

        foreach (['companies.index', 'contacts.index', 'tickets.index', 'interactions.index', 'templates.index', 'admin.users.index'] as $route) {
            $html = $this->actingAs($admin)->get(route($route))->getContent();
            $head = substr($html, 0, strpos($html, '</head>'));

            $this->assertStringContainsString('js/grid-size.js', $head, "$route: size script is not in the head");
            $this->assertMatchesRegularExpression('#build/assets/grid-[\w-]+\.js|resources/js/grid\.jsx#', $head, "$route: grid code is not requested from the head");
        }

        // A page without a grid (the login page) does not pay for the 1.3 MB grid chunk.
        auth()->logout();
        $head = substr($c = $this->get('/login')->getContent(), 0, strpos($c, '</head>'));
        $this->assertDoesNotMatchRegularExpression('#build/assets/grid-[\w-]+\.js|resources/js/grid\.jsx#', $head);
    }

    public function test_every_data_table_in_the_app_is_a_grid_with_a_placeholder(): void
    {
        $admin = User::factory()->admin()->create();
        $company = Company::factory()->create();
        \App\Models\Contact::factory()->count(2)->create(['company_id' => $company->company_id]);
        \App\Models\ProductModule::create(['name' => 'Billing']);

        // Company page: all its contacts in a full-size grid (no small fixed page size).
        $html = $this->actingAs($admin)->get(route('companies.show', $company))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/<div class="grid-skeleton" data-total="2"[^>]*>.*?<table class="list" data-grid>/s', $html);
        $this->assertStringNotContainsString('data-page-size="', $html);   // the attribute (the layout CSS only mentions the name)

        // Modules: inputs belong to their row's form through the form attribute (a grid cell cannot hold a spanning form).
        $modules = $this->get(route('admin.modules.index'))->assertOk()->getContent();
        $this->assertStringContainsString('<table class="list" data-grid>', $modules);
        $this->assertMatchesRegularExpression('/<input form="module-form-\d+" name="name"/', $modules);
        $this->assertMatchesRegularExpression('/<form id="module-form-\d+" method="POST"/', $modules);

        // Dashboard cards are small embedded grids: fixed rows per page, headers, and they do not widen a page on their own.
        $ticket = \App\Models\Ticket::factory()->create(['assigned_to' => null, 'status' => 'open']);
        $dash = $this->get(route('admin.dashboard'))->assertOk()->getContent();
        $this->assertStringContainsString('<table class="list" data-grid data-page-size="5">', $dash);
        $this->assertStringContainsString('<th>Ticket</th>', $dash);
    }

    public function test_the_users_list_has_a_search_bar_that_filters_by_name_email_phone_or_role(): void
    {
        $admin = User::factory()->admin()->create(['full_name' => 'Boss Person']);
        User::factory()->create(['full_name' => 'Zelda Finder', 'email' => 'zelda@find.example', 'phone' => '9111100000']);
        User::factory()->create(['full_name' => 'Other Agent', 'email' => 'other@find.example', 'phone' => '9222200000']);

        $page = $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
        $page->assertSee('name="q"', false)->assertSee('placeholder="Search"', false);

        $this->get(route('admin.users.index', ['q' => 'zelda']))->assertSee('Zelda Finder')->assertDontSee('Other Agent');
        $this->get(route('admin.users.index', ['q' => 'other@find']))->assertSee('Other Agent')->assertDontSee('Zelda Finder');
        $this->get(route('admin.users.index', ['q' => '91111']))->assertSee('Zelda Finder')->assertDontSee('Other Agent');
        $this->get(route('admin.users.index', ['q' => 'admin']))->assertSee('Boss Person')->assertDontSee('Zelda Finder');
        $this->get(route('admin.users.index', ['q' => 'nobody-matches-this']))->assertSee('No users found.');

        // The search term survives into the box and into the pager links.
        $this->get(route('admin.users.index', ['q' => 'zelda']))->assertSee('value="zelda"', false);
    }
}
