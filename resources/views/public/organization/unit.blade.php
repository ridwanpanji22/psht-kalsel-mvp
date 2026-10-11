@php
    // The tree is rendered server-side in full: every active unit reaches the
    // HTML so the page works without JavaScript and search engines still see
    // the whole hierarchy. `resources/js/organization-tree.js` only adds
    // collapse, search, and type filtering on top of this markup.
    $hasChildren = $unit->children->isNotEmpty();
    $childCount = $unit->children->count();
@endphp
<li>
    <article
        class="psht-card organization-unit-card border-l-4 p-4 sm:p-5"
        data-tree-node
        data-unit-name="{{ $unit->name }}"
        data-unit-type="{{ $unit->type->value }}"
        data-depth="{{ $depth }}"
    >
        @if ($hasChildren)
            <button
                type="button"
                class="organization-unit-toggle flex w-full flex-wrap items-baseline justify-between gap-2 text-left"
                data-tree-toggle
                aria-expanded="true"
                aria-controls="unit-children-{{ $unit->id }}"
                aria-label="Buka atau tutup unit bawahan {{ $unit->name }}"
            >
                <span class="text-lg font-semibold text-white break-words">{{ $unit->name }}</span>
                <span class="flex items-center gap-2">
                    <span class="rounded-md border border-white/15 px-2 py-0.5 text-xs font-semibold uppercase tracking-[0.14em] text-amber-300">{{ $unit->type->value }}</span>
                    <span class="rounded-full bg-white/10 px-2 py-0.5 text-xs font-semibold text-stone-200" data-tree-badge>{{ $childCount }}</span>
                    <span class="organization-unit-chevron text-stone-400" aria-hidden="true">&rsaquo;</span>
                </span>
            </button>
        @else
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <span class="text-lg font-semibold text-white break-words">{{ $unit->name }}</span>
                <span class="rounded-md border border-white/15 px-2 py-0.5 text-xs font-semibold uppercase tracking-[0.14em] text-amber-300">{{ $unit->type->value }}</span>
            </div>
        @endif

        @if ($unit->supervisingBranch)
            <p class="mt-2 text-sm text-stone-300">Dibina oleh {{ $unit->supervisingBranch->name }}</p>
        @endif

        @if ($hasChildren)
            <ul
                id="unit-children-{{ $unit->id }}"
                class="organization-children mt-4 space-y-3"
                data-tree-children
                aria-label="Unit bawahan {{ $unit->name }}"
            >
                @foreach ($unit->children as $child)
                    @include('public.organization.unit', ['unit' => $child, 'depth' => $depth + 1])
                @endforeach
            </ul>
        @endif
    </article>
</li>
