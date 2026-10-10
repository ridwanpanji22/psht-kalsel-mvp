<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trusted ownership manifest for demo storage artifacts.
 *
 * A file (or media row) is considered demo-owned only when a row here proves
 * that this seeder created/claimed it under a known namespace. The `demo/`
 * path prefix alone is never treated as ownership, so foreign or hand-managed
 * files that merely share a name/path survive reconciliation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demo_artifacts', function (Blueprint $table): void {
            $table->id();
            $table->string('namespace');
            $table->string('disk');
            $table->string('path');
            $table->string('kind', 40);
            $table->string('seed_key');
            $table->string('owner_type')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->timestamps();

            $table->unique(['namespace', 'disk', 'path']);
            $table->index(['namespace', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demo_artifacts');
    }
};
