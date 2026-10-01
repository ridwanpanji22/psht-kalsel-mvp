const locations = [
    { id: 'bjm', name: 'Padepokan Banjarmasin', address: 'Jl. Veteran, Banjarmasin', schedule: 'Selasa dan Jumat, 20.00 WITA', latitude: -3.3186, longitude: 114.5944, unitName: 'Cabang Banjarmasin' },
    { id: 'bjb', name: 'Tempat Latihan Banjarbaru', address: 'Kawasan Mentaos, Banjarbaru', schedule: 'Rabu dan Sabtu, 19.30 WITA', latitude: -3.4429, longitude: 114.8322, unitName: 'Cabang Banjarbaru' },
    { id: 'martapura', name: 'Tempat Latihan Martapura', address: 'Kawasan Sekumpul, Martapura', schedule: 'Senin dan Kamis, 20.00 WITA', latitude: -3.4107, longitude: 114.8452, unitName: 'Cabang Banjar' },
    { id: 'pelaihari', name: 'Tempat Latihan Pelaihari', address: 'Kawasan Angsau, Pelaihari', schedule: 'Selasa dan Sabtu, 20.00 WITA', latitude: -3.7987, longitude: 114.7783, unitName: 'Cabang Tanah Laut' },
];

const listElement = document.querySelector('[data-training-location-list]');

if (listElement instanceof HTMLElement) {
    locations.forEach((location) => {
        const item = document.createElement('li');
        const link = document.createElement('a');
        const unit = document.createElement('span');
        const name = document.createElement('span');
        const address = document.createElement('span');
        const schedule = document.createElement('span');

        link.href = `tempat-latihan-detail.html?id=${encodeURIComponent(location.id)}`;
        link.className = 'preview-card focus-ring block h-full w-full p-5 text-left hover:border-[#2162ed]';
        unit.className = 'text-xs font-bold uppercase tracking-[0.14em] text-amber-300';
        name.className = 'mt-2 block text-lg font-semibold text-white';
        address.className = 'mt-2 block text-sm text-stone-400';
        schedule.className = 'mt-3 block text-sm text-stone-300';

        unit.textContent = location.unitName;
        name.textContent = location.name;
        address.textContent = location.address;
        schedule.textContent = location.schedule;

        link.append(unit, name, address, schedule);
        item.append(link);
        listElement.append(item);
    });
}

const detailElement = document.querySelector('[data-location-detail]');
const notFoundElement = document.querySelector('[data-location-not-found]');
const detailMapElement = document.querySelector('#training-map-detail');
const detailNameElement = document.querySelector('[data-location-name]');
const detailKickerElement = document.querySelector('[data-location-kicker]');

function createPopup(location) {
    const wrapper = document.createElement('div');
    const heading = document.createElement('strong');
    const address = document.createElement('p');
    const schedule = document.createElement('p');

    heading.textContent = location.name;
    address.textContent = location.address;
    schedule.textContent = location.schedule;
    wrapper.append(heading, address, schedule);

    return wrapper;
}

function setDetailName(text) {
    if (detailNameElement instanceof HTMLElement) detailNameElement.textContent = text;
}

function setDetailKicker(text) {
    if (detailKickerElement instanceof HTMLElement) detailKickerElement.textContent = text;
}

function showNotFound() {
    setDetailName('Tempat latihan tidak ditemukan');
    setDetailKicker('Lokasi tidak tersedia');
    if (detailElement instanceof HTMLElement) detailElement.hidden = true;
    if (notFoundElement instanceof HTMLElement) notFoundElement.hidden = false;
}

function renderDetail(location) {
    if (detailElement instanceof HTMLElement) detailElement.hidden = false;
    if (notFoundElement instanceof HTMLElement) notFoundElement.hidden = true;

    setDetailName(location.name);
    setDetailKicker('Lokasi latihan');

    const fields = {
        '[data-location-unit]': location.unitName,
        '[data-location-address]': location.address,
        '[data-location-schedule]': location.schedule,
    };

    Object.entries(fields).forEach(([selector, value]) => {
        const target = document.querySelector(selector);
        if (target instanceof HTMLElement) target.textContent = value;
    });

    if (detailMapElement instanceof HTMLElement && window.L) {
        const leaflet = window.L;
        const map = leaflet.map(detailMapElement, { scrollWheelZoom: false }).setView([location.latitude, location.longitude], 14);

        leaflet.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        leaflet.marker([location.latitude, location.longitude]).addTo(map).bindPopup(createPopup(location)).openPopup();
    }
}

if (detailElement instanceof HTMLElement || detailMapElement instanceof HTMLElement) {
    const selectedId = new URLSearchParams(window.location.search).get('id');
    const location = locations.find((item) => item.id === selectedId);

    if (location) {
        renderDetail(location);
    } else {
        showNotFound();
    }
}
