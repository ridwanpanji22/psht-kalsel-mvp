<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\Gallery;
use App\Models\OrganizationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Gallery>
 */
class GalleryFactory extends Factory
{
    protected $model = Gallery::class;

    public function definition(): array
    {
        return [
            'organization_unit_id' => OrganizationUnit::factory(),
            'slug' => fake()->unique()->slug(),
            'title' => fake()->sentence(3),
            'description' => null,
            'status' => PublicationStatus::Draft,
            'submitted_by_id' => null,
            'published_by_id' => null,
            'published_at' => null,
        ];
    }
}
