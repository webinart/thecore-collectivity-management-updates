(() => {
    'use strict';
    wp.domReady(() => {
        const labels = window.TCCMProgrammeLabels;
        for (const editor of document.querySelectorAll('[data-tccm-programme-editor]')) {
            const payload = editor.querySelector('[name="tccm_event_programme_payload"]');
            const list = editor.querySelector('[data-tccm-programme-days]');
            const error = editor.querySelector('[data-tccm-programme-error]');
            const lockName = 'tccm-event-programme';
            let locked = false;
            let programme;
            const saveLock = invalid => {
                if (invalid === locked || !wp.data?.dispatch('core/editor')) return;
                locked = invalid;
                const store = wp.data.dispatch('core/editor');
                if (invalid) store.lockPostSaving(lockName);
                else store.unlockPostSaving(lockName);
            };
            try {
                programme = JSON.parse(payload.value);
                if (!Array.isArray(programme.days)) throw new Error();
            } catch {
                error.hidden = false;
                error.textContent = labels.invalid;
                saveLock(true);
                continue;
            }
            const create = (tag, text = '', className = '') => {
                const element = document.createElement(tag);
                element.textContent = text;
                element.className = className;
                return element;
            };
            const validate = () => {
                const dates = programme.days.map(day => day.date);
                list.querySelectorAll('[data-programme-date]').forEach((input, index) => {
                    input.setCustomValidity(dates.filter(date => date === dates[index]).length > 1 ? labels.duplicate : '');
                });
                list.querySelectorAll('[data-programme-item]').forEach(panel => {
                    const end = panel.querySelector('[data-programme-field="end"]');
                    const start = panel.querySelector('[data-programme-field="start"]');
                    const duration = panel.querySelector('[data-programme-field="duration"]');
                    end.setCustomValidity(end.value && (!start.value || Number(duration.value) > 0) ? labels.choice : '');
                });
                const valid = [...list.querySelectorAll('input,textarea')].every(input => input.checkValidity());
                error.hidden = valid;
                error.textContent = valid ? '' : labels.invalid;
                saveLock(!valid);
                return valid;
            };
            const sync = () => {
                payload.value = JSON.stringify(programme);
                validate();
            };
            const button = (label, action) => {
                const control = create('button', label, 'button');
                control.type = 'button';
                control.addEventListener('click', action);
                return control;
            };
            const field = (record, key, label, type = 'text', maxLength = 200) => {
                const wrapper = create('label', label);
                const input = create(type === 'textarea' ? 'textarea' : 'input');
                input.dataset.programmeField = key;
                if (type !== 'textarea') input.type = type;
                else input.rows = 2;
                input.value = key === 'duration' && !record[key] ? '' : record[key];
                if (['text', 'textarea'].includes(type)) input.maxLength = maxLength;
                if (key === 'title' || key === 'date') input.required = true;
                if (key === 'date') input.dataset.programmeDate = 'true';
                if (key === 'duration') { input.min = 0; input.max = 1440; input.step = 1; }
                input.addEventListener('input', () => {
                    record[key] = key === 'duration' ? Number(input.value || 0) : input.value;
                    sync();
                });
                wrapper.append(input);
                return wrapper;
            };
            const render = () => {
                list.replaceChildren();
                if (!programme.days.length) list.append(create('p', labels.empty));
                programme.days.forEach((day, dayIndex) => {
                    const group = create('fieldset');
                    group.dataset.programmeDay = 'true';
                    group.append(create('legend', `${labels.day} ${dayIndex + 1}`));
                    const header = create('div', '', 'tccm-programme-editor__day-header');
                    header.append(field(day, 'date', labels.date, 'date'), button(labels.removeDay, () => {
                        if (day.items.length && !window.confirm(labels.confirm)) return;
                        programme.days.splice(dayIndex, 1);
                        render(); sync();
                    }));
                    group.append(header);
                    day.items.forEach((item, itemIndex) => {
                        const panel = create('div', '', 'tccm-programme-editor__item');
                        panel.dataset.programmeItem = 'true';
                        const fields = create('div', '', 'tccm-programme-editor__fields');
                        fields.append(field(item, 'title', labels.title), field(item, 'start', labels.start, 'time'), field(item, 'end', labels.end, 'time'), field(item, 'duration', labels.duration, 'number'));
                        const details = create('div', '', 'tccm-programme-editor__details');
                        details.append(field(item, 'description', labels.description, 'textarea', 4000), field(item, 'location', labels.location));
                        const actions = create('div', '', 'tccm-programme-editor__actions');
                        for (const [label, offset] of [[labels.up, -1], [labels.down, 1]]) {
                            const move = button(label, () => {
                                const destination = itemIndex + offset;
                                [day.items[destination], day.items[itemIndex]] = [day.items[itemIndex], day.items[destination]];
                                render(); sync();
                                list.querySelectorAll('[data-programme-day]')[dayIndex].querySelectorAll('[data-programme-field="title"]')[destination].focus();
                            });
                            move.disabled = itemIndex + offset < 0 || itemIndex + offset >= day.items.length;
                            move.setAttribute('aria-label', `${label} : ${item.title || labels.title}`);
                            actions.append(move);
                        }
                        actions.append(button(labels.removeItem, () => {
                            if (item.title && !window.confirm(labels.confirm)) return;
                            day.items.splice(itemIndex, 1);
                            render(); sync();
                        }));
                        panel.append(fields, details, actions);
                        group.append(panel);
                    });
                    group.append(button(labels.addItem, () => {
                        if (programme.days.reduce((count, day) => count + day.items.length, 0) >= 500) { window.alert(labels.limit); return; }
                        day.items.push({ title: '', start: '', end: '', duration: 0, description: '', location: '' });
                        render(); sync();
                        list.querySelectorAll('[data-programme-day]')[dayIndex].querySelectorAll('[data-programme-field="title"]')[day.items.length - 1].focus();
                    }));
                    list.append(group);
                });
                validate();
            };
            editor.querySelector('[data-tccm-programme-add-day]').addEventListener('click', () => {
                if (programme.days.length >= 31) { window.alert(labels.limit); return; }
                let date = editor.dataset.startDate;
                if (programme.days.length && programme.days.at(-1).date) {
                    const next = new Date(`${programme.days.at(-1).date}T12:00:00Z`);
                    next.setUTCDate(next.getUTCDate() + 1);
                    if (!Number.isNaN(next.getTime())) date = next.toISOString().slice(0, 10);
                }
                programme.days.push({ date, items: [] });
                render(); sync();
                list.querySelectorAll('[data-programme-date]')[programme.days.length - 1].focus();
            });
            editor.closest('form')?.addEventListener('submit', event => {
                if (!validate()) {
                    event.preventDefault();
                    list.querySelector(':invalid')?.reportValidity();
                }
            });
            render();
        }
    });
})();
