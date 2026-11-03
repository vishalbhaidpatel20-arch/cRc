<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_templates', function (Blueprint $table) {
            $table->increments('template_id');
            $table->string('name', 120);
            $table->enum('category', ['query', 'bug', 'customization', 'follow_up', 'general'])->default('general');
            $table->enum('channel', ['email', 'whatsapp']);
            $table->string('subject', 200)->nullable();
            $table->text('body')->comment('placeholders like {{name}}');
            $table->boolean('is_active')->default(true);

            $table->unique(['name', 'channel'], 'uq_templates_name_channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_templates');
    }
};
