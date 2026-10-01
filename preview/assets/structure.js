const input = document.querySelector('#unit-search');
const nodes = [...document.querySelectorAll('[data-unit-name]')];
const result = document.querySelector('#search-result');

if (input instanceof HTMLInputElement && result instanceof HTMLElement) {
    const applyFilter = () => {
        const query = input.value.trim().toLocaleLowerCase('id');
        let visible = 0;

        nodes.forEach((node) => {
            const ownMatch = node.dataset.unitName?.toLocaleLowerCase('id').includes(query) ?? false;
            const descendantMatch = [...node.querySelectorAll('[data-unit-name]')].some((child) => child.dataset.unitName?.toLocaleLowerCase('id').includes(query));
            const show = !query || ownMatch || descendantMatch;
            node.hidden = !show;
            if (show && ownMatch) visible += 1;
        });

        result.textContent = query ? `${visible} unit cocok` : `${nodes.length} unit contoh ditampilkan`;
    };

    input.addEventListener('input', applyFilter);
}
