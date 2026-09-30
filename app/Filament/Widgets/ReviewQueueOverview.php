<?php

namespace App\Filament\Widgets;

use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use App\Models\Event;
use App\Models\TrainingLocation;
use App\Support\OrganizationScope;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ReviewQueueOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $unitIds = app(OrganizationScope::class)->visibleUnitIds(auth()->user());

        return [
            Stat::make('Persetujuan menunggu', (string) ApprovalRequest::query()
                ->where('status', ApprovalStatus::Pending)
                ->whereHas('membership', fn ($query) => $query->whereIn('organization_unit_id', $unitIds))
                ->count()),
            Stat::make('Agenda mendatang', (string) Event::query()
                ->whereIn('organization_unit_id', $unitIds)
                ->where('starts_at', '>=', now())
                ->count()),
            Stat::make('Lokasi belum terverifikasi', (string) TrainingLocation::query()
                ->whereIn('organization_unit_id', $unitIds)
                ->whereNull('verified_at')
                ->count()),
        ];
    }
}
