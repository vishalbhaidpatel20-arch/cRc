<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_ups', function (Blueprint $table) {
            $table->increments('follow_up_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('interaction_id')->nullable();
            $table->unsignedInteger('assigned_to');
            $table->date('due_date');
            $table->string('description', 255);
            $table->enum('status', ['pending', 'done', 'cancelled'])->default('pending');
            $table->dateTime('completed_at')->nullable();

            $table->index(['assigned_to', 'status', 'due_date'], 'idx_fu_assignee_status_due');
            $table->index('contact_id', 'idx_fu_contact');
            $table->index('interaction_id', 'idx_fu_interaction');

            $table->foreign('contact_id', 'fk_fu_contact')
                ->references('contact_id')->on('contacts')->cascadeOnDelete();
            $table->foreign('interaction_id', 'fk_fu_interaction')
                ->references('interaction_id')->on('interactions')->nullOnDelete();
            $table->foreign('assigned_to', 'fk_fu_assignee')
                ->references('user_id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_ups');
    }
};
