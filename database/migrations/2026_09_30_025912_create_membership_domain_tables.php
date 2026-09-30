<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('people', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('person_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('organization_unit_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->string('status')->default('active');
            $table->string('member_number', 100)->nullable()->unique();
            $table->date('started_on')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['organization_unit_id', 'type', 'status']);
        });

        Schema::create('membership_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('membership_id')->constrained()->restrictOnDelete();
            $table->foreignId('requested_by_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->json('payload');
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });

        Schema::create('audit_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject_type');
            $table->unsignedBigInteger('subject_id');
            $table->string('action');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('membership_status_histories');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('people');
    }
};
