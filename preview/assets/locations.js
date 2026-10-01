const locations = [
    { id: 'bjm', name: 'Padepokan Banjarmasin', address: 'Jl. Veteran, Banjarmasin', schedule: 'Selasa dan Jumat, 20.00 WITA', latitude: -3.3186, longitude: 114.5944, unitName: 'Cabang Banjarmasin' },
    { id: 'bjb', name: 'Tempat Latihan Banjarbaru', address: 'Kawasan Mentaos, Banjarbaru', schedule: 'Rabu dan Sabtu, 19.30 WITA', latitude: -3.4429, longitude: 114.8322, unitName: 'Cabang Banjarbaru' },
    { id: 'martapura', name: 'Tempat Latihan Martapura', address: 'Kawasan Sekumpul, Martapura', schedule: 'Senin dan Kamis, 20.00 WITA', latitude: -3.4107, longitude: 114.8452, unitName: 'Cabang Banjar' },
    { id: 'pelaihari', name: 'Tempat Latihan Pelaihari', address: 'Kawasan Angsau, Pelaihari', schedule: 'Selasa dan Sabtu, 20.00 WITA', latitude: -3.7987, longitude: 114.7783, unitName: 'Cabang Tanah Laut' },
];

const mapElement = document.querySelector('#training-map');
const locationButtons = [...document.querySelectorAll('[data-location-id]')];

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

if (mapElement instanceof HTMLElement && window.L) {
    const map = L.map(mapElement, { scrollWheelZoom: false }).setView([-3.3, 114.6], 9);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    const markers = new Map();
    locations.forEach((location) => {
        const marker = L.marker([location.latitude, location.longitude]).addTo(map).bindPopup(createPopup(location));
        markers.set(location.id, marker);
    });

    function activateLocation(id) {
        const location = locations.find((item) => item.id === id);
        const marker = markers.get(id);
        if (!location || !marker) return;

        locationButtons.forEach((button) => button.setAttribute('aria-pressed', button.dataset.locationId === id ? 'true' : 'false'));
        map.setView([location.latitude, location.longitude], 14);
        marker.openPopup();
    }

    locationButtons.forEach((button) => button.addEventListener('click', () => activateLocation(button.dataset.locationId)));
    activateLocation(locations[0].id);
}
