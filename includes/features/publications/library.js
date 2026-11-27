function initPublicationComposer() {
    const fitPreview = frame => {
        const main = frame.contentDocument?.querySelector('main');
        if (!main || frame.previewDocument === main.ownerDocument) return;
        frame.previewDocument = main.ownerDocument;
        const fit = () => {
            const width = frame.offsetWidth;
            if (!width) return;
            const height = Math.ceil(main.getBoundingClientRect().height);
            const scale = frame.getBoundingClientRect().width / width;
            const preview = frame.parentElement;
            preview.style.setProperty('--tccm-frame-height', height + 'px');
            preview.style.setProperty('--tccm-preview-height', Math.ceil(height * scale) + 'px');
        };
        fit();
        new frame.contentWindow.ResizeObserver(fit).observe(main);
    };
    const frames = '.tccm-catalog-thumb iframe';
    document.addEventListener('load', event => {
        if (event.target.matches?.(frames)) fitPreview(event.target);
    }, true);
    document.querySelectorAll(frames).forEach(fitPreview);

    const list = document.querySelector('[data-composition]');
    if (!list) return;
    const form = list.closest('form');
    const status = form.querySelector('[role="status"]');
    let dragging = null;

    function controls(row) {
        row.draggable = true;
        const name = row.querySelector('strong').textContent;
        const group = document.createElement('div');
        for (const [text, operation] of [['↑', 'Remonter'], ['↓', 'Descendre'], ['×', 'Retirer']]) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = text;
            button.setAttribute('aria-label', operation + ' ' + name);
            button.addEventListener('click', () => {
                if (text === '×') {
                    (row.nextElementSibling?.querySelector('button') || row.previousElementSibling?.querySelector('button') || form.querySelector('[data-add]')).focus();
                    row.remove();
                } else {
                    const adjacent = text === '↑' ? row.previousElementSibling : row.nextElementSibling;
                    if (!adjacent) return;
                    if (text === '↑') adjacent.before(row); else adjacent.after(row);
                    button.focus();
                }
                status.textContent = text === '×' ? name + ' retiré' : name + ', position ' + (Array.from(list.children).indexOf(row) + 1);
            });
            group.append(button);
        }
        row.append(group);
    }

    list.querySelectorAll('li').forEach(controls);
    form.querySelectorAll('[data-add]').forEach(button => button.addEventListener('click', () => {
        const row = document.createElement('li');
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'parts[]';
        input.value = button.dataset.add;
        const name = document.createElement('strong');
        name.textContent = button.lastElementChild.textContent;
        const thumb = button.querySelector('.tccm-catalog-thumb');
        row.append(input);
        if (thumb) row.append(thumb.cloneNode(true));
        row.append(name);
        controls(row);
        list.append(row);
        status.textContent = name.textContent + ' ajouté en position ' + list.children.length;
    }));
    list.addEventListener('dragstart', event => {
        dragging = event.target.closest('li');
        if (dragging) event.dataTransfer.setData('text/plain', dragging.querySelector('strong').textContent);
    });
    list.addEventListener('dragover', event => { if (dragging) event.preventDefault(); });
    list.addEventListener('drop', event => {
        const target = event.target.closest('li');
        if (!dragging || !target || target === dragging) return;
        event.preventDefault();
        if (event.clientY > target.getBoundingClientRect().top + target.offsetHeight / 2) target.after(dragging);
        else target.before(dragging);
    });
    list.addEventListener('dragend', () => { dragging = null; });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initPublicationComposer, { once: true });
} else {
    initPublicationComposer();
}
