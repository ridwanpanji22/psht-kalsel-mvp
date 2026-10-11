// Preview parity for the production organization tree: the same name search,
// type filter, and collapse controls, driven by the sample units in
// preview/struktur.html. Production uses resources/js/organization-tree.js; the
// interaction contract (data-tree-node / data-tree-toggle / data-tree-children)
// is identical so the preview genuinely mirrors the live page.
const input = document.querySelector('#unit-search');
const result = document.querySelector('#search-result');
const tree = document.querySelector('#organization-tree');
const nodes = tree ? [...tree.querySelectorAll('[data-tree-node]')] : [];
const filters = [...document.querySelectorAll('[data-tree-filter]')];
const expandAll = document.querySelector('[data-tree-expand-all]');
const collapseAll = document.querySelector('[data-tree-collapse-all]');

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

const selfMatches = (node) => {
    const name = (node.dataset.unitName || '').toLocaleLowerCase('id');
    const type = node.dataset.unitType || '';

    return (query === '' || name.includes(query))
        && (activeType === 'all' || type === activeType);
};

const applyFilters = () => {
    const narrowing = query !== '' || activeType !== 'all';
    let visible = 0;

    nodes.forEach((node) => {
        const selfMatch = narrowing ? selfMatches(node) : true;
        const descendantMatch = narrowing
            && [...node.querySelectorAll('[data-tree-node]')].some((child) => selfMatches(child));
        const show = selfMatch || descendantMatch;

        node.hidden = !show;

        if (show) {
            visible += 1;

            if (narrowing) {
                let parent = node.parentElement?.closest('[data-tree-node]');

                while (parent) {
                    setCollapsed(parent, false);
                    parent = parent.parentElement?.closest('[data-tree-node]');
                }
            }
        }
    });

    if (result instanceof HTMLElement) {
        result.textContent = narrowing
            ? `${visible} dari ${nodes.length} unit cocok`
            : `${nodes.length} unit contoh ditampilkan`;
    }
};

if (input instanceof HTMLInputElement && result instanceof HTMLElement) {
    input.addEventListener('input', () => {
        query = input.value.trim().toLocaleLowerCase('id');
        applyFilters();
    });
}

nodes.forEach((node) => {
    const toggle = node.querySelector(':scope > [data-tree-toggle]');

    if (!toggle) {
        return;
    }

    const flip = () => setCollapsed(node, node.dataset.collapsed !== 'true');

    toggle.addEventListener('click', flip);
    toggle.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            flip();
        }
    });
});

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
