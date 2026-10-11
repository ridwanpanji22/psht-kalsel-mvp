// Progressive enhancement for the server-rendered organization tree.
//
// The Blade view renders every active unit so the page works without
// JavaScript and crawlers see the full hierarchy. This module only adds
// collapse/expand, name search, and type filtering on top. Because the markup
// is the source of truth, a failed init leaves a fully readable (if long) page
// rather than an empty one.

// On first paint only the province and its branches stay expanded, so the page
// opens at roughly two screens instead of the full ~19,000px hierarchy. A node
// collapsed at depth >= 1 hides its own children, which is what keeps every
// ranting and rayon below the branch level folded away until the reader asks
// for them.
const INITIAL_COLLAPSED_MIN_DEPTH = 1;

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

    // True when the node itself passes the active query and type filter.
    const selfMatches = (node) => {
        const name = (node.dataset.unitName || '').toLocaleLowerCase('id');
        const type = node.dataset.unitType || '';

        return (query === '' || name.includes(query))
            && (activeType === 'all' || type === activeType);
    };

    const applyFilters = () => {
        const narrowing = query !== '' || activeType !== 'all';
        let visibleCount = 0;

        nodes.forEach((node) => {
            const selfMatch = narrowing ? selfMatches(node) : true;
            // A non-matching ancestor still has to stay visible when a
            // descendant matches, otherwise the matching unit is orphaned
            // inside a hidden subtree.
            const descendantMatch = narrowing
                && [...node.querySelectorAll('[data-tree-node]')].some((child) => selfMatches(child));
            const show = selfMatch || descendantMatch;

            node.hidden = !show;

            if (show) {
                visibleCount += 1;

                // Auto-expand ancestors so a matching unit below the branch
                // level is actually reachable.
                if (narrowing) {
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

            if (narrowing) {
                result.textContent = `${visibleCount} dari ${total} unit cocok.`;
            } else {
                result.textContent = `${total} unit ditampilkan.`;
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

    // Initial state: the province and its branches stay expanded, everything
    // below them opens collapsed so the page opens at roughly two screens.
    nodes.forEach((node) => {
        if (Number(node.dataset.depth || '0') >= INITIAL_COLLAPSED_MIN_DEPTH) {
            setCollapsed(node, true);
        }
    });

    applyFilters();
}
