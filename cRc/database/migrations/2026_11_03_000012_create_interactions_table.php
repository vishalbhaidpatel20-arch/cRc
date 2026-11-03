<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interactions', function (Blueprint $table) {
            $table->increments('interaction_id');
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('ticket_id')->nullable();
            $table->enum('topic', ['query', 'bug', 'customization', 'follow_up', 'general'])->default('general');
            $table->enum('channel', ['call', 'whatsapp', 'email', 'meeting']);
            $table->enum('direction', ['inbound', 'outbound']);
            $table->text('summary');
            $table->dateTime('interaction_at');

            $table->index(['contact_id', 'interaction_at'], 'idx_int_contact_time');
            $table->index(['user_id', 'interaction_at'], 'idx_int_user_time');
            $table->index('ticket_id', 'idx_int_ticket');
            $table->index('interaction_at', 'idx_int_time');
            $table->fullText('summary', 'ft_int_summary');

            $table->foreign('contact_id', 'fk_int_contact')
                ->references('contact_id')->on('contacts')->restrictOnDelete();
            $table->foreign('user_id', 'fk_int_user')
                ->references('user_id')->on('users')->restrictOnDelete();
            $table->foreign('ticket_id', 'fk_int_ticket')
                ->references('ticket_id')->on('tickets')->nullOnDelete();
        });

        // Keep contacts.last_contacted_at current. GREATEST + COALESCE: a back-dated
        // entry never moves the date backwards, and the first entry works on NULL.
        DB::unprepared('DROP TRIGGER IF EXISTS trg_interactions_after_insert');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_interactions_after_insert
            AFTER INSERT ON interactions
            FOR EACH ROW
            BEGIN
                UPDATE contacts
                   SET last_contacted_at = GREATEST(COALESCE(last_contacted_at, NEW.interaction_at), NEW.interaction_at)
                 WHERE contact_id = NEW.contact_id;
            END
            SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_interactions_after_insert');
        Schema::dropIfExists('interactions');
    }
};
