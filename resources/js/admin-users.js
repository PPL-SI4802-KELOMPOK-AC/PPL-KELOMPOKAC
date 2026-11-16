const accountForm = document.querySelector('[data-admin-user-form]');

if (accountForm) {
    const password = accountForm.elements.namedItem('password');
    const confirmation = accountForm.elements.namedItem('password_confirmation');
    const match = accountForm.querySelector('#password-match');
    const save = accountForm.querySelector('[data-admin-save]');
    const status = accountForm.querySelector('[data-admin-save-status]');
    const originalLabel = save.textContent;
    const tracked = ['name', 'email', 'role', 'is_active', 'password', 'password_confirmation'];
    const snapshot = () => JSON.stringify(tracked.map(name => accountForm.elements.namedItem(name).value));
    const baseline = snapshot();
    let saving = false;

    accountForm.querySelectorAll('[data-password-toggle]').forEach(button => {
        const field = accountForm.elements.namedItem(button.dataset.passwordToggle);
        const label = button.getAttribute('aria-label');
        button.hidden = false;
        button.addEventListener('click', () => {
            const visible = field.type === 'password';
            field.type = visible ? 'text' : 'password';
            button.textContent = visible ? 'Sembunyikan' : 'Lihat';
            button.setAttribute('aria-pressed', String(visible));
            button.setAttribute('aria-label', visible ? label.replace('Tampilkan', 'Sembunyikan') : label);
        });
    });

    const checkConfirmation = () => {
        const filled = confirmation.value.length > 0;
        const matches = confirmation.value === password.value;
        confirmation.setCustomValidity(filled && !matches ? 'Konfirmasi kata sandi belum sesuai.' : '');
        confirmation.required = password.required || password.value.length > 0;
        match.textContent = filled ? (matches ? 'Kata sandi sudah sesuai.' : 'Konfirmasi kata sandi belum sesuai.') : '';
        match.classList.toggle('is-match', filled && matches);
        match.classList.toggle('is-mismatch', filled && !matches);
    };
    password.addEventListener('input', checkConfirmation);
    confirmation.addEventListener('input', checkConfirmation);
    checkConfirmation();

    const updateRoleGuide = () => {
        const role = accountForm.elements.namedItem('role').value;
        document.querySelectorAll('[data-role-guide]').forEach(item => {
            item.classList.toggle('is-current', item.dataset.roleGuide === role);
        });
    };
    accountForm.elements.namedItem('role').addEventListener('change', updateRoleGuide);
    updateRoleGuide();

    accountForm.addEventListener('submit', event => {
        if (saving) {
            event.preventDefault();
            return;
        }
        saving = true;
        save.disabled = true;
        save.textContent = 'Menyimpan…';
        accountForm.setAttribute('aria-busy', 'true');
        status.textContent = 'Menyimpan data pengguna.';
    });
    window.addEventListener('pageshow', () => {
        saving = false;
        save.disabled = false;
        save.textContent = originalLabel;
        accountForm.removeAttribute('aria-busy');
        status.textContent = '';
    });
    window.addEventListener('beforeunload', event => {
        if (!saving && snapshot() !== baseline) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    accountForm.querySelector('[aria-invalid="true"]')?.focus();
}
