import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const mapElement = document.querySelector('#training-map');
const locationDataElement = document.querySelector('#training-location-data');

if (mapElement instanceof HTMLElement && locationDataElement instanceof HTMLScriptElement) {
    const locations = JSON.parse(locationDataElement.textContent || '[]');

    if (locations.length > 0) {
        const map = L.map(mapElement, { scrollWheelZoom: false, zoomControl: true }).setView([-3.0, 115.0], 7);
        const markers = L.featureGroup();

        for (const location of locations) {
            const marker = L.marker([Number(location.latitude), Number(location.longitude)]).addTo(markers);
            marker.bindPopup(`<strong>${escapeHtml(location.name)}</strong><br>${escapeHtml(location.address)}`);
        }

        markers.addTo(map);
        if (markers.getLayers().length > 1) {
            map.fitBounds(markers.getBounds().pad(0.15));
        } else {
            map.setView(markers.getBounds().getCenter(), 14);
        }
    }
}

function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, (character) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;',
    })[character]);
}
