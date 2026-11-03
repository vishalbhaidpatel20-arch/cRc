<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Module names are entered by the admin, never hard-coded.
        Schema::create('product_modules', function (Blueprint $table) {
            $table->increments('module_id');
            $table->string('name', 100)->unique('uq_modules_name');
            $table->string('description', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_modules');
    }
};
