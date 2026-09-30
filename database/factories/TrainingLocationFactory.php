<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\OrganizationUnit;
use App\Models\TrainingLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TrainingLocation>
 */
class TrainingLocationFactory extends Factory
{
    protected $model = TrainingLocation::class;

    public function definition(): array
    {
        return [
            'organization_unit_id' => OrganizationUnit::factory(),
            'slug' => fake()->unique()->slug(),
            'name' => fake()->streetName(),
            'address' => fake()->address(),
            'contact' => null,
            'schedule' => null,
            'latitude' => '-3.3186',
            'longitude' => '114.5944',
            'status' => PublicationStatus::Draft,
            'verified_at' => null,
            'submitted_by_id' => null,
            'published_by_id' => null,
            'published_at' => null,
        ];
    }
}
