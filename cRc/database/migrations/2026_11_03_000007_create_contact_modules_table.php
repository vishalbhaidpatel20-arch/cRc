<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // M-M: contacts <-> product_modules
        Schema::create('contact_modules', function (Blueprint $table) {
            $table->unsignedInteger('contact_id');
            $table->unsignedInteger('module_id');

            $table->primary(['contact_id', 'module_id']);
            $table->index('module_id', 'idx_cm_module');

            $table->foreign('contact_id', 'fk_cm_contact')
                ->references('contact_id')->on('contacts')->cascadeOnDelete();
            $table->foreign('module_id', 'fk_cm_module')
                ->references('module_id')->on('product_modules')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_modules');
    }
};
