<?php

namespace Database\Seeders\Support;

use App\Models\OrganizationUnit;
use App\Models\User;
use LogicException;

/**
 * In-memory references shared between demo seeders.
 *
 * Stores the province, keyed unit collections (branches, rantings, rayons,
 * special units), and keyed operators. Each seeder receives the same instance
 * so later stages can resolve records created by earlier stages without
 * re-querying by guesswork.
 */
class DemoSeedContext
{
    private ?OrganizationUnit $province = null;

    /**
     * @var array<string, OrganizationUnit>
     */
    private array $units = [];

    /**
     * @var array<string, User>
     */
    private array $operators = [];

    /**
     * Deterministic natural keys of seeded publication records, grouped by
     * model class. Later seeders (e.g. the workflow seeder) use these to scope
     * their side effects to records this run created, never to unrelated rows.
     *
     * @var array<class-string, list<string>>
     */
    private array $publicationKeys = [];

    public function province(): OrganizationUnit
    {
        return $this->province ?? throw new LogicException('Konteks demo belum memiliki unit provinsi.');
    }

    public function setProvince(OrganizationUnit $province): void
    {
        $this->province = $province;
    }

    /**
     * @return array<string, OrganizationUnit>
     */
    public function branches(): array
    {
        return $this->unitsOfType('branch');
    }

    /**
     * @return array<string, OrganizationUnit>
     */
    public function rantings(): array
    {
        return $this->unitsOfType('ranting');
    }

    /**
     * @return array<string, OrganizationUnit>
     */
    public function rayons(): array
    {
        return $this->unitsOfType('rayon');
    }

    /**
     * @return array<string, OrganizationUnit>
     */
    public function specialUnits(): array
    {
        return $this->unitsOfType('special');
    }

    public function putUnit(string $key, OrganizationUnit $unit): void
    {
        $this->units[$key] = $unit;
    }

    public function unit(string $key): OrganizationUnit
    {
        return $this->units[$key] ?? throw new LogicException("Unit demo dengan key [{$key}] tidak ditemukan.");
    }

    public function hasUnit(string $key): bool
    {
        return isset($this->units[$key]);
    }

    public function putOperator(string $key, User $user): void
    {
        $this->operators[$key] = $user;
    }

    public function operator(string $key): User
    {
        return $this->operators[$key] ?? throw new LogicException("Operator demo dengan key [{$key}] tidak ditemukan.");
    }

    /**
     * @return array<string, User>
     */
    public function operators(): array
    {
        return $this->operators;
    }

    /**
     * Record the deterministic natural keys of a seeded publication model.
     *
     * @param  class-string  $modelClass
     * @param  list<string>  $keys
     */
    public function putPublicationKeys(string $modelClass, array $keys): void
    {
        $this->publicationKeys[$modelClass] = array_values($keys);
    }

    /**
     * The deterministic natural keys recorded for a seeded publication model.
     *
     * @param  class-string  $modelClass
     * @return list<string>
     */
    public function publicationKeys(string $modelClass): array
    {
        return $this->publicationKeys[$modelClass] ?? [];
    }

    /**
     * @return array<string, OrganizationUnit>
     */
    private function unitsOfType(string $prefix): array
    {
        $matches = [];

        foreach ($this->units as $key => $unit) {
            if (str_starts_with($key, $prefix.'-')) {
                $matches[$key] = $unit;
            }
        }

        return $matches;
    }
}
