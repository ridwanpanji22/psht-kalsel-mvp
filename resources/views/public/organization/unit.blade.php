<article class="rounded-xl border border-stone-200 bg-white p-5" style="margin-inline-start: {{ min($depth, 5) * 1.25 }}rem">
    <h2 class="font-semibold">{{ $unit->name }}</h2>
    <p class="mt-1 text-sm text-stone-600">{{ $unit->type->value }}</p>
    @if ($unit->supervisingBranch)
        <p class="mt-2 text-sm text-emerald-800">Dibina oleh {{ $unit->supervisingBranch->name }}</p>
    @endif
    @if ($unit->children->isNotEmpty())
        <ul class="mt-4 space-y-3" aria-label="Unit bawahan {{ $unit->name }}">
            @foreach ($unit->children as $child)
                <li>@include('public.organization.unit', ['unit' => $child, 'depth' => $depth + 1])</li>
            @endforeach
        </ul>
    @endif
</article>
