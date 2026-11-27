document.addEventListener('click', event => {
    const button = event.target.closest('.tccm-publications button[data-op]');
    if (!button) return;
    const rows = button.closest('fieldset').querySelector('[data-pub-rows]');
    const row = button.closest('[data-pub-row]');
    switch (button.dataset.op) {
        case 'add': {
            const copy = rows.firstElementChild.cloneNode(true);
            copy.querySelector('select').selectedIndex = 0;
            rows.appendChild(copy);
            copy.querySelector('select').focus();
            break;
        }
        case 'remove':
            if (rows.children.length > 1) {
                const sibling = row.nextElementSibling || row.previousElementSibling;
                row.remove();
                sibling.querySelector('select').focus();
            }
            break;
        case 'up':
            if (row.previousElementSibling) rows.insertBefore(row, row.previousElementSibling);
            button.focus();
            break;
        case 'down':
            if (row.nextElementSibling) rows.insertBefore(row.nextElementSibling, row);
            button.focus();
            break;
    }
});