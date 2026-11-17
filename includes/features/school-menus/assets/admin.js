(() => {
    const configuration = window.TCCMSchoolMenus || { markers: [], courses: {} };
    const node = (tag, text, className = '') => {
        const element = document.createElement(tag);
        if (text) element.textContent = text;
        if (className) element.className = className;
        return element;
    };
    const button = (label, action, secondary = true) => {
        const element = node('button', label, secondary ? 'button' : 'button button-primary');
        element.type = 'button';
        element.addEventListener('click', action);
        return element;
    };
    document.querySelector('[data-school-marker-logo]')?.addEventListener('click', () => {
        if (!window.wp?.media) return;
        const frame = wp.media({ title: 'Choisir le logo du repère', library: { type: 'image' }, multiple: false });
        frame.on('select', () => {
            document.querySelector('#tccm-marker-logo').value = frame.state().get('selection').first().id;
            document.querySelectorAll('[name^="tccm_marker_logo_crop["]').forEach(input => { input.value = ''; });
        });
        frame.open();
    });
    document.querySelector('[data-school-marker-clear]')?.addEventListener('click', () => {
        document.querySelector('#tccm-marker-logo').value = '0';
        document.querySelectorAll('[name^="tccm_marker_logo_crop["]').forEach(input => { input.value = ''; });
    });
    document.querySelectorAll('[data-school-menu-editor]').forEach(editor => {
        const payload = editor.querySelector('[name="tccm_school_menu_payload"]');
        const target = editor.querySelector('[data-school-menu-fields]');
        const error = editor.querySelector('[data-school-menu-error]');
        const value = JSON.parse(payload.value);
        const sync = () => { payload.value = JSON.stringify(value); };
        const dateLabel = date => new Intl.DateTimeFormat(configuration.locale || 'fr-FR', { weekday: 'long', day: 'numeric', month: 'long', timeZone: 'UTC' }).format(new Date(`${date}T12:00:00Z`));
        const field = (label, initial, onChange, type = 'text') => {
            const wrapper = node('label', '', 'tccm-menu-field');
            wrapper.append(node('span', label));
            const input = node(type === 'textarea' ? 'textarea' : 'input');
            if (type !== 'textarea') input.type = type;
            input.value = initial;
            input.addEventListener('input', () => { onChange(input.value); sync(); });
            wrapper.append(input);
            return wrapper;
        };
        const remove = (collection, index) => {
            if (confirm('Retirer cet élément ? La modification sera appliquée à l’enregistrement.')) {
                collection.splice(index, 1);
                sync();
                render();
            }
        };
        const renderMarkers = part => {
            const details = node('details', '', 'tccm-menu-markers');
            const selectedNames = part.markers.map(id => configuration.markers.find(marker => marker.id === id)?.name || `Repère supprimé (${id})`);
            const summary = node('summary', selectedNames.length ? selectedNames.join(' · ') : 'Ajouter des repères');
            details.append(summary);
            for (const [family, label] of Object.entries({ preparation: 'Qualité et préparation', origin: 'Origine et fournisseur' })) {
                const group = node('fieldset');
                group.append(node('legend', label));
                const markers = configuration.markers.filter(marker => marker.family === family);
                if (!markers.length) group.append(node('p', 'Aucun repère disponible.'));
                for (const marker of markers) {
                    const option = node('label');
                    const checkbox = node('input');
                    checkbox.type = 'checkbox';
                    checkbox.checked = part.markers.includes(marker.id);
                    checkbox.addEventListener('change', () => {
                        part.markers = checkbox.checked ? [...part.markers, marker.id] : part.markers.filter(id => id !== marker.id);
                        summary.textContent = part.markers.map(id => configuration.markers.find(entry => entry.id === id)?.name || `Repère supprimé (${id})`).join(' · ') || 'Ajouter des repères';
                        sync();
                    });
                    option.append(checkbox, document.createTextNode(` ${marker.name}`));
                    group.append(option);
                }
                details.append(group);
            }
            for (const id of part.markers.filter(id => !configuration.markers.some(marker => marker.id === id))) {
                details.append(button(`Retirer le repère supprimé ${id}`, () => { part.markers = part.markers.filter(markerId => markerId !== id); sync(); render(); }));
            }
            return details;
        };
        const render = () => {
            target.replaceChildren();
            const toolbar = node('div', '', 'tccm-menu-toolbar');
            const startField = field('Semaine du lundi', value.start, date => {
                const start = new Date(`${date}T12:00:00Z`);
                if (!Number.isNaN(start.getTime()) && start.getUTCDay() === 1) {
                    value.start = date;
                    value.days.forEach((day, index) => {
                        const dayDate = new Date(start);
                        dayDate.setUTCDate(start.getUTCDate() + index);
                        day.date = dayDate.toISOString().slice(0, 10);
                        target.querySelector(`[data-day-title="${index}"]`).textContent = dateLabel(day.date);
                    });
                }
            }, 'date');
            startField.querySelector('input').required = true;
            startField.querySelector('input').addEventListener('change', event => event.target.setCustomValidity(new Date(`${event.target.value}T12:00:00Z`).getUTCDay() === 1 ? '' : 'Choisissez un lundi.'));
            toolbar.append(startField, field('Lien de l’affiche originale (facultatif)', value.source_url, url => { value.source_url = url; }, 'url'));
            target.append(toolbar);
            value.days.forEach((day, dayIndex) => {
                const section = node('section', '', 'tccm-menu-day');
                const heading = node('h3', dateLabel(day.date));
                heading.dataset.dayTitle = String(dayIndex);
                section.append(heading);
                const controls = node('div', '', 'tccm-menu-day-controls');
                const statusLabel = node('label', 'Statut de la journée ');
                const status = node('select');
                for (const [key, label] of Object.entries({ not_specified: 'Menu non renseigné', menu: 'Menu renseigné', closed: 'Pas de service' })) {
                    const option = node('option', label);
                    option.value = key;
                    option.selected = day.status === key;
                    status.append(option);
                }
                status.addEventListener('change', () => {
                    const hasFood = Object.values(day.courses).some(items => items.length);
                    if (status.value !== 'menu' && hasFood && !confirm('Retirer tous les aliments de cette journée ?')) { status.value = day.status; return; }
                    day.status = status.value;
                    if (day.status !== 'menu') { day.vegetarian = false; Object.keys(day.courses).forEach(course => { day.courses[course] = []; }); }
                    sync(); render();
                });
                statusLabel.append(status);
                const vegetarianLabel = node('label');
                const vegetarian = node('input');
                vegetarian.type = 'checkbox';
                vegetarian.checked = day.vegetarian;
                vegetarian.disabled = day.status !== 'menu';
                vegetarian.addEventListener('change', () => { day.vegetarian = vegetarian.checked; sync(); });
                vegetarianLabel.append(vegetarian, document.createTextNode(' Menu végétarien'));
                controls.append(statusLabel, vegetarianLabel);
                section.append(controls);
                if (day.status === 'menu') {
                    const courses = node('div', '', 'tccm-menu-courses');
                    for (const [course, label] of Object.entries(configuration.courses)) {
                        const column = node('div', '', 'tccm-menu-course');
                        column.append(node('h4', label));
                        day.courses[course].forEach((item, itemIndex) => {
                            const itemBox = node('div', '', 'tccm-menu-item');
                            item.parts.forEach((part, partIndex) => {
                                const partBox = node('div', '', 'tccm-menu-part');
                                const input = field(partIndex ? 'Suite ou ingrédient précis' : 'Aliment ou préparation', part.text, text => { part.text = text; });
                                input.querySelector('input').required = true;
                                input.querySelector('input').maxLength = 500;
                                partBox.append(input, renderMarkers(part));
                                if (partIndex) partBox.append(button('Retirer ce fragment', () => remove(item.parts, partIndex)));
                                itemBox.append(partBox);
                            });
                            const actions = node('div', '', 'tccm-menu-actions');
                            const addPart = button('Détailler un ingrédient', () => { item.parts.push({ text: '', markers: [] }); sync(); render(); });
                            addPart.disabled = item.parts.length >= 12;
                            actions.append(addPart, button('Retirer l’aliment', () => remove(day.courses[course], itemIndex)));
                            itemBox.append(actions);
                            column.append(itemBox);
                        });
                        const addItem = button('Ajouter un aliment', () => { day.courses[course].push({ parts: [{ text: '', markers: [] }] }); sync(); render(); });
                        addItem.disabled = day.courses[course].length >= 12;
                        column.append(addItem);
                        courses.append(column);
                    }
                    section.append(courses);
                }
                target.append(section);
            });
            target.append(field('Informations complémentaires (facultatif)', value.notice, notice => { value.notice = notice; }, 'textarea'));
            for (const [key, title, description] of [
                ['source_markers', 'Origines mentionnées sur l’affiche', 'Ces repères apparaissent dans la légende, sans être attribués à tous les aliments.'],
                ['source_badges', 'Badges à droite du tableau', 'Ces repères de l’affiche restent séparés des fournisseurs de la légende et des origines attribuées aux aliments.']
            ]) {
                const sourceMarkers = node('fieldset', '', 'tccm-menu-markers');
                sourceMarkers.append(node('legend', title), node('p', description, 'description'));
                for (const marker of configuration.markers.filter(marker => marker.family === 'origin')) {
                    const option = node('label');
                    const checkbox = node('input');
                    checkbox.type = 'checkbox';
                    checkbox.checked = (value[key] || []).includes(marker.id);
                    checkbox.addEventListener('change', () => {
                        const selected = value[key] || [];
                        value[key] = checkbox.checked ? [...selected, marker.id] : selected.filter(id => id !== marker.id);
                        sync();
                    });
                    option.append(checkbox, document.createTextNode(` ${marker.name}`));
                    sourceMarkers.append(option);
                }
                for (const id of (value[key] || []).filter(id => !configuration.markers.some(marker => marker.id === id && marker.family === 'origin'))) {
                    sourceMarkers.append(button(`Retirer le repère d’origine indisponible ${id}`, () => { value[key] = value[key].filter(markerId => markerId !== id); sync(); render(); }));
                }
                target.append(sourceMarkers);
            }
        };
        editor.closest('form').addEventListener('submit', event => {
            sync();
            const invalid = value.days.some(day => day.status === 'menu' && !Object.values(day.courses).some(items => items.length));
            error.hidden = !invalid;
            error.textContent = invalid ? 'Ajoutez au moins un aliment à chaque journée déclarée avec un menu.' : '';
            if (invalid) { event.preventDefault(); error.scrollIntoView({ block: 'center' }); }
        });
        render();
    });
})();
