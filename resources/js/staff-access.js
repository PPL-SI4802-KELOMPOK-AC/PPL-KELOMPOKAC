document.querySelectorAll('[data-access-submit]').forEach(form => {
    let sending = false;
    form.addEventListener('submit', event => {
        if (sending) { event.preventDefault(); return; }
        sending = true;
        form.setAttribute('aria-busy', 'true');
        form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; });
        const status = form.querySelector('[data-access-loading]');
        if (status) status.textContent = 'Memproses permintaan. Tunggu sampai hasil ditampilkan.';
    });
    window.addEventListener('pageshow', () => {
        sending = false; form.removeAttribute('aria-busy');
        form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = false; });
        const status = form.querySelector('[data-access-loading]'); if (status) status.textContent = '';
    });
});
