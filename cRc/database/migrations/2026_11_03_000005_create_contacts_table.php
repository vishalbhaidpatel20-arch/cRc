<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->increments('contact_id');
            $table->unsignedInteger('company_id');
            $table->string('full_name', 120);
            $table->string('email', 190)->unique('uq_contacts_email');
            $table->string('phone', 30)->nullable();
            $table->string('whatsapp_no', 30)->nullable();
            $table->string('position', 100)->nullable();
            $table->string('department', 100)->nullable();
            $table->string('business_name', 150)->nullable();
            $table->enum('product_role', ['end_user', 'admin', 'decision_maker', 'technical', 'finance'])->default('end_user');
            $table->enum('preferred_channel', ['call', 'whatsapp', 'email', 'meeting'])->default('email');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_active')->default(true);
            $table->dateTime('last_contacted_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['company_id', 'is_active'], 'idx_contacts_company_active');
            $table->index('full_name', 'idx_contacts_full_name');
            $table->index('phone', 'idx_contacts_phone');
            $table->index('last_contacted_at', 'idx_contacts_last_contacted');

            $table->foreign('company_id', 'fk_contacts_company')
                ->references('company_id')->on('companies')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};
