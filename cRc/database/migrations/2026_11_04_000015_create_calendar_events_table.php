<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Personal plans on the calendar (admins and agents each schedule their own). start_at is stored in UTC. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_events', function (Blueprint $table) {
            $table->increments('event_id');
            $table->unsignedInteger('user_id');
            $table->string('title', 150);
            $table->text('notes')->nullable();
            $table->dateTime('start_at')->comment('UTC');
            $table->boolean('all_day')->default(false);
            $table->unsignedSmallInteger('remind_minutes')->nullable()->comment('minutes before start_at; NULL = no reminder');
            $table->dateTime('reminded_at')->nullable()->comment('set once the reminder notification was sent');
            $table->enum('status', ['planned', 'done'])->default('planned');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'start_at'], 'idx_ce_user_start');
            $table->index(['status', 'reminded_at', 'start_at'], 'idx_ce_reminders');

            $table->foreign('user_id', 'fk_ce_user')->references('user_id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_events');
    }
};
