import './bootstrap';

if (document.querySelector('[data-region-map]')) {
    import('./region-map').catch(() => {
        document.querySelector('[data-map-status]').textContent = 'Peta gagal dimuat. Muat ulang halaman untuk mencoba kembali.';
        document.querySelector('[data-map-panel]').dataset.state = 'error';
        const retry = document.querySelector('[data-map-retry]');
        retry.hidden = false;
        retry.addEventListener('click', () => window.location.reload());
    });
}
