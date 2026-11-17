(() => {
    const frames = new Set();
    const visible = section => !section.hidden && (!section.closest('[data-menu-archives]') || section.closest('[data-menu-archives]').open);
    const prepare = section => {
        if (section.dataset.illustratedInitialized) return;
        section.dataset.illustratedInitialized = 'true';
        const fallback = document.createElement('div');
        fallback.className = `${section.className} tccms-illustrated-fallback`;
        const stage = document.createElement('div');
        stage.className = 'tccms-illustrated-stage';
        const frame = document.createElement('iframe');
        frame.className = 'tccms-illustrated-frame';
        frame.title = section.querySelector('h2').textContent.trim();
        frame.setAttribute('aria-hidden', 'true');
        frame.tabIndex = -1;
        frame.referrerPolicy = 'same-origin';
        const footer = section.querySelector('.tccms-week-footer');
        section.classList.remove('tccms-week--with-rail');
        fallback.append(...section.childNodes);
        stage.append(fallback, frame);
        section.append(stage);
        if (footer) section.append(footer);
        const state = { section, stage, frame, fallback, layout: '', width: 1280, height: 794, ready: false };
        const fit = () => {
            if (!visible(section) || stage.clientWidth < 1) return;
            const layout = stage.clientWidth < 960 ? 'mobile' : 'screen';
            state.width = layout === 'mobile' ? stage.clientWidth : 1280;
            const scale = Math.min(1, stage.clientWidth / state.width);
            frame.style.width = `${state.width}px`;
            frame.style.height = `${state.height}px`;
            frame.style.transform = `scale(${scale})`;
            if (state.ready) stage.style.height = `${Math.ceil(state.height * scale)}px`;
            if (layout !== state.layout) {
                state.layout = layout;
                state.ready = false;
                stage.classList.remove('is-ready');
                stage.style.height = '';
                fallback.hidden = false;
                frame.setAttribute('aria-hidden', 'true');
                frame.tabIndex = -1;
                const source = new URL(section.dataset.illustratedUrl, location.href);
                if (source.origin !== location.origin) return;
                source.searchParams.set('layout', layout);
                frame.src = source.href;
            }
        };
        const fail = () => {
            state.ready = false;
            fallback.hidden = false;
            stage.style.height = '';
            stage.classList.remove('is-ready');
            frame.setAttribute('aria-hidden', 'true');
            frame.tabIndex = -1;
        };
        frame.addEventListener('error', fail);
        state.fit = fit;
        state.fail = fail;
        frames.add(state);
        new ResizeObserver(fit).observe(stage);
        fit();
    };
    const scan = () => {
        document.querySelectorAll('[data-illustrated-url]').forEach(section => {
            if (visible(section)) prepare(section);
        });
        for (const state of frames) if (visible(state.section)) state.fit();
    };
    window.addEventListener('message', event => {
        if (event.origin !== location.origin) return;
        const state = [...frames].find(item => item.frame.contentWindow === event.source);
        if (!state) return;
        if (event.data?.type === 'SVJ_AUTUMN_ERROR') { state.fail(); return; }
        if (event.data?.type !== 'SVJ_AUTUMN_DIMENSIONS' || event.data.week !== state.section.dataset.weekStart || !Number.isFinite(event.data.height) || event.data.height < 1 || event.data.height > 50000 || !Number.isFinite(event.data.width) || Math.abs(event.data.width - state.width) > 2) return;
        state.height = event.data.height;
        state.ready = true;
        state.fallback.hidden = true;
        state.stage.classList.add('is-ready');
        state.frame.removeAttribute('aria-hidden');
        state.frame.tabIndex = 0;
        state.fit();
    });
    document.addEventListener('tccm-school-menu-selected', scan);
    document.querySelectorAll('[data-menu-archives]').forEach(details => details.addEventListener('toggle', scan));
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', scan);
    else scan();
})();
