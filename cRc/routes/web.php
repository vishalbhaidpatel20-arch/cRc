<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ModuleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\Agent\DashboardController as AgentDashboardController;
use App\Http\Controllers\InteractionController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ContactController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route(auth()->user()->homeRoute())
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});

Route::middleware(['auth', 'active'])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Companies and contacts. Admin-only routes are declared first so that
    // /companies/create is not captured by /companies/{company}.
    Route::middleware('role:admin')->group(function () {
        Route::get('/companies/create', [CompanyController::class, 'create'])->name('companies.create');
        Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::get('/companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
        Route::put('/companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
        Route::patch('/companies/{company}/active', [CompanyController::class, 'toggleActive'])->name('companies.active');

        Route::get('/contacts/create', [ContactController::class, 'create'])->name('contacts.create');
        Route::post('/contacts', [ContactController::class, 'store'])->name('contacts.store');
        Route::get('/contacts/{contact}/edit', [ContactController::class, 'edit'])->name('contacts.edit');
        Route::put('/contacts/{contact}', [ContactController::class, 'update'])->name('contacts.update');
        Route::patch('/contacts/{contact}/active', [ContactController::class, 'toggleActive'])->name('contacts.active');
    });

    // Read-only for every logged-in user (agents can view all companies and contacts).
    Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('/companies/{company}', [CompanyController::class, 'show'])->name('companies.show');
    Route::get('/contacts', [ContactController::class, 'index'])->name('contacts.index');
    Route::get('/contacts/{contact}', [ContactController::class, 'show'])->name('contacts.show');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');

        // Only admins can create agent accounts. There is no public /register route.
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');

        // Product modules (names are entered by the admin, never hard-coded).
        Route::get('/modules', [ModuleController::class, 'index'])->name('modules.index');
        Route::post('/modules', [ModuleController::class, 'store'])->name('modules.store');
        Route::put('/modules/{module}', [ModuleController::class, 'update'])->name('modules.update');
    });

    Route::middleware('role:agent')->prefix('agent')->name('agent.')->group(function () {
        Route::get('/', AgentDashboardController::class)->name('dashboard');
    });

    // Tickets. Admins and agents both use these routes; what an agent can see is decided by the
    // Ticket global scope + TicketPolicy (agents only ever reach tickets assigned to them).
    // Assigning is admin only, enforced here and again in TicketPolicy::assign.
    Route::get('/tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('/tickets/create', [TicketController::class, 'create'])->name('tickets.create');
    Route::post('/tickets', [TicketController::class, 'store'])->name('tickets.store');
    Route::get('/tickets/{ticket}', [TicketController::class, 'show'])->name('tickets.show');
    Route::get('/tickets/{ticket}/edit', [TicketController::class, 'edit'])->name('tickets.edit');
    Route::put('/tickets/{ticket}', [TicketController::class, 'update'])->name('tickets.update');
    Route::post('/tickets/{ticket}/assign', [TicketController::class, 'assign'])
        ->middleware('role:admin')->name('tickets.assign');

    Route::post('/notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read');

    // Conversation log. Everyone logs and reads; entries are append-only (no edit/delete routes).
    Route::get('/interactions', [InteractionController::class, 'index'])->name('interactions.index');
    Route::post('/tickets/{ticket}/interactions', [InteractionController::class, 'storeForTicket'])->name('tickets.interactions.store');
    Route::post('/contacts/{contact}/interactions', [InteractionController::class, 'storeForContact'])->name('contacts.interactions.store');

    Route::patch('/follow-ups/{followUp}/done', [FollowUpController::class, 'done'])->name('follow-ups.done');

    // Message templates: admins manage (declared first so /templates/create is not a {template}), everyone reads.
    Route::middleware('role:admin')->group(function () {
        Route::get('/templates/create', [TemplateController::class, 'create'])->name('templates.create');
        Route::post('/templates', [TemplateController::class, 'store'])->name('templates.store');
        Route::get('/templates/{template}/edit', [TemplateController::class, 'edit'])->name('templates.edit');
        Route::put('/templates/{template}', [TemplateController::class, 'update'])->name('templates.update');
        Route::patch('/templates/{template}/active', [TemplateController::class, 'toggleActive'])->name('templates.active');
    });
    Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
});
