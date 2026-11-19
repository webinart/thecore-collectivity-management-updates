(() => {
    const storageKey = 'svj-school-menu-designer-v2';
    const themeFormat = window.SVJMenuTheme;
    const server = window.TCCMSDesigner;
    const records = new Map((server?.records || []).map(record => [record.theme.id, record]));
    const clone = value => JSON.parse(JSON.stringify(value));
    const icon = name => `<svg class="icon" aria-hidden="true"><use href="#icon-${name}"/></svg>`;
    const escapeHtml = value => String(value).replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
    const baseModel = themeFormat.toEditor(themeFormat.autumn());
    const foliage = themeFormat.toEditor(themeFormat.autumnSquirrel());
    const simple = clone(baseModel);
    Object.assign(simple, { id: 'autumn-clear', name: 'Automne — Papier clair' });
    simple.elements[1].visible = false;
    simple.style.texture = false;
    simple.style.ribbonColor = '#075b61';
    const presets = [baseModel, foliage, simple, themeFormat.toEditor(themeFormat.winter()), themeFormat.toEditor(themeFormat.winterGarlands()), themeFormat.toEditor(themeFormat.winterEnchanted())];
    let models = clone(presets);
    if (server?.records.length) models = server.records.map(record => {
        const model = themeFormat.toEditor(record.theme);
        model.status = record.published && !record.has_changes ? 'ready' : 'draft';
        return model;
    });
    let currentId = baseModel.id;
    if (server) currentId = models[0].id;
    let selectedId = 'leaves';
    let currentTab = 'composition';
    let mode = 'screen';
    let dirty = false;
    let savedAt = '';
    let themeFilter = 'all';
    let canvasReady = false;
    let canvasDimensions = { width: 1280, height: 850 };
    let assetPurpose = 'add';
    let appearanceSignature = '';
    let pickerSignature = '';
    let storageWarning = '';
    let toastTimer;
    let lastEdit = { key: '', time: 0 };
    const undoStack = [];
    const redoStack = [];
    const element = selector => document.querySelector(selector);
    const mainCanvas = element('#menu-canvas');
    const canvasUrl = mainCanvas.src;
    const previews = new Map();
    const isApprovedAutumn = model => records.get(model.id)?.composition === 'autumn-approved';
    const previewUrl = (model, layout = 'screen') => {
        const url = new URL(canvasUrl);
        if (isApprovedAutumn(model)) {
            url.searchParams.set('composition', 'autumn-approved');
            if (layout !== 'screen') url.searchParams.set('layout', layout);
        }
        return url.href;
    };
    const fitPreview = cover => {
        const frame = cover.querySelector('iframe');
        const scale = Math.min(cover.clientWidth / frame.width, cover.clientHeight / frame.height);
        frame.style.setProperty('--preview-scale', scale);
    };
    const previewSizes = new ResizeObserver(entries => entries.forEach(entry => fitPreview(entry.target)));
    const current = () => models.find(model => model.id === currentId);
    const selected = () => current().elements.find(piece => piece.id === selectedId);
    if (!server) try {
        const stored = JSON.parse(localStorage.getItem(storageKey) || localStorage.getItem('svj-school-menu-designer-v1'));
        if (stored && [1, 2].includes(stored.version) && Array.isArray(stored.models) && stored.models.length && stored.models.length <= 100) {
            const restored = stored.models.map(entry => {
                if (stored.version === 1) return themeFormat.migrate(entry);
                const model = themeFormat.toEditor(entry.theme);
                model.status = entry.workflow?.status === 'ready' ? 'ready' : 'draft';
                model.assignment = entry.workflow?.assignment === 'approved' ? 'approved' : 'communication';
                return model;
            });
            if (new Set(restored.map(model => model.id)).size !== restored.length) throw new Error('Identifiants de modèles dupliqués.');
            models = restored;
            for (const preset of presets) if (!models.some(model => model.id === preset.id)) models.push(clone(preset));
            if (!models.some(model => JSON.stringify(model.style) === JSON.stringify(baseModel.style) && JSON.stringify(model.elements) === JSON.stringify(baseModel.elements))) {
                models.push({ ...clone(baseModel), id: `original-${crypto.randomUUID()}`, name: 'Automne — Hérisson (original)' });
            }
            currentId = models.some(model => model.id === stored.currentId) ? stored.currentId : models[0].id;
            selectedId = current().elements.some(piece => piece.id === 'leaves') ? 'leaves' : current().elements[0]?.id;
            savedAt = stored.savedAt || '';
        }
    } catch (error) { savedAt = ''; storageWarning = 'Les brouillons locaux ne sont pas lisibles. Ils restent conservés dans le navigateur ; les modèles de référence sont affichés.'; }
    const parameters = new URLSearchParams(location.search);
    let requestedTheme = parameters.get('theme');
    if (parameters.get('reference') === '1') {
        const preset = presets.find(model => model.id === requestedTheme);
        if (preset) {
            let reference = models.find(model => JSON.stringify(model.style) === JSON.stringify(preset.style) && JSON.stringify(model.elements) === JSON.stringify(preset.elements));
            if (!reference) {
                reference = { ...clone(preset), id: `${preset.id}-ref-${crypto.randomUUID().slice(0, 8)}`, name: `${preset.name} (référence)` };
                models.push(reference);
            }
            requestedTheme = reference.id;
        }
    }
    if (models.some(model => model.id === requestedTheme)) { currentId = requestedTheme; selectedId = current().elements[0]?.id; currentTab = 'appearance'; }
    const notify = message => {
        clearTimeout(toastTimer);
        element('#toast').textContent = message;
        element('#toast').hidden = false;
        toastTimer = setTimeout(() => { element('#toast').hidden = true; }, 4000);
    };
    const fitCanvas = () => {
        const viewport = element('#canvas-viewport');
        const requested = element('#zoom').value;
        const width = canvasDimensions.width;
        const padding = parseFloat(getComputedStyle(viewport).paddingLeft) + parseFloat(getComputedStyle(viewport).paddingRight);
        const scale = requested === 'fit' ? Math.min(1, Math.max(0.15, (viewport.clientWidth - padding) / width)) : Number(requested);
        const frame = element('#canvas-frame');
        const iframe = element('#menu-canvas');
        frame.style.width = `${width * scale}px`;
        frame.style.height = `${canvasDimensions.height * scale}px`;
        iframe.style.width = `${width}px`;
        iframe.style.height = `${canvasDimensions.height}px`;
        iframe.style.transform = `scale(${scale})`;
    };
    const updateCanvas = () => {
        const source = previewUrl(current(), mode);
        if (mainCanvas.src !== source) {
            canvasReady = false;
            canvasDimensions = { width: { screen: 1280, mobile: 390, print: 1123 }[mode], height: 850 };
            fitCanvas();
            mainCanvas.src = source;
            return;
        }
        if (canvasReady && !isApprovedAutumn(current())) mainCanvas.contentWindow.postMessage({ type: 'SVJ_MENU_UPDATE', model: current(), selectedId: currentTab === 'composition' ? selectedId : null, editingDecorations: currentTab === 'composition', mode }, location.origin);
        fitCanvas();
    };
    const renderList = () => {
        element('#element-list').innerHTML = current().elements.map(piece => `<div class="element-row ${piece.id === selectedId ? 'is-selected' : ''} ${piece.visible ? '' : 'is-hidden'}"><button class="element-select" data-select="${escapeHtml(piece.id)}" aria-pressed="${piece.id === selectedId}"><img src="${escapeHtml(piece.source)}" alt=""><span>${escapeHtml(piece.name)}</span></button><button class="icon-button" data-visibility="${escapeHtml(piece.id)}" aria-label="${piece.visible ? 'Masquer' : 'Afficher'} ${escapeHtml(piece.name)}" title="${piece.visible ? 'Masquer' : 'Afficher'}">${icon(piece.visible ? 'eye' : 'eye-off')}</button></div>`).join('');
    };
    const renderProperties = () => {
        const piece = selected();
        element('#composition-properties').hidden = currentTab !== 'composition' || !piece;
        element('#no-selection').hidden = currentTab !== 'composition' || Boolean(piece);
        element('#appearance-properties').hidden = currentTab !== 'appearance';
        element('#usage-properties').hidden = currentTab !== 'usage';
        if (piece) {
            element('#selected-name').textContent = piece.name;
            element('#selected-image').src = piece.source;
            element('#element-name').value = piece.name;
            element('#element-visible').checked = piece.visible;
            element('#element-scale').value = piece.scale;
            element('#element-scale-number').value = piece.scale;
            element('#element-anchor').value = piece.anchor;
            element('#element-paired').checked = piece.paired;
            element('#element-x').value = piece.x;
            element('#element-y').value = piece.y;
            document.querySelectorAll('[name="element-layer"]').forEach(input => { input.checked = input.value === piece.layer; });
        }
        element('#ribbon-color').value = current().style.ribbonColor;
        element('#ribbon-color-value').textContent = current().style.ribbonColor.toUpperCase();
        element('#menu-font').value = current().style.font;
        element('#menu-font-size').value = current().style.fontSize;
        element('#paper-texture').checked = current().style.texture;
        element('#model-name').value = current().name;
        element('#model-theme').value = current().theme;
        document.querySelectorAll('[name="assignment"]').forEach(input => { input.checked = input.value === current().assignment; });
        renderAppearance();
    };
    const refresh = () => {
        renderModelPicker();
        element('#model-title').textContent = current().name;
        element('#breadcrumb-theme').textContent = current().theme;
        element('#model-status').textContent = current().status === 'ready' ? server ? 'Publié' : 'Prêt à valider' : 'Brouillon';
        element('#model-status').classList.toggle('is-ready', current().status === 'ready');
        element('#save-model span').textContent = current().status === 'ready' ? 'Enregistrer' : 'Enregistrer le brouillon';
        element('#save-status').textContent = dirty ? 'Modifications non enregistrées' : server ? records.has(currentId) ? 'Enregistré dans WordPress' : 'Nouveau modèle non enregistré' : savedAt ? 'Enregistré dans ce navigateur' : 'Non enregistré · Dans ce navigateur uniquement';
        if (server && records.get(currentId)?.locked) element('#save-status').textContent = 'Modèle de référence — dupliquez-le pour créer votre variante';
        element('#undo').disabled = undoStack.length === 0;
        element('#redo').disabled = redoStack.length === 0;
        renderList();
        renderProperties();
        updateCanvas();
    };
    const edit = (key, callback) => {
        const now = Date.now();
        if (lastEdit.key !== key || now - lastEdit.time > 700) {
            undoStack.push(clone(current()));
            if (undoStack.length > 40) undoStack.shift();
        }
        lastEdit = { key, time: now };
        redoStack.length = 0;
        callback();
        current().status = 'draft';
        dirty = true;
        refresh();
    };
    const setTab = tab => {
        currentTab = tab;
        document.querySelectorAll('[data-tab]').forEach(button => { button.setAttribute('aria-selected', String(button.dataset.tab === tab)); });
        renderProperties();
        updateCanvas();
    };
    const selectPiece = pieceId => {
        selectedId = pieceId;
        setTab('composition');
        renderList();
        document.body.dataset.elementsOpen = 'false';
        element('#toggle-elements').setAttribute('aria-expanded', 'false');
    };
    const renderLibrary = () => {
        const search = element('#model-search').value.trim().toLocaleLowerCase('fr');
        const filtered = models.filter(model => (themeFilter === 'all' || model.theme === themeFilter) && model.name.toLocaleLowerCase('fr').includes(search));
        element('#library-count').textContent = `${filtered.length} modèle${filtered.length > 1 ? 's' : ''}`;
        element('#library-group-title').textContent = themeFilter === 'all' ? 'Tous les modèles' : themeFilter;
        element('#library-empty').hidden = filtered.length !== 0;
        previewSizes.disconnect();
        previews.clear();
        const library = element('#model-library');
        library.innerHTML = filtered.map(model => `
            <article class="model-card">
                <div class="cover" aria-hidden="true">
                    <span class="preview-status">Chargement de l’aperçu…</span>
                    <iframe class="model-preview" src="${escapeHtml(previewUrl(model))}" title="Aperçu du modèle" width="1280" height="850" loading="lazy" tabindex="-1"></iframe>
                </div>
                <button class="model-open" data-open-model="${escapeHtml(model.id)}" aria-label="Ouvrir ${escapeHtml(model.name)}">
                    <span class="model-card-info"><strong>${escapeHtml(model.name)}</strong><small>${escapeHtml(model.theme)}</small></span>
                </button>
                <div class="model-card-bottom"><span class="status ${model.status === 'ready' ? 'is-ready' : ''}">${model.status === 'ready' ? server ? 'Publié' : 'Prêt à valider' : 'Brouillon'}</span><button class="text-button" data-duplicate-card="${escapeHtml(model.id)}">${icon('copy')}Dupliquer</button></div>
            </article>`).join('');
        library.querySelectorAll('.model-preview').forEach((frame, index) => {
            previews.set(frame.contentWindow, { frame, model: filtered[index] });
            const cover = frame.parentElement;
            previewSizes.observe(cover);
            frame.addEventListener('load', () => {
                if (!frame.contentDocument?.querySelector('#paper')) cover.querySelector('.preview-status').textContent = 'Aperçu indisponible';
            });
        });
    };
    const showLibrary = () => {
        element('#editor-screen').hidden = true;
        element('#library-screen').hidden = false;
        renderLibrary();
        element('.library-header h1').setAttribute('tabindex', '-1');
        element('.library-header h1').focus();
    };
    const openModel = modelId => {
        if (!models.some(model => model.id === modelId)) return;
        currentId = modelId;
        selectedId = current().elements.find(piece => piece.id === 'leaves')?.id || current().elements[0]?.id;
        undoStack.length = 0;
        redoStack.length = 0;
        lastEdit.key = '';
        element('#library-screen').hidden = true;
        element('#editor-screen').hidden = false;
        setTab('composition');
        refresh();
    };
    const duplicate = modelId => {
        const copy = clone(models.find(model => model.id === modelId));
        copy.id = `model-${crypto.randomUUID()}`;
        copy.name = `${copy.name} — Copie`.slice(0, 80);
        copy.status = 'draft';
        models.push(copy);
        dirty = true;
        openModel(copy.id);
        notify('Déclinaison créée. Vous pouvez modifier son nom dans Utilisation.');
    };
    const openAssets = purpose => {
        if (purpose === 'add' && current().elements.length >= 16) return notify('Un thème peut contenir au maximum 16 illustrations.');
        assetPurpose = purpose;
        element('#asset-dialog-title').textContent = purpose === 'replace' ? 'Remplacer l’illustration' : 'Ajouter une illustration';
        element('#asset-error').hidden = true;
        element('#upload-image').value = '';
        element('#asset-dialog').showModal();
    };
    const applyAsset = (source, name) => {
        edit('asset', () => {
            if (assetPurpose === 'replace' && selected()) Object.assign(selected(), { source, paired: false });
            else {
                const piece = { id: `image-${crypto.randomUUID()}`, name, source, anchor: 'top-right', scale: 100, x: 0, y: 0, layer: 'front', visible: true, paired: false };
                current().elements.push(piece);
                selectedId = piece.id;
            }
        });
        element('#asset-dialog').close();
        setTab('composition');
        renderList();
    };
    const createDialog = () => {
        element('#new-model-base').innerHTML = models.map(model => `<option value="${escapeHtml(model.id)}">${escapeHtml(model.name)}</option>`).join('') + '<option value="blank">Structure seule, sans illustration</option>';
        element('#new-model-theme').value = themeFilter === 'all' ? 'Automne' : themeFilter;
        element('#new-model-name').value = '';
        element('#create-dialog').showModal();
    };
    document.querySelectorAll('[data-open-library]').forEach(button => button.addEventListener('click', showLibrary));
    document.querySelectorAll('.nav-item').forEach(item => item.setAttribute('aria-label', item.textContent.trim()));
    document.querySelectorAll('[data-tab]').forEach(button => button.addEventListener('click', () => setTab(button.dataset.tab)));
    document.querySelectorAll('[data-mode]').forEach(button => button.addEventListener('click', () => {
        mode = button.dataset.mode;
        element('#preview-format').textContent = { screen: 'Aperçu ordinateur', mobile: 'Aperçu téléphone · 390 px', print: 'Impression · A4 paysage' }[mode];
        document.querySelectorAll('[data-mode]').forEach(control => { control.classList.toggle('is-active', control === button); control.setAttribute('aria-pressed', String(control === button)); });
        element('#canvas-viewport').scrollTop = 0;
        updateCanvas();
    }));
    element('#element-list').addEventListener('click', event => {
        const pick = event.target.closest('[data-select]');
        if (pick) selectPiece(pick.dataset.select);
        const visibility = event.target.closest('[data-visibility]');
        if (visibility) edit('visibility', () => { const piece = current().elements.find(candidate => candidate.id === visibility.dataset.visibility); piece.visible = !piece.visible; });
    });
    element('#element-visible').addEventListener('change', event => edit('visible', () => { if (selected()) selected().visible = event.target.checked; }));
    element('#element-name').addEventListener('input', event => edit('element-name', () => { if (selected()) selected().name = event.target.value || 'Illustration'; }));
    for (const selector of ['#element-scale', '#element-scale-number']) element(selector).addEventListener('input', event => {
        const scale = Number(event.target.value);
        if (Number.isFinite(scale) && scale >= 40 && scale <= 200) edit('scale', () => { if (selected()) selected().scale = scale; });
    });
    for (const axis of ['x', 'y']) element(`#element-${axis}`).addEventListener('input', event => {
        const value = Number(event.target.value);
        if (Number.isFinite(value) && value >= -60 && value <= 60) edit(`position-${axis}`, () => { if (selected()) selected()[axis] = value; });
    });
    element('#element-anchor').addEventListener('change', event => edit('anchor', () => { if (selected()) selected().anchor = event.target.value; }));
    element('#element-paired').addEventListener('change', event => edit('paired', () => { if (selected()) { selected().paired = event.target.checked; if (event.target.checked) selected().anchor = 'ribbon'; } }));
    document.querySelectorAll('[name="element-layer"]').forEach(input => input.addEventListener('change', () => edit('layer', () => { if (selected()) selected().layer = input.value; })));
    element('#remove-element').addEventListener('click', () => edit('remove', () => { current().elements = current().elements.filter(piece => piece.id !== selectedId); selectedId = current().elements[0]?.id; }));
    element('#add-element').addEventListener('click', () => openAssets('add'));
    element('#replace-image').addEventListener('click', () => openAssets('replace'));
    element('.asset-library').insertAdjacentHTML('beforeend', [['autumn-squirrel.png', 'Écureuil de l’affiche'], ['winter-fox.png', 'Renardeau emmitouflé'], ['winter-table-garland-cutout-v3.png', 'Guirlande découpée du rebord'], ['winter-table-corner-cutout-v3.png', 'Guirlande découpée d’angle'], ['winter-table-garland-v2.png', 'Guirlande entière'], ['winter-table-corner-v2.png', 'Angle entier'], ['winter-organic-drape.png', 'Fil lumineux'], ['winter-organic-corner.png', 'Branche lumineuse']].map(([source, name]) => `<button data-asset="${source}" data-name="${name}"><img src="assets/${source}" alt=""><span>${name}</span></button>`).join(''));
    document.querySelectorAll('[data-asset]').forEach(button => button.addEventListener('click', () => applyAsset(`assets/${button.dataset.asset}`, button.dataset.name)));
    element('#upload-image').addEventListener('change', event => {
        const file = event.target.files[0];
        if (!file) return;
        const fail = message => { element('#asset-error').textContent = message; element('#asset-error').hidden = false; };
        if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) return fail('Choisissez une image PNG, JPG ou WebP de moins de 2 Mo.');
        const reader = new FileReader();
        reader.onerror = () => fail('L’image ne peut pas être lue. Choisissez un autre fichier.');
        reader.onload = () => {
            const image = new Image();
            image.onload = () => applyAsset(String(reader.result), file.name.replace(/\.[^.]+$/, '').slice(0, 60));
            image.onerror = () => fail('Ce fichier ne contient pas une image lisible.');
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    });
    const setColor = color => edit('color', () => { current().style.ribbonColor = color; });
    element('#ribbon-color').addEventListener('input', event => setColor(event.target.value));
    document.querySelectorAll('[data-color]').forEach(button => button.addEventListener('click', () => setColor(button.dataset.color)));
    element('#menu-font').addEventListener('change', event => edit('font', () => { current().style.font = event.target.value; }));
    element('#menu-font-size').addEventListener('input', event => { const size = Number(event.target.value); if (size >= 17 && size <= 24) edit('font-size', () => { current().style.fontSize = size; }); });
    element('#paper-texture').addEventListener('change', event => edit('texture', () => { current().style.texture = event.target.checked; }));
    element('#model-name').addEventListener('input', event => edit('model-name', () => { current().name = event.target.value || 'Modèle sans titre'; }));
    element('#model-theme').addEventListener('change', event => edit('theme', () => { current().theme = event.target.value; }));
    document.querySelectorAll('[name="assignment"]').forEach(input => input.addEventListener('change', () => edit('assignment', () => { current().assignment = input.value; })));
    const requestServer = async payload => {
        const response = await fetch(server.endpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ ...payload, nonce: server.nonce }) });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.data?.message || 'Enregistrement impossible. Votre saisie reste dans cet écran ; exportez-la pour la conserver.');
        return result.data;
    };
    let saving = false;
    const saveServer = async intent => {
        if (saving) return false;
        const model = current();
        const record = records.get(model.id);
        if (record?.locked) { notify('Dupliquez ce modèle de référence pour le modifier.'); return false; }
        const theme = themeFormat.fromEditor(model);
        const sent = JSON.stringify(theme);
        saving = true;
        element('#save-model').disabled = true;
        element('#confirm-review').disabled = true;
        try {
            const result = await requestServer({ intent, theme, id: record?.id || 0, revision: record?.revision || '' });
            records.set(theme.id, result);
            if (JSON.stringify(themeFormat.fromEditor(model)) === sent) {
                model.status = intent === 'publish' ? 'ready' : 'draft';
                dirty = false;
            }
            savedAt = new Date().toISOString();
            refresh();
            notify(intent === 'publish' ? 'Modèle publié. Il est disponible pour les semaines de menu.' : 'Brouillon enregistré dans WordPress.');
            return true;
        } catch (error) { notify(error.message); return false; }
        finally { saving = false; element('#save-model').disabled = false; element('#confirm-review').disabled = false; }
    };
    element('#save-model').addEventListener('click', async () => {
        try {
            if (server) { await saveServer('draft'); return; }
            const stamp = new Date().toISOString();
            const entries = models.map(model => ({ theme: themeFormat.fromEditor(model), workflow: { status: model.status, assignment: model.assignment } }));
            localStorage.setItem(storageKey, JSON.stringify({ version: 2, models: entries, currentId, savedAt: stamp }));
            savedAt = stamp;
            dirty = false;
            refresh();
            notify('Brouillon enregistré dans ce navigateur.');
        } catch (error) { notify(error.name === 'QuotaExceededError' ? 'Espace de stockage insuffisant. Exportez votre thème en JSON pour le conserver.' : `Enregistrement refusé : ${error.message}`); }
    });
    element('#duplicate-model').addEventListener('click', () => duplicate(currentId));
    const undo = () => {
        if (!undoStack.length) return;
        redoStack.push(clone(current()));
        models[models.findIndex(model => model.id === currentId)] = undoStack.pop();
        lastEdit.key = '';
        dirty = true;
        refresh();
    };
    const redo = () => {
        if (!redoStack.length) return;
        undoStack.push(clone(current()));
        models[models.findIndex(model => model.id === currentId)] = redoStack.pop();
        lastEdit.key = '';
        dirty = true;
        refresh();
    };
    element('#undo').addEventListener('click', undo);
    element('#redo').addEventListener('click', redo);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') { document.body.dataset.elementsOpen = 'false'; element('#toggle-elements').setAttribute('aria-expanded', 'false'); }
        if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'z' && !event.target.closest('input, textarea, select')) { event.preventDefault(); if (event.shiftKey) redo(); else undo(); }
    });
    element('#zoom').addEventListener('change', fitCanvas);
    new ResizeObserver(fitCanvas).observe(element('#canvas-viewport'));
    element('#toggle-elements').addEventListener('click', () => { const open = document.body.dataset.elementsOpen !== 'true'; document.body.dataset.elementsOpen = String(open); element('#toggle-elements').setAttribute('aria-expanded', String(open)); });
    element('#close-elements').addEventListener('click', () => { document.body.dataset.elementsOpen = 'false'; element('#toggle-elements').setAttribute('aria-expanded', 'false'); element('#toggle-elements').focus(); });
    document.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => element(`#${button.dataset.closeDialog}`).close()));
    element('#model-library').addEventListener('click', event => {
        const open = event.target.closest('[data-open-model]');
        if (open) openModel(open.dataset.openModel);
        const copy = event.target.closest('[data-duplicate-card]');
        if (copy) duplicate(copy.dataset.duplicateCard);
    });
    document.querySelectorAll('[data-theme]').forEach(button => button.addEventListener('click', () => { themeFilter = button.dataset.theme; document.querySelectorAll('[data-theme]').forEach(control => control.setAttribute('aria-pressed', String(control === button))); renderLibrary(); }));
    element('#model-search').addEventListener('input', renderLibrary);
    element('#create-model').addEventListener('click', createDialog);
    element('#empty-create').addEventListener('click', createDialog);
    element('#create-form').addEventListener('submit', event => {
        event.preventDefault();
        const basedOn = element('#new-model-base').value;
        const model = clone(models.find(candidate => candidate.id === basedOn) || themeFormat.migrate(baseModel));
        Object.assign(model, { id: `model-${crypto.randomUUID()}`, name: element('#new-model-name').value.trim() || 'Nouveau modèle', theme: element('#new-model-theme').value, status: 'draft' });
        if (basedOn === 'blank') model.elements = [];
        models.push(model);
        dirty = true;
        openModel(model.id);
        element('#create-dialog').close();
    });
    element('#review-model').addEventListener('click', () => { document.querySelectorAll('.review-checks input').forEach(input => { input.checked = false; }); element('#confirm-review').disabled = true; element('#review-dialog').showModal(); });
    element('.review-checks').addEventListener('change', () => { element('#confirm-review').disabled = ![...document.querySelectorAll('.review-checks input')].every(input => input.checked); });
    element('#confirm-review').addEventListener('click', async () => {
        try { themeFormat.fromEditor(current()); } catch (error) { return notify(error.message); }
        if (themeFormat.warnings(current()).length) { element('#review-dialog').close(); setTab('appearance'); return notify('Corrigez les alertes de contraste avant la validation.'); }
        if (server) { if (await saveServer('publish')) element('#review-dialog').close(); return; }
        current().status = 'ready'; dirty = true; refresh(); element('#review-dialog').close(); notify('Modèle prêt à valider. Enregistrez-le pour conserver ce statut.');
    });
    window.addEventListener('message', event => {
        if (event.origin !== location.origin) return;
        const preview = previews.get(event.source);
        if (preview) {
            const { frame, model } = preview;
            const cover = frame.parentElement;
            if (event.data?.type === 'SVJ_MENU_READY') {
                frame.contentWindow.postMessage({ type: 'SVJ_MENU_UPDATE', model, mode: 'screen', thumbnail: true, editingDecorations: false }, location.origin);
            }
            const dimensions = event.data?.type === 'SVJ_MENU_DIMENSIONS' ? event.data.dimensions
                : event.data?.type === 'SVJ_AUTUMN_DIMENSIONS' ? event.data : null;
            if (dimensions && Number.isFinite(dimensions.width) && Number.isFinite(dimensions.height) && dimensions.width > 0 && dimensions.height > 0) {
                if (Number(frame.width) !== dimensions.width) frame.width = dimensions.width;
                if (Number(frame.height) !== dimensions.height) frame.height = dimensions.height;
                fitPreview(cover);
                cover.dataset.ready = 'true';
            }
            if (event.data?.type === 'SVJ_AUTUMN_ERROR') cover.querySelector('.preview-status').textContent = 'Aperçu indisponible';
            return;
        }
        if (event.source !== mainCanvas.contentWindow) return;
        if (event.data?.type === 'SVJ_MENU_READY') { canvasReady = true; window.menuDesignerReady = true; updateCanvas(); }
        if (event.data?.type === 'SVJ_MENU_DIMENSIONS') { canvasDimensions = event.data.dimensions; fitCanvas(); }
        if (event.data?.type === 'SVJ_AUTUMN_DIMENSIONS' && isApprovedAutumn(current())) {
            canvasReady = true;
            window.menuDesignerReady = true;
            canvasDimensions = { width: event.data.width, height: event.data.height };
            fitCanvas();
        }
        if (event.data?.type === 'SVJ_MENU_SELECT') selectPiece(event.data.id);
        if (event.data?.type === 'SVJ_MENU_MOVE') edit('drag', () => { const piece = current().elements.find(candidate => candidate.id === event.data.id); if (piece) { piece.x = Math.max(-60, Math.min(60, Math.round(event.data.x))); piece.y = Math.max(-60, Math.min(60, Math.round(event.data.y))); } });
    });
    const renderModelPicker = () => {
        const signature = JSON.stringify(models.map(model => [model.id, model.name, model.theme]));
        if (pickerSignature !== signature) {
            const themes = [...new Set(models.map(model => model.theme))];
            element('#model-switcher').innerHTML = themes.map(theme => `<optgroup label="${escapeHtml(theme)}">${models.filter(model => model.theme === theme).map(model => `<option value="${escapeHtml(model.id)}">${escapeHtml(model.name)}</option>`).join('')}</optgroup>`).join('');
            pickerSignature = signature;
        }
        element('#model-switcher').value = currentId;
    };
    element('#model-switcher').addEventListener('change', event => {
        openModel(event.target.value);
        const address = new URL(location.href); address.searchParams.set('theme', currentId); address.searchParams.delete('reference'); history.replaceState(null, '', address);
    });
    element('#edit-model-appearance').addEventListener('click', () => {
        setTab('appearance');
        element('#theme-section').focus();
        if (matchMedia('(max-width: 760px)').matches) element('#appearance-properties').scrollIntoView({ block: 'start' });
    });
    element('#theme-section').innerHTML = Object.entries(themeFormat.groups).map(([key, label]) => `<option value="${key}">${label}</option>`).join('');
    const basicPaths = new Set(['style.ribbonColor', 'style.font', 'style.fontSize', 'style.texture']);
    const renderAppearance = () => {
        const group = element('#theme-section').value;
        const scopes = group === 'days' ? themeFormat.days : group === 'courses' ? themeFormat.courses : null;
        const scopeSelect = element('#theme-entry');
        const oldScope = scopeSelect.value;
        if (scopeSelect.dataset.group !== group) {
            scopeSelect.innerHTML = scopes ? Object.entries(scopes).map(([key, label]) => `<option value="${key}">${label}</option>`).join('') : '';
            scopeSelect.dataset.group = group;
            if (scopes && Object.hasOwn(scopes, oldScope)) scopeSelect.value = oldScope;
        }
        element('#theme-entry-group').hidden = !scopes;
        element('#theme-entry-label').textContent = group === 'days' ? 'Jour à personnaliser' : 'Catégorie à personnaliser';
        element('#basic-theme-controls').hidden = group !== 'base';
        const sharedLegend = group === 'badges' && current().style.badges.placement === 'legend';
        const idleAnimation = group === 'animation' && current().style.animation.mode === 'none';
        const settings = themeFormat.fields.filter(setting => setting.group === group && (!idleAnimation || setting.path === 'style.animation.mode') && !basicPaths.has(setting.path) && (!setting.scope || setting.scope === scopeSelect.value)
            && (!sharedLegend || setting.path === 'style.badges.placement' || setting.path.startsWith('style.badges.logos.')));
        const signature = `${currentId}/${group}/${scopeSelect.value}/${sharedLegend}/${idleAnimation}`;
        if (appearanceSignature !== signature) {
            const focusedPath = document.activeElement?.dataset.themePath;
            element('#theme-controls').innerHTML = settings.map(setting => {
                const inputId = `setting-${setting.path.replaceAll('.', '-')}`;
                const attributes = `id="${inputId}" data-theme-path="${setting.path}"`;
                let control;
                if (setting.kind === 'color') control = `<div class="color-field"><input type="color" ${attributes}><output data-color-output="${setting.path}"></output></div>`;
                if (setting.kind === 'number') control = `<div class="number-with-unit"><input type="number" ${attributes} min="${setting.minimum}" max="${setting.maximum}" step="${setting.step}"><span>${setting.unit}</span></div>`;
                if (setting.kind === 'select') control = `<select ${attributes}>${Object.entries(setting.options).map(([key, label]) => `<option value="${key}">${label}</option>`).join('')}</select>`;
                if (setting.kind === 'boolean') control = `<input type="checkbox" ${attributes}>`;
                if (setting.kind === 'asset') control = `<small data-asset-state="${setting.path}"></small>${setting.path === 'style.backgroundImage' ? '<div class="background-presets"><button class="button button-small button-secondary" data-background-preset="assets/autumn-forest-background.png">Sous-bois</button><button class="button button-small button-secondary" data-background-preset="assets/winter-forest-background.png">Forêt enneigée</button></div>' : ''}<input type="file" ${attributes} accept="image/png,image/jpeg,image/webp"><button class="text-button" data-reset-asset="${setting.path}">${setting.path === 'style.backgroundImage' ? 'Retirer le fond' : setting.path === 'style.animation.variant' ? 'Retirer le second motif' : 'Rétablir le visuel d’origine'}</button>`;
                return `<div class="property-group theme-setting"><label for="${inputId}">${setting.label}</label>${control}</div>`;
            }).join('') + (group === 'badges' ? '<p class="property-hint">Les logos importés doivent être les visuels officiels du même label. Le nom et la signification du label ne changent pas.</p>' : '');
            appearanceSignature = signature;
            if (settings.some(setting => setting.path === focusedPath)) element(`[data-theme-path="${focusedPath}"]`).focus();
        }
        for (const setting of settings) {
            const input = element(`[data-theme-path="${setting.path}"]`);
            const value = themeFormat.get(current(), setting.path);
            if (setting.kind === 'asset') element(`[data-asset-state="${setting.path}"]`).textContent = `${setting.path === 'style.backgroundImage' ? value ? 'Fond illustré' : 'Aucun fond illustré' : value ? 'Visuel personnalisé' : setting.path === 'style.animation.variant' ? 'Aucun second motif' : 'Visuel d’origine'} · PNG, JPG ou WebP, 2 Mo maximum`;
            else if (setting.kind === 'boolean') input.checked = value;
            else if (input !== document.activeElement) input.value = value;
            if (setting.kind === 'color') element(`[data-color-output="${setting.path}"]`).textContent = value.toUpperCase();
        }
        const warnings = themeFormat.warnings(current());
        const box = element('#contrast-warnings');
        box.hidden = !warnings.length;
        box.innerHTML = `<strong>Contrastes à vérifier</strong><ul>${warnings.map(message => `<li>${escapeHtml(message)}</li>`).join('')}</ul>`;
    };
    const setThemeField = (setting, value) => edit(setting.path, () => themeFormat.set(current(), setting.path, value));
    element('#theme-section').addEventListener('change', renderAppearance);
    element('#theme-entry').addEventListener('change', renderAppearance);
    document.querySelectorAll('[data-theme-section]').forEach(button => button.addEventListener('click', () => {
        element('#theme-section').value = button.dataset.themeSection; setTab('appearance');
        document.body.dataset.elementsOpen = 'false'; element('#toggle-elements').setAttribute('aria-expanded', 'false');
    }));
    element('#theme-controls').addEventListener('input', event => {
        const setting = themeFormat.fields.find(candidate => candidate.path === event.target.dataset.themePath);
        if (!setting || setting.kind === 'asset') return;
        let value = event.target.value;
        if (setting.kind === 'number') {
            if (!event.target.value || !event.target.validity.valid) return;
            value = Number(value);
        }
        if (setting.kind === 'boolean') value = event.target.checked;
        setThemeField(setting, value);
    });
    element('#theme-controls').addEventListener('click', event => {
        const background = event.target.closest('[data-background-preset]');
        if (background) return setThemeField(themeFormat.fields.find(setting => setting.path === 'style.backgroundImage'), background.dataset.backgroundPreset);
        const setting = themeFormat.fields.find(candidate => candidate.path === event.target.dataset.resetAsset && candidate.kind === 'asset');
        if (setting) setThemeField(setting, '');
    });
    element('#theme-controls').addEventListener('change', async event => {
        const setting = themeFormat.fields.find(candidate => candidate.path === event.target.dataset.themePath && candidate.kind === 'asset');
        const file = event.target.files?.[0];
        if (!setting || !file) return;
        const modelId = currentId;
        try {
            if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) throw new Error('Choisissez un PNG, JPG ou WebP de moins de 2 Mo.');
            const source = await new Promise((resolve, reject) => { const reader = new FileReader(); reader.onload = () => resolve(String(reader.result)); reader.onerror = () => reject(new Error('Image illisible.')); reader.readAsDataURL(file); });
            await new Promise((resolve, reject) => { const picture = new Image(); picture.onload = resolve; picture.onerror = () => reject(new Error('Ce fichier ne contient pas une image lisible.')); picture.src = source; });
            if (modelId === currentId) setThemeField(setting, source);
        } catch (error) { notify(error.message); }
    });
    const importDialog = element('#import-dialog');
    const importFile = element('#import-theme-file');
    const importError = element('#import-error');
    const importProgress = element('#import-progress');
    let importRequest;
    if (server?.archiveEndpoint) {
        importFile.accept += ',.zip,application/zip';
        element('#import-help').textContent = 'Choisissez le ZIP du thème ou un JSON autonome pour ouvrir un nouveau brouillon.';
    }
    const readThemeFile = async (file, signal) => {
        if (!file.name.toLowerCase().endsWith('.zip')) {
            if (file.size > themeFormat.maximumBytes) throw new Error('Choisissez un JSON de 8 Mio maximum.');
            return file.text();
        }
        if (!server?.archiveEndpoint) throw new Error('Choisissez un JSON autonome dans cet éditeur.');
        if (file.size > server.archiveLimit) throw new Error('Le ZIP dépasse la taille d’envoi autorisée.');
        const data = new FormData();
        data.set('archive', file);
        data.set('nonce', server.nonce);
        const response = await fetch(server.archiveEndpoint, { method: 'POST', credentials: 'same-origin', body: data, signal });
        const result = await response.json().catch(() => null);
        if (!response.ok || result?.success !== true) throw new Error(result?.data?.message || 'Le ZIP n’a pas pu être ouvert.');
        return result.data.theme;
    };
    document.querySelectorAll('[data-import-theme]').forEach(button => button.addEventListener('click', () => {
        importError.hidden = true;
        importDialog.showModal();
    }));
    importDialog.addEventListener('close', () => importRequest?.abort());
    importFile.addEventListener('change', async event => {
        const file = event.target.files[0];
        if (!file) return;
        const request = new AbortController();
        importRequest = request;
        importFile.disabled = true;
        importError.hidden = true;
        importProgress.textContent = 'Ouverture du thème…';
        importProgress.hidden = false;
        try {
            const theme = await readThemeFile(file, request.signal);
            if (request.signal.aborted) return;
            const model = themeFormat.toEditor(theme);
            model.id = `model-${crypto.randomUUID()}`;
            models.push(model);
            dirty = true;
            openModel(model.id);
            setTab('appearance');
            importDialog.close();
            notify('Thème importé dans un nouveau brouillon.');
        } catch (error) {
            if (!request.signal.aborted) {
                importError.textContent = error.message;
                importError.hidden = false;
            }
        } finally {
            if (importRequest === request) {
                importRequest = null;
                importFile.disabled = false;
                importFile.value = '';
                importProgress.hidden = true;
            }
        }
    });
    element('#export-theme').addEventListener('click', () => {
        try {
            const theme = themeFormat.fromEditor(current());
            const blobUrl = URL.createObjectURL(new Blob([JSON.stringify(theme, null, 2) + '\n'], { type: 'application/json' }));
            const link = document.createElement('a'); link.href = blobUrl; link.download = `${theme.id}.json`; link.click(); setTimeout(() => URL.revokeObjectURL(blobUrl), 1000);
            notify('Thème exporté en JSON.');
        } catch (error) { notify(`Export refusé : ${error.message}`); }
    });
    if (server) {
        const button = document.createElement('button');
        button.className = 'button button-secondary';
        button.type = 'button';
        button.id = 'create-with-ai';
        button.textContent = 'Créer avec l’IA';
        element('.header-actions').prepend(button);
        document.body.insertAdjacentHTML('beforeend', '<dialog id="ai-dialog" aria-labelledby="ai-title"><form id="ai-form"><div class="dialog-heading"><h2 id="ai-title">Créer un modèle avec l’IA</h2><button type="button" class="button" id="ai-close">Fermer</button></div><label for="ai-brief">Décrivez le thème souhaité</label><textarea id="ai-brief" rows="5" minlength="5" maxlength="3000" required></textarea><p>Le résultat sera un brouillon à vérifier. Les illustrations proviennent de la bibliothèque disponible.</p><p id="ai-feedback" role="status"></p><button class="button button-primary" type="submit">Générer un brouillon</button></form></dialog>');
        button.addEventListener('click', () => { element('#ai-feedback').textContent = ''; element('#ai-dialog').showModal(); element('#ai-brief').focus(); });
        element('#ai-close').addEventListener('click', () => element('#ai-dialog').close());
        element('#ai-form').addEventListener('submit', async event => {
            event.preventDefault();
            const submit = element('#ai-form button[type="submit"]');
            if (submit.disabled) return;
            submit.disabled = true;
            element('#ai-feedback').textContent = 'Génération en cours…';
            try {
                const result = await requestServer({ intent: 'generate', brief: element('#ai-brief').value, theme: themeFormat.fromEditor(current()) });
                const model = themeFormat.toEditor(themeFormat.parse(result.theme));
                records.set(model.id, result);
                models.push(model);
                openModel(model.id);
                dirty = false;
                refresh();
                element('#ai-dialog').close();
                notify('Brouillon IA enregistré. Vérifiez-le avant publication.');
            } catch (error) { element('#ai-feedback').textContent = error.message; }
            finally { submit.disabled = false; }
        });
    }
    setTab(currentTab);
    refresh();
    if (server?.library) showLibrary();
    if (storageWarning) notify(storageWarning);
})();
