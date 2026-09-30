<?php

namespace Database\Factories;

use App\Models\Gallery;
use App\Models\Media;
use App\Models\OrganizationUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    public function definition(): array
    {
        return [
            'gallery_id' => Gallery::factory(),
            'organization_unit_id' => OrganizationUnit::factory(),
            'disk' => 'local',
            'path' => fake()->uuid().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'alt_text' => fake()->sentence(),
            'is_public' => false,
        ];
    }
}
