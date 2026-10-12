import './firebase-auth';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import markerIcon2xUrl from 'leaflet/dist/images/marker-icon-2x.png';
import markerIconUrl from 'leaflet/dist/images/marker-icon.png';
import markerShadowUrl from 'leaflet/dist/images/marker-shadow.png';
import { initGalleryCarousels } from './gallery-carousel';
import { initGalleryLightbox } from './public-gallery-lightbox';

// Leaflet resolves its default marker images relative to the stylesheet at
// runtime, which breaks under Vite's asset URLs: `L.Icon.Default` always
// prepends `imagePath` to the configured icon value, so an already-absolute
// import URL becomes `images/http://...` and 404s. Import the images so Vite
// fingerprints them, then bypass Leaflet's relative resolution entirely by
// overriding `_getIconUrl` to return the resolved URLs verbatim.
L.Icon.Default.prototype._getIconUrl = function (name) {
    const urls = {
        icon: markerIconUrl,
        icon2x: markerIcon2xUrl,
        shadow: markerShadowUrl,
    };

    return urls[name];
};
L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2xUrl,
    iconUrl: markerIconUrl,
    shadowUrl: markerShadowUrl,
});

const mapElement = document.querySelector('#training-map');
const locationDataElement = document.querySelector('#training-location-data');

if (mapElement instanceof HTMLElement && locationDataElement instanceof HTMLScriptElement) {
    const locations = JSON.parse(locationDataElement.textContent || '[]');

    if (locations.length > 0) {
        const map = L.map(mapElement, { scrollWheelZoom: false, zoomControl: true }).setView([-3.0, 115.0], 7);
        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);
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

const heroVideo = document.querySelector('[data-hero-video]');

if (heroVideo instanceof HTMLVideoElement) {
    const motionPreference = window.matchMedia('(prefers-reduced-motion: no-preference)');

    const syncHeroVideoPlayback = (matches) => {
        if (!matches) {
            heroVideo.pause();

            return;
        }

        heroVideo.play().catch(() => {});
    };

    syncHeroVideoPlayback(motionPreference.matches);

    motionPreference.addEventListener('change', (event) => {
        syncHeroVideoPlayback(event.matches);
    });
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

// Initialize carousel after DOM content loaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initGalleryCarousels());
} else {
    initGalleryCarousels();
}

// Single-marker training-location detail map. The controller has already
// validated the published + verified record before this data is emitted, so
// no coordinates exist for invalid/unpublished slugs.
const locationDetailElement = document.querySelector('#training-map-detail');
const locationDetailDataElement = document.querySelector('#training-location-detail-data');

if (locationDetailElement instanceof HTMLElement && locationDetailDataElement instanceof HTMLScriptElement) {
    const location = JSON.parse(locationDetailDataElement.textContent || 'null');

    if (location && location.latitude && location.longitude) {
        const point = [Number(location.latitude), Number(location.longitude)];
        const map = L.map(locationDetailElement, { scrollWheelZoom: false, zoomControl: true }).setView(point, 14);

        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        }).addTo(map);

        const popup = document.createElement('div');
        const heading = document.createElement('strong');
        const address = document.createElement('p');
        heading.textContent = location.name;
        address.textContent = location.address;
        popup.append(heading, address);

        L.marker(point).addTo(map).bindPopup(popup).openPopup();
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initGalleryLightbox());
} else {
    initGalleryLightbox();
}