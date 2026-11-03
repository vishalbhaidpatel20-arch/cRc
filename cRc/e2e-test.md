# E2E test data

Single place to keep track of the data the end-to-end tests use, and the data for manual
testing. Update this file whenever a journey test or the seed data changes.

## 1. How the E2E tests run

| Item | Value |
|---|---|
| Location | `tests/Feature/E2E/` |
| Run all E2E | `php artisan test tests/Feature/E2E` |
| Run with test names | `php artisan test tests/Feature/E2E --testdox` |
| Run the whole suite | `php artisan test` |
| Database | `crc_test` (MariaDB), set in `phpunit.xml` |
| Isolation | `RefreshDatabase`: every test starts from empty tables |
| Dev database | `crc`. **Tests never touch it.** Never run `migrate:fresh`/`db:seed` against it from a test. |
| Login in tests | Real `POST /login` (session + middleware), via `JourneyHelpers::loginAs()` |
| Not covered | Real browser, JavaScript, CSRF rejection (Laravel disables CSRF in tests) |

The `crc_test` database must exist once: `CREATE DATABASE crc_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
Tables are created by the migrations on the first test run.

This file covers the data of the E2E journeys (section 3) and of the earlier feature tests (section 3b).

## 2. Where test data comes from

No shared fixtures or seed file: **each test builds its own data**, so tests stay independent.

| Source | Used for |
|---|---|
| `User::factory()` | Users. States: `admin()`, `agent()`, `inactive()`, `invited()` (no password) |
| `Company::factory()` | Companies (random name, code `CMP####`, active) |
| `Contact::factory()` | Contacts (random person, linked company, role `end_user`, channel `email`) |
| `JourneyHelpers` | `makeAdmin()`, `makeAgent()`, `loginAs()`, `logout()`, `browse()`, `submit()` |

**Default password for every factory user: `password`.** (`loginAs($email)` uses it automatically.)
Factory defaults: `role=agent`, `all_companies=1`, `is_active=1`.

## 3. Fixed values used by each journey

Only values the tests assert on are listed. Everything else is random from the factories.

### AdminOnboardingJourneyTest
| Data | Value |
|---|---|
| Admin | name `Boss Admin` (random email), password `password` |
| Companies | `Journey One` (J1), `Journey Two` (J2), `Journey Three` (J3) |
| New agent | `Rita Restricted`, `rita@example.com`, phone `9000000001` |
| Agent temp password | `temp-pass-123` |
| Agent access | restricted (`all_companies=0`) to Journey One + Journey Two |
| Extra field sent | `role=admin` (must be ignored; agent stays `agent`) |
| Bad submission | password `short`, confirmation `nope` (must create nothing) |

### ClientDataEntryJourneyTest
| Data | Value |
|---|---|
| Bystander company | `Bystander Ltd` with contact `Unrelated Uma`, phone `1112223334` (must never match searches for Globex) |
| New company | `Globex Pvt Ltd`, industry `Manufacturing`, city `Surat` (the test also sends `company_code=HACKED`, which must be ignored) |
| Contact 1 | `Alice Anderson`, `alice@globex.example`, `9811100001`, Buyer, primary |
| Contact 2 | `Bob Brown`, `bob@globex.example`, `9822200002`, Manager, primary (takes the flag from Alice) |
| Duplicate-email check | `Dupe` using `alice@globex.example` (must be rejected) |
| Edit | Alice renamed to `Alice Archer`, position `Head of Procurement` |

### AgentReadOnlyJourneyTest
| Data | Value |
|---|---|
| Agent | name `Read Only Rae`, password `password` |
| Companies | `Acme Corp` (Nashik), `Hooli Inc` (Vapi) |
| Contacts | `Carol Clark` (`carol@acme.example`, `9700000001`), `Dan Dixon` (`9700000002`) |
| Restricted-agent check | agent with `all_companies=0` assigned `Assigned Co`; must still see `Assigned Person` and `Unassigned Person` (company `Unassigned Co`) |

### SecurityJourneyTest
| Data | Value |
|---|---|
| Users | random admin / agent via factories; invited user `invited@example.com` (no password) |
| Other emails | `x@example.com` (bad logins for the throttle test), `target@example.com`, `victim@example.com` |
| Company | `Some Co` plus a random company and contact for the URL list |
| Throttle | 5 bad attempts allowed per minute; the 6th returns 429 |

## 3b. Fixed values used by the earlier (non-E2E) feature tests

These are single-feature tests in `tests/Feature/`. They use the same rules as section 2
(`crc_test`, `RefreshDatabase`, factories, default password `password`).

### AuthTest (milestone 1, 15 tests)
| Data | Value |
|---|---|
| Users | factory admin / agent / inactive / invited users with random emails, password `password` |
| Wrong password | `wrong` |
| Unknown email | `nobody@example.com` (must get the same generic error as a wrong password) |
| Throttle | `x@example.com` with password `bad`, 6 attempts: the 6th returns 429 |
| Invited user | factory `invited()` state (NULL password): login with empty and with `password` must fail |

### AgentManagementTest (admin creates agents, 10 tests)
| Data | Value |
|---|---|
| New agent | `New Agent`, `new.agent@example.com`, phone `9000000099` |
| Temp password | `secret-pass-1` (confirmation must match; stored hashed) |
| Weak password check | `short` (rejected, minimum 8) |
| Duplicate email check | email of an existing factory user |
| Extra field sent | `role=admin` (must be ignored) |
| Companies | `T One` (T1), `T Two` (T2), `T Three` (T3): agent restricted to T1 + T2; restricted with none selected must fail |
| List check | agent named `Listed Agent` appears on the Users page |
| Public registration | `GET`/`POST /register` must return 404 |

### CompanyContactTest (milestone 2, 18 tests)
| Data | Value |
|---|---|
| Visibility check | company `Visible Co`, contact `Visible Person`, viewed by a restricted agent (`all_companies=0`) |
| Agent write attempts | company `Hack Co` and rename `Renamed` (both must be refused, 403, nothing saved) |
| Created company | `Fresh Client`, city `Pune` (code must be `CMP###` built from its id) |
| Unique name check | `Taken Name` |
| Code is read-only | company code `CMP777`; rename to `Renamed Co` with a fake `company_code=HACKED` (code must stay `CMP777`) |
| Contact payload | `Test Person`, `test.person@example.com`, `9811111111`, role `end_user`, channel `call` |
| Contact validation | `dup@example.com` (duplicate), company id `99999` (missing), role `boss` (invalid) |
| Keep own email on edit | `same@example.com`, name changed to `Edited Name` |
| Primary contact | first contact primary, second created primary: the first loses the flag |
| Deactivation | contact `Soon Hidden` (hidden by default, visible under `status=inactive` / `all`) |
| Search | company `Zebra Traders`, contact `Quentin Findme`, `quentin@findme.example`, `9876501234`; decoy `Other Person`. Terms tried: `Quentin`, `findme.example`, `98765012`, `Zebra` |
| Company filter | `In Company A` vs `In Company B` |
| Company search | `Searchable Ltd` (city `Rajkot`, 3 contacts) vs decoy `Different Inc` |
| Wildcard check | search term `%` must not match `Plain Person` |

### Unit
`tests/Unit/ExampleTest.php`: Laravel's default placeholder (1 test, no data).

### Unit tests (models, middleware, factories, database; 87 tests)
| Data | Value |
|---|---|
| User | password `plain-secret` (must be hashed, never stored plain); `Fill Test` with `user_id=999` (id must be ignored); factory default password `password` |
| Company search | names `Zebra Traders`, `50% Off Ltd`, `A_c Corp` vs `Abc Corp`; code `XYZ123`; city `Rajkot`; industry `Textiles`; decoy `Decoy`. Terms: `Zebra`, `zEBRA`, `  Zebra  `, `%`, `A_c` |
| Contact search | `Quentin Findme`, `q@findme.example`, phone `9876501234` (term `98765012`), whatsapp `9555500000`, position `Chief Wizard`, business `Gadget Emporium`, company `Zebra Traders`; `100% Owner`, `A_c Person` vs `Abc Person` |
| Contact enums | `PRODUCT_ROLES` / `CHANNELS` are compared with the `contacts` enum columns in `information_schema` |
| Middleware | roles `admin` / `agent`; inactive user must get `Your account has been deactivated.` and a redirect to `route('login')` |
| Trigger | interactions at `2026-03-01 10:00:00`, `2026-03-05 09:30:00`, back-dated `2026-02-01 08:00:00` |
| Schema guards | ticket `TKT-0001`, comment `orphan` (no author, must be rejected by `chk_tc_author`), duplicate email `dup@example.com` |

## 4. Manual-testing data (dev database `crc`)

Loaded from `database/sql/03_seed.sql` after `php artisan migrate`. Not used by the automated tests.

| User | Role | Notes |
|---|---|---|
| `anita@support.example` | admin | password `password` |
| `rahul@support.example` | agent | password `password`, all companies |
| `priya@support.example` | agent | password `password`, only Acme + Bluewave |
| `karan@support.example` | agent | no password yet (invited): login must fail |
| `vishal@example.com` | admin | created by hand in `crc`; password is kept by the owner, **not recorded here** |

| Companies | Contacts (count) |
|---|---|
| CMP001 Acme Traders | 3 |
| CMP002 Bluewave Foods | 2 |
| CMP003 Crescent Textiles | 2 |
| CMP004 Delta Logistics | 2 (company has no tickets) |

Also seeded: 8 tickets, assignments, comments, 10 interactions, 7 follow-ups, 4 message templates and
2 modules ("Module 1", "Module 2"). Dates are relative to the day the seed was loaded.

Rebuild the dev data from scratch: run `database/sql/00_reset.sql`, then `php artisan migrate`,
then load `database/sql/03_seed.sql`. This deletes any records entered by hand.

## 5. Rules for keeping this file current

1. New journey or new fixed value in a test: add it to section 3 in the same change.
2. Prefer factories with random data; use fixed names only when the test asserts on them.
3. Never put real client data or real passwords in tests or in this file. Use made-up names.
4. Tests must create their own data. Do not rely on rows from `crc` or on test order.
5. If the seed users or companies change, update section 4.
6. Add a new route? `SecurityJourneyTest` fails until it is added to its protected-URL list. Update the list and
   mention it here if it needs new data.

## 6. Test case index

Suite total: **145 tests** = 15 AuthTest + 10 AgentManagementTest + 18 CompanyContactTest + 1 Unit placeholder + 87 unit tests + 14 E2E.
Per-test names for the earlier feature tests: `php artisan test tests/Feature --testdox`.

### Unit test classes (87)
| Class | Tests | Folder |
|---|---|---|
| UserTest | 16 | tests/Unit/Models |
| CompanyTest | 14 | tests/Unit/Models |
| ContactTest | 17 | tests/Unit/Models |
| EnsureUserHasRoleTest | 5 | tests/Unit/Middleware |
| EnsureUserIsActiveTest | 6 | tests/Unit/Middleware |
| FactoryTest | 12 | tests/Unit/Factories |
| InteractionTriggerTest | 4 | tests/Unit/Database |
| SchemaGuardTest | 13 | tests/Unit/Database |

Per-test names: `php artisan test tests/Unit --testdox`.

### E2E journeys (14)

| # | Test | File |
|---|---|---|
| 1 | Admin onboards a restricted agent who logs in and is kept out of admin areas | AdminOnboardingJourneyTest |
| 2 | Logged-in users are bounced away from the login page | AdminOnboardingJourneyTest |
| 3 | Admin enters a client company and its contacts end to end | ClientDataEntryJourneyTest |
| 4 | Nothing can be deleted, even by an admin in session | ClientDataEntryJourneyTest |
| 5 | Agent browses and searches but cannot change anything | AgentReadOnlyJourneyTest |
| 6 | Restricted agent still sees every company and contact | AgentReadOnlyJourneyTest |
| 7 | Guest is redirected to login from every protected URL | SecurityJourneyTest |
| 8 | Every registered route is either public or in the protected list | SecurityJourneyTest |
| 9 | There is no registration flow, even when logged in | SecurityJourneyTest |
| 10 | Deactivated agent is logged out mid-session and cannot log in again | SecurityJourneyTest |
| 11 | Invited users without a password cannot log in | SecurityJourneyTest |
| 12 | Login is throttled after five bad attempts | SecurityJourneyTest |
| 13 | Wrong role gets 403 in both directions and the right role recovers | SecurityJourneyTest |
| 14 | Logout really ends the session | SecurityJourneyTest |
