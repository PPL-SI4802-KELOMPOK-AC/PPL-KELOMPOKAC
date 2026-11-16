import './bootstrap';
import './admin-users';

const submissionForm = document.querySelector('[data-submission-form]');
if (submissionForm) {
    const checkbox = submissionForm.elements.namedItem('pernyataan');
    const button = submissionForm.querySelector('[data-submission-button]');
    const status = submissionForm.querySelector('[data-submission-status]');
    let sending = false;
    const updateButton = () => { button.disabled = sending || !checkbox.checked; };
    checkbox.addEventListener('change', updateButton);
    submissionForm.addEventListener('submit', event => {
        if (sending || !checkbox.checked) { event.preventDefault(); return; }
        sending = true;
        updateButton();
        status.textContent = 'Pengajuan sedang dikirim. Tunggu sampai nomor pengajuan ditampilkan.';
    });
    window.addEventListener('pageshow', () => { sending = false; updateButton(); });
    updateButton();
}

if (document.querySelector('[data-location-form]')) {
    const form = document.querySelector('[data-location-form]');
    const next = form.querySelector('[data-location-next]');
    const help = form.querySelector('[data-location-next-help]');
    const requiredFields = ['provinsi', 'kabupaten', 'kecamatan', 'desa', 'alamat'];
    const updateNext = () => {
        const complete = requiredFields.every(name => {
            const field = form.elements.namedItem(name);
            return !field.disabled && field.value.trim() !== '' && field.validity.valid;
        });
        if (next.disabled === complete) next.disabled = !complete;
        help.textContent = complete
            ? 'Data lokasi lengkap. Anda dapat melanjutkan ke bukti foto.'
            : 'Lengkapi kabupaten/kota, kecamatan, desa/kelurahan, dan alamat untuk melanjutkan.';
    };
    form.addEventListener('input', updateNext);
    form.addEventListener('change', updateNext);
    const observer = new MutationObserver(updateNext);
    observer.observe(form, { subtree: true, attributes: true, attributeFilter: ['disabled'] });
    updateNext();
    window.addEventListener('pagehide', () => observer.disconnect(), { once: true });
    import('./location-form').catch(() => {
        document.querySelector('[data-location-map-status]').textContent = 'Peta belum dapat dimuat. Muat ulang halaman atau isi koordinat secara manual.';
    });
}

if (document.querySelector('[data-evidence-upload]')) {
    import('./evidence-upload').catch(() => {
        document.querySelector('[data-evidence-status]').textContent = 'Preview foto gagal dimuat. Muat ulang halaman untuk mencoba kembali.';
    });
}

if (document.querySelector('[data-dashboard]')) {
    import('./dashboard').catch(() => {
        const status = document.querySelector('[data-dashboard-status]');
        status.textContent = 'Antarmuka ringkasan gagal dimuat. Muat ulang halaman untuk mencoba kembali.';
        const retry = document.querySelector('[data-dashboard-retry]');
        retry.hidden = false;
        retry.addEventListener('click', () => location.reload());
    });
}

if (document.querySelector('#sidebar, [data-region-manager]')) {
    import('./region-manage').catch(() => {
        const status = document.querySelector('[data-list-status]');
        if (status) status.textContent = 'Antarmuka gagal dimuat. Muat ulang halaman untuk mencoba kembali.';
    });
}

if (document.querySelector('[data-region-map]')) {
    import('./region-map').catch(() => {
        document.querySelector('[data-map-status]').textContent = 'Peta gagal dimuat. Muat ulang halaman untuk mencoba kembali.';
        document.querySelector('[data-map-panel]').dataset.state = 'error';
        const retry = document.querySelector('[data-map-retry]');
        retry.hidden = false;
        retry.addEventListener('click', () => window.location.reload());
    });
}
