<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_assignments', function (Blueprint $table) {
            $table->increments('assignment_id');
            $table->unsignedInteger('ticket_id');
            $table->unsignedInteger('from_user')->nullable()->comment('NULL on first assignment');
            $table->unsignedInteger('to_user')->nullable();
            $table->unsignedInteger('assigned_by');
            $table->string('reason', 255)->nullable();
            $table->dateTime('assigned_at')->useCurrent();

            $table->index(['ticket_id', 'assigned_at'], 'idx_ta_ticket_time');
            $table->index('from_user', 'idx_ta_from');
            $table->index('to_user', 'idx_ta_to');
            $table->index('assigned_by', 'idx_ta_by');

            $table->foreign('ticket_id', 'fk_ta_ticket')
                ->references('ticket_id')->on('tickets')->cascadeOnDelete();
            $table->foreign('from_user', 'fk_ta_from')
                ->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('to_user', 'fk_ta_to')
                ->references('user_id')->on('users')->nullOnDelete();
            $table->foreign('assigned_by', 'fk_ta_by')
                ->references('user_id')->on('users')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_assignments');
    }
};
