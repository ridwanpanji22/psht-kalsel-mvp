const eventMapElement = document.querySelector('#event-map-preview');

if (eventMapElement instanceof HTMLElement && window.L) {
    const position = [-3.3186, 114.5944];
    const map = L.map(eventMapElement, { scrollWheelZoom: false }).setView(position, 13);

    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(map);

    const popup = document.createElement('div');
    const title = document.createElement('strong');
    const note = document.createElement('p');
    title.textContent = 'Lokasi kegiatan contoh';
    note.textContent = 'Padepokan PSHT Banjarmasin';
    popup.append(title, note);

    L.marker(position).addTo(map).bindPopup(popup).openPopup();
}
