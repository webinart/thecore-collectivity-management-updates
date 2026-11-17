(() => {
    const action = document.querySelector('[data-print-action]');
    const status = document.querySelector('[data-print-status]');
    if (!action || !status) return;
    let ready = false;
    let failed = false;
    action.hidden = false;
    action.addEventListener('click', () => {
        if (failed) location.reload();
        else if (ready) window.print();
    });
    const loadImage = source => new Promise((resolve, reject) => {
        const image = new Image();
        image.onload = resolve;
        image.onerror = reject;
        image.src = source;
    });
    const prepare = async () => {
        status.textContent = 'Préparation de l’impression…';
        const sources = new Set([...document.querySelectorAll('img, svg image')].map(image => image.currentSrc || image.getAttribute('href') || image.getAttribute('src')).filter(Boolean));
        document.querySelectorAll('.tccms-cell, .tccms-week-leaf, .tccms-day-head').forEach(element => {
            const style = getComputedStyle(element);
            for (const value of [style.backgroundImage, getComputedStyle(element, '::before').webkitMaskBoxImageSource]) {
                for (const match of (value || '').matchAll(/url\(["']?([^"')]+)["']?\)/g)) sources.add(match[1]);
            }
        });
        let timer;
        try {
            await Promise.race([
                Promise.all([document.fonts.ready, ...[...sources].map(loadImage)]),
                new Promise((resolve, reject) => { timer = setTimeout(() => reject(new Error('Print preparation timed out.')), 20000); })
            ]);
            ready = true;
            document.body.dataset.printReady = 'true';
            status.textContent = 'Choisissez une imprimante ou « Enregistrer au format PDF » dans la fenêtre d’impression.';
            action.disabled = false;
            await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            window.print();
        } catch (error) {
            failed = true;
            status.textContent = 'Le menu n’a pas pu charger toutes ses illustrations. Rechargez la page avant de l’imprimer.';
            action.textContent = 'Recharger le menu';
            action.disabled = false;
        } finally {
            clearTimeout(timer);
        }
    };
    prepare();
})();
