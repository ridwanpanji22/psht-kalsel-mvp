<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\OrganizationUnit;
use Illuminate\View\View;

class OrganizationController extends Controller
{
    public function index(): View
    {
        $units = OrganizationUnit::query()
            ->where('is_active', true)
            ->with([
                'children' => fn ($query) => $query->where('is_active', true)->orderBy('name'),
                'supervisingBranch:id,name',
            ])
            ->whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name', 'type', 'parent_id', 'supervising_branch_id']);

        return view('public.organization.index', ['units' => $units]);
    }
}
