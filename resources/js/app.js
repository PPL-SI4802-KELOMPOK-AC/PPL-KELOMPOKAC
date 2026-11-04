import './bootstrap';

if (document.querySelector('[data-dashboard]')) {
    import('./dashboard').catch(() => {
        const status = document.querySelector('[data-dashboard-status]');
        status.textContent = 'Antarmuka ringkasan gagal dimuat. Muat ulang halaman untuk mencoba kembali.';
        const retry = document.querySelector('[data-dashboard-retry]');
        retry.hidden = false;
        retry.addEventListener('click', () => location.reload());
    });
}

if (document.body.classList.contains('admin-page')) {
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
