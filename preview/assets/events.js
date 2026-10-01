const sampleEvents = {
    'latihan-gabungan-provinsi': {
        title: 'Latihan Gabungan Tingkat Provinsi',
        date: 'Minggu, 15 November 2026, 08.00 WITA',
        summaryDate: '15 November 2026',
        time: '08.00 WITA',
        venue: 'Padepokan PSHT Banjarmasin',
        location: 'Banjarmasin',
        description: 'Pengurus Provinsi Kalimantan Selatan mengadakan latihan bersama untuk memperkuat pembinaan materi dasar dan koordinasi antarcabang.',
        dateTime: '2026-11-15T08:00:00+08:00',
    },
    'ujian-siswa-cabang-banjarmasin': {
        title: 'Ujian Siswa Cabang Banjarmasin',
        date: 'Minggu, 6 Desember 2026, 07.30 WITA',
        summaryDate: '6 Desember 2026',
        time: '07.30 WITA',
        venue: 'Padepokan PSHT Banjarmasin',
        location: 'Banjarmasin',
        description: 'Ujian siswa cabang menjadi ruang evaluasi materi latihan dan perkembangan kemampuan peserta.',
        dateTime: '2026-12-06T07:30:00+08:00',
    },
    'pelatihan-pelatih-ranting': {
        title: 'Pelatihan Pelatih Ranting',
        date: 'Minggu, 24 Mei 2026, 08.00 WITA',
        summaryDate: '24 Mei 2026',
        time: '08.00 WITA',
        venue: 'Sekretariat Cabang Banjarmasin',
        location: 'Banjarmasin',
        description: 'Pelatihan contoh untuk meningkatkan kesiapan pelatih ranting dalam membina kegiatan latihan.',
        dateTime: '2026-05-24T08:00:00+08:00',
    },
};

const eventFilters = [...document.querySelectorAll('[data-event-filter]')];
const eventItems = [...document.querySelectorAll('[data-event-status]')];
const eventCount = document.querySelector('[data-event-count]');

function applyEventFilter(filter) {
    let visible = 0;

    eventItems.forEach((item) => {
        const show = filter === 'all' || item.dataset.eventWhen === filter;
        item.hidden = !show;
        if (show) visible += 1;
    });

    if (eventCount) eventCount.textContent = `${visible} kegiatan ditampilkan`;
}

eventFilters.forEach((button) => {
    button.addEventListener('click', () => {
        eventFilters.forEach((item) => item.setAttribute('aria-pressed', item === button ? 'true' : 'false'));
        applyEventFilter(button.dataset.eventFilter);
    });
});

const activeFilter = eventFilters.find((button) => button.getAttribute('aria-pressed') === 'true');
if (activeFilter) applyEventFilter(activeFilter.dataset.eventFilter);

const requestedSlug = new URLSearchParams(window.location.search).get('slug');
const selectedEvent = Object.hasOwn(sampleEvents, requestedSlug) ? sampleEvents[requestedSlug] : sampleEvents['latihan-gabungan-provinsi'];
const title = document.querySelector('[data-event-title]');
const date = document.querySelector('[data-event-date]');
const venue = document.querySelector('[data-event-venue]');
const description = document.querySelector('[data-event-description]');
const summaryDate = document.querySelector('[data-event-summary-date]');
const summaryTime = document.querySelector('[data-event-summary-time]');
const summaryLocation = document.querySelector('[data-event-summary-location]');

if (title) title.textContent = selectedEvent.title;
if (date) {
    date.textContent = selectedEvent.date;
    date.setAttribute('datetime', selectedEvent.dateTime);
}
if (venue) venue.textContent = selectedEvent.venue;
if (description) description.textContent = selectedEvent.description;
if (summaryDate) summaryDate.textContent = selectedEvent.summaryDate;
if (summaryTime) summaryTime.textContent = selectedEvent.time;
if (summaryLocation) summaryLocation.textContent = selectedEvent.location;

const copyButton = document.querySelector('[data-copy-event]');
const copyStatus = document.querySelector('[data-copy-status]');

copyButton?.addEventListener('click', async () => {
    const link = window.location.href;
    try {
        await navigator.clipboard.writeText(link);
        if (copyStatus) copyStatus.textContent = 'Tautan berhasil disalin.';
    } catch {
        if (copyStatus) copyStatus.textContent = `Salin tautan dari bilah alamat: ${link}`;
    }
});
