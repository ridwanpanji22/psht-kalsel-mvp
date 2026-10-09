<?php

namespace Database\Seeders;

use Database\Seeders\Support\DemoSeedContext;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Orchestrates the deterministic MVP demo dataset.
 *
 * Refuses to run in production so that demo passwords and dummy identities can
 * never reach a real environment. Later domain seeders are appended to
 * {@see self::run()} in dependency order as they are added.
 */
class DemoDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'Seeder data demo tidak boleh dijalankan pada environment production.',
            );
        }

        $context = new DemoSeedContext;

        $this->callWith(DemoOrganizationSeeder::class, ['context' => $context]);
        $this->callWith(DemoOperatorSeeder::class, ['context' => $context]);
    }
}
