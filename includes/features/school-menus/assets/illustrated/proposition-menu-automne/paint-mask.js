(() => {
    let preparation;
    let texture;
    const masks = new Map();
    const urls = new Set();
    const normalize = pixels => {
        for (let offset = 3; offset < pixels.length; offset += 4) {
            if (pixels[offset] <= 3) pixels[offset] = 0;
            else if (pixels[offset] >= 252) pixels[offset] = 255;
        }
        return pixels;
    };
    const create = async () => {
        const image = new Image();
        image.src = new URL('../modeles-menus/assets/paint-mask.png', document.baseURI).href;
        await image.decode();
        const canvas = document.createElement('canvas');
        canvas.width = image.naturalWidth;
        canvas.height = image.naturalHeight;
        const context = canvas.getContext('2d');
        context.drawImage(image, 0, 0);
        const data = context.getImageData(0, 0, canvas.width, canvas.height);
        normalize(data.data);
        context.putImageData(data, 0, 0);
        return canvas;
    };
    const imageUrl = async canvas => {
        const blob = await new Promise((resolve, reject) => canvas.toBlob(value => value ? resolve(value) : reject(new Error('Masque indisponible.')), 'image/png'));
        const url = URL.createObjectURL(blob);
        urls.add(url);
        const image = new Image();
        image.src = url;
        await image.decode();
        return url;
    };
    const compose = async (source, width, height) => {
        const canvas = document.createElement('canvas');
        canvas.width = Math.ceil(width * 2);
        canvas.height = Math.ceil(height * 2);
        const context = canvas.getContext('2d');
        const edgeX = Math.min(Math.round(18 * canvas.width / width), Math.floor(canvas.width / 2));
        const edgeY = Math.min(Math.round(12 * canvas.height / height), Math.floor(canvas.height / 2));
        const sourceColumns = [0, source.width * .12, source.width * .88, source.width];
        const sourceRows = [0, source.height * .15, source.height * .85, source.height];
        const targetColumns = [0, edgeX, canvas.width - edgeX, canvas.width];
        const targetRows = [0, edgeY, canvas.height - edgeY, canvas.height];
        for (let row = 0; row < 3; row += 1) for (let column = 0; column < 3; column += 1) {
            context.drawImage(source,
                sourceColumns[column], sourceRows[row], sourceColumns[column + 1] - sourceColumns[column], sourceRows[row + 1] - sourceRows[row],
                targetColumns[column], targetRows[row], targetColumns[column + 1] - targetColumns[column], targetRows[row + 1] - targetRows[row]);
        }
        return imageUrl(canvas);
    };
    const ribbon = async (element, style) => {
        const before = getComputedStyle(element, '::before');
        const width = parseFloat(before.width);
        const height = parseFloat(before.height);
        const maskSource = element.style.getPropertyValue('--autumn-painted-mask').match(/url\("([^"\)]+)/)[1];
        const key = `ribbon:${width}:${height}:${JSON.stringify(style)}:${maskSource}`;
        if (!masks.has(key)) masks.set(key, (async () => {
            texture ||= (async () => {
                const image = new Image();
                image.src = new URL('../modeles-menus/assets/paper-texture.webp', document.baseURI).href;
                await image.decode();
                return image;
            })();
            const mask = new Image(); mask.src = maskSource;
            const [paper] = await Promise.all([texture, mask.decode()]);
            const canvas = document.createElement('canvas');
            canvas.width = mask.naturalWidth; canvas.height = mask.naturalHeight;
            const context = canvas.getContext('2d');
            context.scale(canvas.width / width, canvas.height / height);
            context.beginPath();
            context.roundRect(0, 0, width, height, ['TopLeft', 'TopRight', 'BottomRight', 'BottomLeft'].map(corner => parseFloat(before[`border${corner}Radius`])));
            context.clip();
            const angle = 110 * Math.PI / 180;
            const directionX = Math.sin(angle);
            const directionY = -Math.cos(angle);
            const length = Math.abs(width * directionX) + Math.abs(height * directionY);
            const gradient = context.createLinearGradient(width / 2 - directionX * length / 2, height / 2 - directionY * length / 2, width / 2 + directionX * length / 2, height / 2 + directionY * length / 2);
            const secondary = style.ribbonGradient ? style.ribbonSecondary : style.ribbonColor;
            for (const [position, color] of [[0, secondary], [.24, style.ribbonColor], [.76, style.ribbonColor], [1, secondary]]) gradient.addColorStop(position, color);
            context.fillStyle = gradient; context.fillRect(0, 0, width, height);
            const pattern = context.createPattern(paper, 'repeat');
            pattern.setTransform(new DOMMatrix().scale(260 / paper.naturalWidth, 260 / paper.naturalHeight));
            context.globalCompositeOperation = 'multiply';
            context.fillStyle = pattern; context.fillRect(0, 0, width, height);
            context.globalCompositeOperation = 'source-over';
            const light = context.createLinearGradient(0, 0, 0, height);
            for (const [position, color] of [[0, '#b9ec484d'], [.38, '#ffffff00'], [.76, '#104f1026'], [1, '#96d5273d']]) light.addColorStop(position, color);
            context.fillStyle = light; context.fillRect(0, 0, width, height);
            context.globalCompositeOperation = 'destination-in';
            context.drawImage(mask, 0, 0, width, height);
            return imageUrl(canvas);
        })());
        element.style.setProperty('--autumn-print-ribbon', `url("${await masks.get(key)}")`);
        element.dataset.printRibbon = 'true';
    };
    const prepare = async (paper, ribbonStyle) => {
        preparation ||= create();
        const source = await preparation;
        await Promise.all([...paper.querySelectorAll('.tccms-week-heading h2,.tccms-day-head,.tccms-mobile-heading,.tccms-course,.tccms-mobile-course dt')].map(async element => {
            if (!element.getClientRects().length) return;
            const style = getComputedStyle(element, '::before');
            const width = parseFloat(style.width);
            const height = parseFloat(style.height);
            if (!(width > 0 && height > 0)) return;
            const key = `${width}:${height}`;
            if (!masks.has(key)) masks.set(key, compose(source, width, height));
            element.style.setProperty('--autumn-painted-mask', `url("${await masks.get(key)}")`);
        }));
        if (ribbonStyle) await ribbon(paper.querySelector('.tccms-week-heading h2'), ribbonStyle);
    };
    window.addEventListener('pagehide', event => {
        if (!event.persisted) for (const url of urls) URL.revokeObjectURL(url);
    });
    window.SVJAutumnPaintMask = Object.freeze({ normalize, prepare });
})();
