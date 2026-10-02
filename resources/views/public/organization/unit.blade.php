@php
    // Indentation compounds: every nested level adds its own inline-start
    // margin on top of the parent list's padding. Uncapped, a level-3+ card at
    // a narrow viewport collapses to a few pixels and its heading overflows.
    // The per-card contribution therefore stops growing after level 2 and the
    // CSS below reduces padding/type for levels deeper than the first two, so
    // deep hierarchies stay indented but readable. See resources/css/app.css.
    $indentRem = min($depth, 2) * 1.25;
@endphp
<article class="psht-card organization-unit-card border-l-4 border-l-psht-bright p-5" style="width: 100%; {{ $depth > 3 ? 'min-width: 40rem; ' : '' }}margin-inline-start: {{ $indentRem }}rem" data-unit-name="{{ $unit->name }}">
    <div class="flex flex-wrap items-baseline justify-between gap-2">
        <h2 class="text-lg font-semibold text-white break-words">{{ $unit->name }}</h2>
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
