<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_units', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type');
            $table->foreignId('parent_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->foreignId('supervising_branch_id')->nullable()->constrained('organization_units')->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['type', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_units');
    }
};
