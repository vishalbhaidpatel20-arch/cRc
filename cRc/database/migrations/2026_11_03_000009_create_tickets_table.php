<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->increments('ticket_id');
            $table->string('ticket_no', 20)->unique('uq_tickets_no');
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('assigned_to')->nullable()->comment('NULL = unassigned');
            $table->enum('topic', ['query', 'bug', 'customization'])->default('query');
            $table->enum('priority', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'in_progress', 'waiting', 'resolved'])->default('open');
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->dateTime('due_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index(['assigned_to', 'status'], 'idx_tickets_assignee_status');
            $table->index(['status', 'priority'], 'idx_tickets_status_priority');
            $table->index(['company_id', 'status'], 'idx_tickets_company_status');
            $table->index('contact_id', 'idx_tickets_contact');
            $table->index(['status', 'due_at'], 'idx_tickets_status_due');
            $table->index('created_at', 'idx_tickets_created');
            $table->fullText(['title', 'description'], 'ft_tickets_text');

            $table->foreign('company_id', 'fk_tickets_company')
                ->references('company_id')->on('companies')->restrictOnDelete();
            $table->foreign('contact_id', 'fk_tickets_contact')
                ->references('contact_id')->on('contacts')->restrictOnDelete();
            $table->foreign('assigned_to', 'fk_tickets_assignee')
                ->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};
