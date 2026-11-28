const palette = { unserved: '#ec9d06', needs_data: '#db4347', outside: '#b8c4ce', missing_master: '#e2e8f0' };
const statusLabels = {
    unserved: 'Ada kebutuhan akses listrik',
    needs_data: 'Perlu pendataan',
    outside: 'Tidak cocok dengan filter aktif',
    missing_master: 'Master wilayah belum tersedia',
};
const categoryPriority = { missing_master: 0, outside: 1, needs_data: 2, unserved: 3 };
const districtCode = code => code.slice(0, 5).replace('.', '');
const subdistrictCode = code => code.slice(0, 8);

export function dashboardAreas({ map, L, root, mapElement, json, node, signal, home, format, viewportRevision }) {
    const find = selector => root.querySelector(selector);
    const search = find('#gov-area-search');
    const options = find('[data-gov-area-options]');
    const back = find('[data-gov-area-back]');
    const heading = find('[data-gov-area-level]');
    const detail = find('[data-gov-area-detail]');
    const status = find('[data-gov-area-status]');
    const assets = new Map();
    const submissionLayer = L.layerGroup().addTo(map);
    const submissionMarkers = new Map();
    let navigation = {}, categories = {}, villageCategories = {}, filters = new URLSearchParams();
    let known = new Map(), layer, renderedNavigation, detailRequest, revision = 0, initialized = false;
    let loading = false, navigating = false;
    let areasWithSubmissions = new Set();
    let selectedStatus, statusList, statusRequest;

    function clearStatus() {
        statusRequest?.abort();
        selectedStatus = statusList = null;
    }

    function geography(file) {
        if (!assets.has(file)) {
            assets.set(file, json(`${mapElement.dataset.areasUrl}/${file}`, signal).catch(error => {
                assets.delete(file);
                throw error;
            }));
        }
        return assets.get(file);
    }

    function category(code) {
        return categories[code] ?? 'missing_master';
    }

    function indexCategories() {
        categories = {};
        for (const [village, value] of Object.entries(villageCategories)) {
            if (!known.has(village)) continue;
            categories[village] = value;
            for (const code of [districtCode(village), subdistrictCode(village)]) {
                if (categoryPriority[value] > categoryPriority[category(code)]) categories[code] = value;
            }
        }
    }

    function resetDetail() {
        detailRequest?.abort();
        clearStatus();
        updateSubmissionMarkers([]);
        detail.replaceChildren(node('h3', 'Detail wilayah'), node('p', 'Pilih desa atau kelurahan untuk melihat kondisi, pengajuan, dan progres.'));
    }

    function panelButton(label, action, className = 'button') {
        const button = node('button', label, className);
        button.type = 'button';
        button.addEventListener('click', action);
        return button;
    }

    async function loadStatus(url, background = false) {
        if (!selectedStatus) return;
        statusRequest?.abort();
        const current = new AbortController();
        statusRequest = current;
        const { id, label, page } = selectedStatus;
        const target = statusList;
        if (!background) target.replaceChildren(node('h5', label), node('p', 'Memuat pengajuan…'));
        const listingUrl = new URL(url);
        listingUrl.searchParams.set('status_pengajuan', id);
        listingUrl.searchParams.set('halaman', page);
        try {
            const { submission_list: data } = await json(listingUrl, current.signal);
            if (current.signal.aborted) return;
            if (data && page > data.last_page) {
                selectedStatus.page = data.last_page;
                return loadStatus(url, background);
            }
            const cards = node('ul', null, 'gov-submission-records');
            for (const item of data?.items ?? []) {
                const card = node('li');
                card.dataset.status = id;
                const date = item.submitted_at?.slice(0, 10).split('-').reverse().join('/') || 'Belum tersedia';
                card.append(node('strong', `ID pengajuan: ${item.id}`),
                    node('p', item.location ?? 'Lokasi: Belum tersedia'),
                    node('p', `Tanggal pengajuan: ${date}`), node('span', item.status, 'gov-area-status-label'));
                cards.append(card);
            }
            target.replaceChildren(node('h5', label), cards.childElementCount ? cards : node('p', 'Tidak ada pengajuan untuk status ini.'));
            if (data?.last_page > 1) {
                const controls = node('div', null, 'gov-area-list-pages');
                for (const [text, next] of [['Sebelumnya', page - 1], ['Berikutnya', page + 1]]) {
                    const button = panelButton(text, () => {
                        selectedStatus.page = next;
                        loadStatus(url);
                    });
                    button.disabled = next < 1 || next > data.last_page;
                    controls.append(button);
                }
                target.append(node('p', `Halaman ${page} dari ${data.last_page}`), controls);
            }
        } catch (error) {
            if (!current.signal.aborted) {
                target.replaceChildren(node('h5', label), node('p', error.message), panelButton('Coba lagi daftar', () => loadStatus(url)));
            }
        }
    }

    function submissionSummary(data, period, url) {
        const section = node('section', null, 'gov-area-card');
        section.append(node('h4', 'Pengajuan terkait'), node('p', `${period} · ${format(data.linked_submissions)} pengajuan terhubung`, 'gov-area-total'));
        const checkpoints = node('div', null, 'gov-area-checkpoints');
        for (const item of data.submissions) {
            const button = panelButton(`${item.status}: ${format(item.count)}`, () => {
                selectedStatus = { id: item.id, label: item.status, page: 1 };
                statusList = node('div', null, 'gov-area-status-list');
                showList(true);
                loadStatus(url);
            }, 'gov-area-checkpoint');
            button.dataset.status = item.id;
            checkpoints.append(button);
        }
        if (!checkpoints.childElementCount) checkpoints.append(node('p', 'Belum ada pengajuan dalam filter ini.'));
        const listView = node('div');
        const back = panelButton('Kembali ke ringkasan status', () => {
            const id = selectedStatus.id;
            clearStatus();
            showList(false);
            [...checkpoints.children].find(button => button.dataset.status === id)?.focus();
        });
        function showList(open) {
            checkpoints.hidden = open;
            listView.hidden = !open;
            listView.replaceChildren(...(open ? [back, statusList] : []));
        }
        section.append(checkpoints, listView);
        showList(Boolean(selectedStatus));
        return section;
    }

    function submissionPopup(points, region, openId) {
        const popup = node('div');
        popup.append(node('strong', 'PENGAJUAN PLTS', 'gov-submission-popup-title'),
            node('p', `${region.label} · ${region.context}`, 'gov-submission-popup-location'));
        for (const point of points) {
            const date = point.submitted_at?.slice(0, 10).split('-').reverse().join('/') || 'Belum tersedia';
            const facts = [node('p', `Tanggal pengajuan: ${date}`), node('p', `Status: ${point.status}`)];
            if (points.length === 1) {
                popup.append(node('p', `ID pengajuan: ${point.id}`), ...facts);
            } else {
                const item = node('details');
                item.dataset.submissionId = point.id;
                item.open = point.id === openId;
                item.append(node('summary', `ID pengajuan: ${point.id}`), ...facts);
                popup.append(item);
            }
        }
        return popup;
    }

    function updateSubmissionMarkers(points, region) {
        const groups = new Map();
        for (const point of points) {
            const key = `${point.latitude},${point.longitude}`;
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(point);
        }
        for (const [key, entry] of submissionMarkers) {
            if (groups.has(key)) continue;
            submissionLayer.removeLayer(entry.marker);
            submissionMarkers.delete(key);
        }
        for (const [key, entries] of groups) {
            let saved = submissionMarkers.get(key);
            const signature = JSON.stringify(entries);
            if (saved?.signature === signature) continue;
            if (!saved || saved.count !== entries.length) {
                const count = entries.length;
                const label = `${count} pengajuan PLTS`;
                const icon = L.divIcon({
                    className: 'gov-submission-marker',
                    html: node('span', count > 1 ? String(count) : ''),
                    iconSize: [26, 28], iconAnchor: [13, 27], popupAnchor: [0, -21],
                });
                if (saved) {
                    saved.marker.setIcon(icon);
                    saved.marker.getElement()?.setAttribute('title', label);
                    saved.marker.getElement()?.setAttribute('aria-label', label);
                    saved.count = count;
                } else {
                    const [{ latitude, longitude }] = entries;
                    const marker = L.marker([latitude, longitude], {
                        icon, title: label, alt: label, bubblingMouseEvents: false, autoPanOnFocus: false,
                    }).addTo(submissionLayer);
                    marker.getElement()?.setAttribute('aria-label', label);
                    saved = { marker, count };
                    submissionMarkers.set(key, saved);
                }
            }
            const popup = saved.marker.getPopup();
            const openId = popup?.getContent().querySelector('details[open]')?.dataset.submissionId;
            const content = submissionPopup(entries, region, openId);
            if (popup) saved.marker.setPopupContent(content);
            else saved.marker.bindPopup(content, { autoPan: false, className: 'gov-submission-popup', minWidth: 120, maxWidth: 190 });
            saved.signature = signature;
        }
    }

    async function showDetail(code, background = false) {
        const region = known.get(code);
        if (!region) return;
        detailRequest?.abort();
        const current = new AbortController();
        detailRequest = current;
        const title = () => {
            const header = node('header', null, 'gov-area-header');
            header.append(node('h3', region.label), node('p', region.context));
            return [header];
        };
        if (!background) detail.replaceChildren(...title(), node('p', 'Memuat data wilayah…'));
        const url = new URL(mapElement.dataset.detailUrl, location.origin);
        url.search = filters.toString();
        url.searchParams.set('wilayah', code);
        try {
            const { detail: data, status: areaStatus } = await json(url, current.signal);
            if (current.signal.aborted) return;
            const content = document.createDocumentFragment();
            content.append(...title());
            if (!data) {
                clearStatus();
                updateSubmissionMarkers([]);
                content.append(node('p', statusLabels[areaStatus]), node('p', 'Data wilayah dan aktivitas: Belum tersedia.'));
                detail.replaceChildren(content);
                return;
            }
            const facts = node('dl');
            for (const [label, value] of [
                ['Kondisi listrik', data.condition || 'Belum tersedia'],
                ['KK terdampak', format(data.affected_kk)],
            ]) facts.append(node('dt', label), node('dd', value));
            const period = filters.has('year') ? `Tahun ${filters.get('year')}` : 'Semua periode';
            const recordedFields = Number(Boolean(data.condition)) + Number(data.affected_kk != null);
            const master = node('section', null, 'gov-area-card');
            master.append(node('h4', 'Data master terkini'), facts,
                node('p', `Kelengkapan kondisi listrik dan KK terdampak · ${recordedFields} dari 2 kolom terisi`, 'gov-area-secondary'));
            const progressCard = node('section', null, 'gov-area-card');
            progressCard.append(node('h4', 'Progres elektrifikasi'), node('p', period, 'gov-area-secondary'));
            const progress = data.progress.map(item => `${item.tanggal_progres || 'Tanggal belum tersedia'} · ${item.tahap_progres || 'Tahap belum tersedia'}`);
            if (progress.length) progressCard.append(node('p', 'Maksimal 10 catatan terbaru.'), list(progress));
            else progressCard.append(node('p', 'Belum ada catatan progres dalam filter ini.'));
            content.append(master, submissionSummary(data, period, url), progressCard);
            updateSubmissionMarkers(data.submission_points ?? [], region);
            detail.replaceChildren(content);
            if (selectedStatus) await loadStatus(url, true);
        } catch (error) {
            if (current.signal.aborted) return;
            const retry = node('button', 'Coba lagi detail', 'button');
            retry.type = 'button';
            retry.addEventListener('click', () => showDetail(code), { once: true });
            detail.replaceChildren(...title(), node('p', error.message), retry);
        }
    }

    function list(entries) {
        const element = node('ul');
        element.append(...entries.map(entry => node('li', entry)));
        return element;
    }

    function tooltip(code) {
        return node('span', `${known.get(code)?.label || code} · ${statusLabels[category(code)]}`);
    }

    function updateOptions() {
        const query = search.value.trim().toLocaleLowerCase('id');
        const records = [...known.values()].filter(region => query
            ? `${region.label} ${region.context} ${region.code}`.toLocaleLowerCase('id').includes(query)
            : region.parent === (navigation.subdistrict || navigation.district || ''));
        options.replaceChildren(new Option(records.length ? 'Pilih wilayah' : 'Wilayah tidak ditemukan', ''),
            ...records.slice(0, query ? 40 : undefined).map(region => new Option(
                query && region.context ? `${region.label} · ${region.context}` : region.label, region.code,
            )));
    }

    async function loadCatalog() {
        if (known.size) return;
        const [districts, index] = await Promise.all([geography('districts.geojson'), geography('index.json')]);
        for (const feature of districts.features) {
            const { code, name: label } = feature.properties;
            known.set(code, { code, label: /^kota\s/i.test(label) ? label : `Kabupaten ${label}`, context: '', parent: '' });
        }
        for (const { code, name: label, subdistrict } of Object.values(index.villages)) {
            const district = districtCode(code), sub = subdistrictCode(code);
            const context = known.get(district)?.label || '';
            known.set(sub, { code: sub, label: subdistrict, context, parent: district });
            known.set(code, { code, label, context: `${subdistrict} · ${context}`, parent: sub });
        }
        known = new Map([...known].sort(([, a], [, b]) => a.label.localeCompare(b.label, 'id')));
        indexCategories();
    }

    function showArea(code) {
        const district = code.length === 4 ? code : districtCode(code);
        const subdistrict = code.length >= 8 ? subdistrictCode(code) : undefined;
        open({ district, subdistrict, village: code.length > 8 ? code : undefined }, true);
    }

    async function navigate(next, fit, zoomRevision, centerOnly = false, focusSavedArea = false) {
        if (loading) return;
        const startViewport = viewportRevision();
        navigation = next;
        const sequence = ++revision;
        navigating = true;
        back.disabled = !next.district;
        resetDetail();
        status.textContent = 'Memuat batas wilayah…';
        try {
            await loadCatalog();
            const source = await geography(next.district ? `villages-${next.district}.geojson` : 'districts.geojson');
            const districtFeature = focusSavedArea && next.district && !next.subdistrict
                ? (await geography('districts.geojson')).features.find(feature => feature.properties.code === next.district)
                : null;
            if (sequence !== revision || signal.aborted) return;
            let features = source.features;
            if (next.subdistrict) {
                features = features.filter(feature => subdistrictCode(feature.properties.code) === next.subdistrict);
            } else if (next.district) {
                const groups = new Map();
                for (const feature of features) {
                    const code = subdistrictCode(feature.properties.code);
                    if (!groups.has(code)) groups.set(code, { type: 'Feature', properties: { code },
                        geometry: { type: 'GeometryCollection', geometries: [] } });
                    groups.get(code).geometry.geometries.push(feature.geometry);
                }
                features = [...groups.values()];
            }

            const levels = [next.district, next.subdistrict].filter(Boolean).map(code => known.get(code)?.label).filter(Boolean);
            heading.textContent = levels.join(' › ') || 'Jawa Barat · Kabupaten/kota';
            layer?.remove();
            let selected;
            layer = L.geoJSON(features, {
                smoothFactor: 1.4,
                style: feature => {
                    const code = feature.properties.code;
                    const state = category(code);
                    const hasSubmission = next.subdistrict && state !== 'outside' && state !== 'missing_master'
                        && areasWithSubmissions.has(code);
                    return { color: code === next.village || hasSubmission ? '#0d3f5b' : '#fff',
                        weight: code === next.village ? 3 : hasSubmission ? 2 : 1,
                        dashArray: hasSubmission ? '5 4' : null,
                        fillColor: palette[state], fillOpacity: state === 'outside' ? .18 : .65 };
                },
                onEachFeature: (feature, shape) => {
                    const code = feature.properties.code;
                    if (code === next.village) selected = shape;
                    shape.bindTooltip(tooltip(code), { sticky: true });
                    if (feature.geometry.type !== 'GeometryCollection') {
                        shape.on('mouseover', () => shape.setStyle({ color: '#0d3f5b', weight: 3, fillOpacity: .78 }));
                        shape.on('mouseout', () => layer.resetStyle(shape));
                    }
                    shape.on('click', () => {
                        if (code.length <= 8) showArea(code);
                        else open({ ...next, village: code }, false);
                    });
                },
            }).addTo(map);
            renderedNavigation = next;
            if (focusSavedArea && next.district) {
                const target = next.village ? selected?.getBounds()
                    : next.subdistrict ? layer.getBounds()
                    : districtFeature ? L.geoJSON(districtFeature).getBounds() : null;
                if (target?.isValid()) map.fitBounds(target, { padding: [22, 22], maxZoom: 15, animate: false });
            } else if (fit && startViewport === viewportRevision() && (!zoomRevision || zoomRevision()) && layer.getBounds().isValid()) {
                const target = selected?.getBounds() ?? layer.getBounds();
                if (centerOnly) map.panTo(target.getCenter(), { animate: false });
                else map.fitBounds(target, { padding: [22, 22], maxZoom: 15, animate: false });
            }
            updateOptions();
            status.textContent = features.length ? '' : 'Batas wilayah tidak ditemukan.';
            mapElement.dataset.areaLevel = next.subdistrict ? 'desa' : next.district ? 'kecamatan' : 'kabupaten';
            mapElement.dataset.areaPolygonCount = String(features.reduce((total, feature) =>
                total + (feature.geometry.geometries?.length ?? 1), 0));
            if (next.village) showDetail(next.village);
            return true;
        } catch (error) {
            if (sequence !== revision || signal.aborted) return;
            status.textContent = 'Batas administratif belum dapat ditampilkan. Gunakan Lihat Jawa Barat untuk mencoba lagi.';
            return false;
        } finally {
            if (sequence === revision) navigating = false;
        }
    }

    const open = (...args) => navigate(...args).catch(() => {});
    search.addEventListener('input', updateOptions);
    options.addEventListener('change', () => { if (options.value) showArea(options.value); });
    back.addEventListener('click', () => open(navigation.subdistrict ? { district: navigation.district } : {}, true));

    return {
        clear() {
            revision++;
            loading = true;
            navigating = false;
            search.disabled = options.disabled = back.disabled = true;
            delete mapElement.dataset.areaLevel;
            status.textContent = 'Menunggu data filter…';
            categories = {};
            villageCategories = {};
            areasWithSubmissions.clear();
            layer?.remove();
            renderedNavigation = null;
            resetDetail();
        },
        async setData(values, submissions, url, canFit, background = false, focusSavedArea = false) {
            const nextFilters = new URLSearchParams(url.search);
            const geographyChanged = ['kabupaten', 'kecamatan', 'desa'].some(key => filters.get(key) !== nextFilters.get(key));
            const shouldCenter = initialized && geographyChanged && nextFilters.has('kabupaten');
            villageCategories = values || {};
            areasWithSubmissions = new Set(Array.isArray(submissions) ? submissions : []);
            if (known.size) indexCategories();
            filters = nextFilters;
            if (!initialized || geographyChanged || focusSavedArea) {
                navigation = { district: filters.get('kabupaten'), subdistrict: filters.get('kecamatan'), village: filters.get('desa') };
            }
            initialized = true;
            loading = false;
            search.disabled = options.disabled = false;
            if (background && !geographyChanged) {
                if (renderedNavigation === navigation) {
                    layer.eachLayer(shape => {
                        layer.resetStyle(shape);
                        shape.setTooltipContent(tooltip(shape.feature.properties.code));
                    });
                    if (navigation.village) await showDetail(navigation.village, true);
                    return true;
                }
                // Ongoing navigation will use the latest categories when it renders.
                if (navigating) return true;
            }
            return open(navigation, shouldCenter, canFit, true, focusSavedArea);
        },
        home() {
            search.value = '';
            home();
            return open({}, false);
        },
        abort() { detailRequest?.abort(); statusRequest?.abort(); },
    };
}
