<?php

namespace App\Http\Controllers;

use App\Actions\ApproveWargaConversion;
use App\Enums\ApprovalStatus;
use App\Models\ApprovalRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ApprovalDecisionController extends Controller
{
    public function store(Request $request, ApprovalRequest $approvalRequest, ApproveWargaConversion $approve): RedirectResponse
    {
        Gate::authorize('review', $approvalRequest);

        $validated = $request->validate([
            'decision' => ['required', 'in:approve,return,reject'],
            'review_note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validated['decision'] === 'approve' && $approvalRequest->type === 'warga_conversion') {
            $approve->handle($request->user(), $approvalRequest);

            return back();
        }

        $status = match ($validated['decision']) {
            'return' => ApprovalStatus::Returned,
            'reject' => ApprovalStatus::Rejected,
            default => throw ValidationException::withMessages(['decision' => 'Keputusan tidak didukung.']),
        };

        $approvalRequest->update([
            'status' => $status,
            'reviewed_by_id' => $request->user()->id,
            'review_note' => $validated['review_note'] ?? null,
            'reviewed_at' => now(),
        ]);

        return back();
    }
}
