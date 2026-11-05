const form = document.querySelector('[data-evidence-upload]');
const input = form.querySelector('input[type="file"]');
const preview = form.querySelector('[data-evidence-preview]');
const status = form.querySelector('[data-evidence-status]');
const submit = form.querySelector('[data-evidence-submit]');
const dropzone = form.querySelector('[data-evidence-dropzone]');
const countOutput = document.querySelector('[data-evidence-count]');
const countLabel = document.querySelector('[data-evidence-count-label]');
const completeness = document.querySelector('[data-evidence-check]');
const completenessLabel = document.querySelector('[data-evidence-check-label]');
const completenessIcon = completeness?.querySelector('.evidence-check-icon');
const existingCount = Number(form.dataset.existingCount || 0);
const maxFiles = 5;
const maxBytes = 5 * 1024 * 1024;
const acceptedTypes = new Set(['image/jpeg', 'image/png', 'image/webp']);
let objectUrls = [];

function clearPreview() {
    objectUrls.forEach(url => URL.revokeObjectURL(url));
    objectUrls = [];
    preview.replaceChildren();
}

input.addEventListener('change', () => {
    clearPreview();
    const files = [...input.files];
    const remaining = maxFiles - existingCount;
    const invalid = files.find(file => !acceptedTypes.has(file.type) || file.size > maxBytes);

    if (files.length < 1) {
        status.textContent = `${remaining} slot foto tersisa.`;
        submit.hidden = true;
        submit.disabled = true;
        countOutput.textContent = existingCount;
        countLabel.textContent = 'Foto tersimpan';
        completeness.classList.toggle('is-complete', existingCount > 0);
        completeness.classList.remove('is-selected');
        completenessLabel.textContent = existingCount > 0 ? 'Foto kondisi wilayah tersedia' : 'Pilih minimal satu foto bukti';
        completenessIcon.textContent = existingCount > 0 ? '✓' : '○';
        return;
    }
    if (files.length > remaining) {
        status.textContent = `Pilih maksimal ${remaining} foto lagi untuk draft ini.`;
        countOutput.textContent = existingCount;
        countLabel.textContent = 'Foto tersimpan';
        submit.hidden = false;
        submit.disabled = true;
        return;
    }
    if (invalid) {
        status.textContent = `${invalid.name}: pilih JPG, PNG, atau WebP berukuran maksimal 5 MB.`;
        countOutput.textContent = existingCount;
        countLabel.textContent = 'Foto tersimpan';
        submit.hidden = false;
        submit.disabled = true;
        return;
    }

    for (const file of files) {
        const card = document.createElement('figure');
        const image = document.createElement('img');
        const caption = document.createElement('figcaption');
        const name = document.createElement('strong');
        const details = document.createElement('span');
        const uploadState = document.createElement('span');
        const url = URL.createObjectURL(file);
        objectUrls.push(url);
        image.src = url;
        image.alt = `Preview ${file.name}`;
        name.textContent = file.name;
        details.textContent = `${(file.size / 1048576).toFixed(1)} MB · ${file.type.replace('image/', '').toUpperCase()} · Foto bukti`;
        uploadState.className = 'evidence-uploaded evidence-uploaded--pending';
        uploadState.textContent = 'Siap diunggah';
        caption.append(name, details, uploadState);
        card.className = 'evidence-card';
        card.append(image, caption);
        preview.append(card);
    }
    countOutput.textContent = existingCount + files.length;
    countLabel.textContent = existingCount > 0 ? 'Foto tersimpan + dipilih' : 'Foto dipilih';
    completeness.classList.toggle('is-complete', existingCount > 0);
    completeness.classList.toggle('is-selected', existingCount === 0);
    completenessLabel.textContent = existingCount > 0 ? 'Foto baru siap diunggah' : 'Foto siap diunggah';
    completenessIcon.textContent = existingCount > 0 ? '✓' : '○';
    status.textContent = `${files.length} foto siap diunggah. ${remaining - files.length} slot tersisa setelah unggah.`;
    submit.hidden = false;
    submit.disabled = false;
});

for (const eventName of ['dragenter', 'dragover']) {
    dropzone.addEventListener(eventName, event => {
        event.preventDefault();
        dropzone.classList.add('is-dragging');
    });
}
for (const eventName of ['dragleave', 'drop']) {
    dropzone.addEventListener(eventName, event => {
        event.preventDefault();
        dropzone.classList.remove('is-dragging');
        if (eventName === 'drop' && event.dataTransfer?.files.length) {
            input.files = event.dataTransfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    });
}

form.addEventListener('submit', () => {
    submit.disabled = true;
    submit.textContent = 'Mengunggah foto…';
    status.textContent = 'Foto sedang diunggah. Jangan tutup halaman ini.';
});

window.addEventListener('pagehide', clearPreview, { once: true });
form.dataset.previewReady = 'true';
