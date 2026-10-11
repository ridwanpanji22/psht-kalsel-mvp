// Progressive enhancement for the server-rendered organization tree.
//
// The Blade view renders every active unit so the page works without
// JavaScript and crawlers see the full hierarchy. This module only adds
// collapse/expand, name search, and type filtering on top. Because the markup
// is the source of truth, a failed init leaves a fully readable (if long) page
// rather than an empty one.

const COLLAPSED_DEPTHS = ['2', '3'];

export function initOrganizationTree() {
    const tree = document.querySelector('#organization-tree');

    if (!(tree instanceof HTMLElement) || tree.dataset.treeReady === 'true') {
        return;
    }

    tree.dataset.treeReady = 'true';

    const search = document.querySelector('#organization-search');
    const filters = [...document.querySelectorAll('[data-tree-filter]')];
    const result = document.querySelector('.organization-result');
    const expandAll = document.querySelector('[data-tree-expand-all]');
    const collapseAll = document.querySelector('[data-tree-collapse-all]');
    const nodes = [...tree.querySelectorAll('[data-tree-node]')];

    let activeType = 'all';
    let query = '';

    const childrenOf = (node) => node.querySelector(':scope > [data-tree-children]');

    const setCollapsed = (node, collapsed) => {
        const children = childrenOf(node);

        if (!children) {
            return;
        }

        node.dataset.collapsed = collapsed ? 'true' : 'false';
        children.hidden = collapsed;

        const toggle = node.querySelector(':scope > [data-tree-toggle]');

        if (toggle) {
            toggle.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
        }
    };

    const toggleNode = (node) => {
        setCollapsed(node, node.dataset.collapsed !== 'true');
    };

    const matchesFilters = (node) => {
        const name = (node.dataset.unitName || '').toLocaleLowerCase('id');
        const type = node.dataset.unitType || '';
        const matchesQuery = query === '' || name.includes(query);
        const matchesType = activeType === 'all' || type === activeType;

        return matchesQuery && matchesType;
    };

    const applyFilters = () => {
        let visibleCount = 0;

        nodes.forEach((node) => {
            const selfMatch = matchesFilters(node);
            const descendantMatch = query !== ''
                && [...node.querySelectorAll('[data-tree-node]')].some((child) => {
                    const childName = (child.dataset.unitName || '').toLocaleLowerCase('id');

                    return childName.includes(query);
                });
            const show = selfMatch || descendantMatch;

            node.hidden = !show;

            if (show) {
                visibleCount += 1;

                // Auto-expand ancestors so a deep search hit is reachable.
                if (query !== '') {
                    let parent = node.parentElement?.closest('[data-tree-node]');

                    while (parent) {
                        setCollapsed(parent, false);
                        parent = parent.parentElement?.closest('[data-tree-node]');
                    }
                }
            }
        });

        if (result) {
            const total = nodes.length;

            if (query === '' && activeType === 'all') {
                result.textContent = `${total} unit ditampilkan.`;
            } else {
                result.textContent = `${visibleCount} dari ${total} unit cocok.`;
            }
        }
    };

    nodes.forEach((node) => {
        const toggle = node.querySelector(':scope > [data-tree-toggle]');

        if (!toggle) {
            return;
        }

        toggle.addEventListener('click', () => toggleNode(node));
        toggle.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                toggleNode(node);
            }
        });
    });

    if (search instanceof HTMLInputElement) {
        search.addEventListener('input', () => {
            query = search.value.trim().toLocaleLowerCase('id');
            applyFilters();
        });
    }

    filters.forEach((filter) => {
        filter.addEventListener('click', () => {
            activeType = filter.dataset.treeFilter || 'all';
            filters.forEach((other) => other.setAttribute(
                'aria-pressed',
                other === filter ? 'true' : 'false',
            ));
            applyFilters();
        });
    });

    if (expandAll) {
        expandAll.addEventListener('click', () => {
            nodes.forEach((node) => setCollapsed(node, false));
        });
    }

    if (collapseAll) {
        collapseAll.addEventListener('click', () => {
            nodes.forEach((node) => setCollapsed(node, true));
        });
    }

    // Initial state: expand the province and branches, collapse the rest so the
    // page opens short. Mirrors the approved preview.
    nodes.forEach((node) => {
        if (COLLAPSED_DEPTHS.includes(node.dataset.depth || '')) {
            setCollapsed(node, true);
        }
    });

    applyFilters();
}
