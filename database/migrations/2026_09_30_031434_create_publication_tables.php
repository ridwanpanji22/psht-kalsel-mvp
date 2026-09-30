<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('kind');
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('body');
            $table->string('status')->default('draft');
            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });

        Schema::create('galleries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_unit_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_unit_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->string('venue')->nullable();
            $table->string('status')->default('draft');
            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_at']);
        });

        Schema::create('training_locations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organization_unit_id')->constrained()->restrictOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('address');
            $table->string('contact')->nullable();
            $table->text('schedule')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('status')->default('draft');
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('submitted_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'organization_unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('training_locations');
        Schema::dropIfExists('events');
        Schema::dropIfExists('galleries');
        Schema::dropIfExists('pages');
    }
};
