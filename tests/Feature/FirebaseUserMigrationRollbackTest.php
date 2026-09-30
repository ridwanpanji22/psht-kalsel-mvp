<?php

namespace Tests\Feature;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FirebaseUserMigrationRollbackTest extends TestCase
{
    /**
     * @var list<Migration>
     */
    private array $restoredMigrations = [];

    protected function tearDown(): void
    {
        foreach ($this->restoredMigrations as $migration) {
            $migration->up();
        }

        $this->restoredMigrations = [];

        parent::tearDown();
    }

    public function test_migration_rolls_back_when_pending_user_has_null_name_without_losing_rows(): void
    {
        $migration = $this->firebaseMigration();

        $tableExisted = Schema::hasTable('users');

        if ($tableExisted) {
            $this->assertFalse(Schema::hasColumn('users', 'firebase_uid'));
            $this->assertFalse(Schema::hasColumn('users', 'onboarded_at'));
        }

        $migration->up();
        $this->restoredMigrations[] = $migration;

        $this->assertTrue(Schema::hasColumn('users', 'firebase_uid'));
        $this->assertTrue(Schema::hasColumn('users', 'onboarded_at'));

        $pendingId = DB::table('users')->insertGetId([
            'name' => null,
            'email' => 'pending@example.test',
            'password' => 'secret',
            'is_active' => false,
            'firebase_uid' => 'pending-firebase-uid',
            'onboarded_at' => null,
        ]);

        $migration->down();

        $this->assertTrue(Schema::hasTable('users'));
        $this->assertFalse(Schema::hasColumn('users', 'firebase_uid'));
        $this->assertFalse(Schema::hasColumn('users', 'onboarded_at'));

        $row = DB::table('users')->where('id', $pendingId)->first();

        $this->assertNotNull($row, 'The pending user row must survive rollback.');
        $this->assertSame('pending@example.test', $row->email);
        $this->assertNotNull($row->name);
        $this->assertNotSame('', $row->name);
    }

    private function firebaseMigration(): Migration
    {
        if (! Schema::hasTable('users')) {
            $this->createMinimalUsersTable();
        }

        /** @var Migration $migration */
        $migration = require database_path(
            'migrations/2026_10_01_000000_update_users_table_for_firebase_auth.php'
        );

        return $migration;
    }

    private function createMinimalUsersTable(): void
    {
        try {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->boolean('is_active')->default(true);
                $table->rememberToken();
                $table->timestamps();
            });
        } catch (QueryException) {
            // The users table was created concurrently; it is safe to continue.
        }
    }
}
