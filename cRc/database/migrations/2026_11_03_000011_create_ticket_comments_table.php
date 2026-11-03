<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // user_id / contact_id use RESTRICT (not SET NULL): SET NULL could blank both
        // columns and break the CHECK. Users/contacts are never hard-deleted anyway.
        Schema::create('ticket_comments', function (Blueprint $table) {
            $table->increments('comment_id');
            $table->unsignedInteger('ticket_id');
            $table->unsignedInteger('user_id')->nullable();
            $table->unsignedInteger('contact_id')->nullable();
            $table->text('message');
            $table->boolean('is_internal')->default(false);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['ticket_id', 'created_at'], 'idx_tc_ticket_time');
            $table->index('user_id', 'idx_tc_user');
            $table->index('contact_id', 'idx_tc_contact');

            $table->foreign('ticket_id', 'fk_tc_ticket')
                ->references('ticket_id')->on('tickets')->cascadeOnDelete();
            $table->foreign('user_id', 'fk_tc_user')
                ->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('contact_id', 'fk_tc_contact')
                ->references('contact_id')->on('contacts')->restrictOnDelete();
        });

        DB::statement('ALTER TABLE ticket_comments ADD CONSTRAINT chk_tc_author CHECK (user_id IS NOT NULL OR contact_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_comments');
    }
};
