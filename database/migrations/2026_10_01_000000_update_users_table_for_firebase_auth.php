<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable()->change();
            $table->string('firebase_uid', 128)->nullable()->unique()->after('password');
            $table->timestamp('onboarded_at')->nullable()->after('firebase_uid');
        });
    }

    public function down(): void
    {
        // Pending Firebase-invited users may legitimately have a null name. Restoring
        // the NOT NULL constraint must not abort on those rows nor lose their data, so
        // backfill a safe non-empty value derived from the email before tightening it.
        DB::table('users')
            ->whereNull('name')
            ->orWhere('name', '')
            ->update([
                'name' => DB::raw("COALESCE(NULLIF(TRIM(email), ''), 'Pending User')"),
            ]);

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['firebase_uid']);
            $table->dropColumn(['firebase_uid', 'onboarded_at']);
        });

        // Rebuild the table so the NOT NULL change replays safely; SQLite's native
        // ->change() rebuild can otherwise abort on the temp-copy COPY step.
        Schema::table('users', function (Blueprint $table): void {
            $table->string('name')->nullable(false)->change();
        });
    }
};
