<?php

namespace Tests;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The app sends up to 200 rows per list (the data grid pages on screen); tests exercise server paging at 20.
        config(['crc.per_page' => 20]);
    }

    /**
     * Agents only see companies (and their contacts) where they hold a ticket (App\Support\AgentAccess). Gives the agent a
     * resolved ticket at each place, so it becomes visible without adding open work. A Company gets the ticket on one of
     * its contacts (a new one only if it has none).
     */
    /**
     * For tests about pages rather than access: from now on every new contact gets a resolved ticket for each of these
     * agents, so they can see it (and its company). Tests that check the hiding create contacts without this.
     */
    protected function agentsWorkAtEveryNewContact(User ...$agents): void
    {
        Contact::created(function (Contact $contact) use ($agents) {
            foreach ($agents as $agent) {
                $this->workAt($agent, $contact);
            }
        });
    }

    protected function workAt(User $agent, Company|Contact ...$places): void
    {
        foreach ($places as $place) {
            $contact = $place instanceof Contact
                ? $place
                : (Contact::withoutGlobalScopes()->where('company_id', $place->company_id)->first()
                    ?? Contact::factory()->for($place)->create());

            Ticket::factory()->assignedTo($agent)->resolved()->create(['contact_id' => $contact->contact_id]);
        }
    }
}
