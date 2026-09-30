<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\Event;
use App\Models\OrganizationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    protected $model = Event::class;

    public function definition(): array
    {
        return [
            'organization_unit_id' => OrganizationUnit::factory(),
            'slug' => fake()->unique()->slug(),
            'title' => fake()->sentence(3),
            'description' => null,
            'starts_at' => '2026-10-01 09:00:00',
            'ends_at' => null,
            'venue' => null,
            'status' => PublicationStatus::Draft,
            'submitted_by_id' => null,
            'published_by_id' => null,
            'published_at' => null,
        ];
    }
}
