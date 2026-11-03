<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1-1 with contacts: contact_id is both primary key and foreign key.
        Schema::create('contact_profiles', function (Blueprint $table) {
            $table->unsignedInteger('contact_id')->primary();
            $table->string('alt_phone', 30)->nullable();
            $table->date('birthday')->nullable();
            $table->string('language', 40)->nullable();
            $table->string('preferred_time', 40)->nullable();
            $table->text('notes')->nullable();

            $table->foreign('contact_id', 'fk_profiles_contact')
                ->references('contact_id')->on('contacts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_profiles');
    }
};
