<?php

namespace App\Filament\Widgets;

use App\Enums\MembershipStatus;
use App\Enums\MembershipType;
use App\Models\Membership;
use App\Models\OrganizationUnit;
use App\Support\OrganizationScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OrganizationOverview extends StatsOverviewWidget
{
    public function getStats(): array
    {
        $unitIds = app(OrganizationScope::class)->visibleUnitIds(auth()->user());
        $memberships = Membership::query()->whereIn('organization_unit_id', $unitIds);
        $unitCount = OrganizationUnit::query()->whereIn('id', $unitIds)->count();

        return [
            Stat::make('Unit dalam cakupan', (string) $unitCount),
            Stat::make('Siswa aktif', (string) (clone $memberships)
                ->where('type', MembershipType::Siswa)
                ->where('status', MembershipStatus::Active)
                ->count()),
            Stat::make('Warga aktif', (string) (clone $memberships)
                ->where('type', MembershipType::Warga)
                ->where('status', MembershipStatus::Active)
                ->count()),
        ];
    }
}
