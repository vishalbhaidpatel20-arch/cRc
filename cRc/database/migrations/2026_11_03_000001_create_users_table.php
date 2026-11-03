<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('user_id');
            $table->string('full_name', 120);
            $table->string('email', 190)->unique('uq_users_email');
            $table->string('phone', 30)->nullable();
            $table->string('password_hash')->nullable()->comment('NULL until the invite is accepted');
            $table->enum('role', ['admin', 'agent'])->default('agent');
            $table->boolean('all_companies')->default(true);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('created_by', 'fk_users_created_by')
                ->references('user_id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
