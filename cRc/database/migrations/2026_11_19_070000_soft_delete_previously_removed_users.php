<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Before soft deletes, a deleted agent was anonymised in place: "Deleted agent #N" with the address
 * deleted-N@removed.invalid and no deleted_at. Mark those rows as soft-deleted so they stay hidden now that
 * the app tells deleted users apart by deleted_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('deleted_at')
            ->where('email', 'like', 'deleted-%@removed.invalid')
            ->update(['deleted_at' => now(), 'is_active' => false]);
    }

    public function down(): void
    {
        // Nothing to undo: the old anonymised rows cannot be told apart from a later soft delete.
    }
};
