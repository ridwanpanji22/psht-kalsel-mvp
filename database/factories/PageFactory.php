<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\OrganizationUnit;
use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Page>
 */
class PageFactory extends Factory
{
    protected $model = Page::class;

    public function definition(): array
    {
        return [
            'organization_unit_id' => OrganizationUnit::factory(),
            'kind' => 'history',
            'slug' => fake()->unique()->slug(),
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'status' => PublicationStatus::Draft,
            'submitted_by_id' => null,
            'published_by_id' => null,
            'published_at' => null,
        ];
    }
}
