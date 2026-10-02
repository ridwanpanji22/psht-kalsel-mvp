<article class="psht-card border-l-4 border-l-psht-bright p-5" style="margin-inline-start: {{ min($depth, 5) * 1.25 }}rem" data-unit-name="{{ $unit->name }}">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="text-lg font-semibold text-white">{{ $unit->name }}</h2>
        <span class="text-xs font-semibold uppercase tracking-[0.14em] text-amber-300">{{ $unit->type->value }}</span>
    </div>
    @if ($unit->supervisingBranch)
        <p class="mt-2 text-sm text-stone-300">Dibina oleh {{ $unit->supervisingBranch->name }}</p>
    @endif
    @if ($unit->children->isNotEmpty())
        <ul class="tree-children mt-4 space-y-3" aria-label="Unit bawahan {{ $unit->name }}">
            @foreach ($unit->children as $child)
                <li>@include('public.organization.unit', ['unit' => $child, 'depth' => $depth + 1])</li>
            @endforeach
        </ul>
    @endif
</article>
