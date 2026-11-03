<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // M-M: users <-> companies (which companies a restricted agent can see)
        Schema::create('user_companies', function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('company_id');

            $table->primary(['user_id', 'company_id']);
            $table->index('company_id', 'idx_uc_company');

            $table->foreign('user_id', 'fk_uc_user')
                ->references('user_id')->on('users')->cascadeOnDelete();
            $table->foreign('company_id', 'fk_uc_company')
                ->references('company_id')->on('companies')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_companies');
    }
};
