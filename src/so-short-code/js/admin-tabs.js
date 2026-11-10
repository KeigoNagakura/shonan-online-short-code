document.addEventListener('DOMContentLoaded', () => {
    const tabs = Array.from(document.querySelectorAll('[data-sosc-tab-target]')).filter((tab) => !tab.closest('[hidden]'));

    document.querySelectorAll('.wrap table.widefat, .wrap table.table').forEach((table) => {
        if (!table.parentElement.classList.contains('sosc-table-wrap')) {
            const wrapper = document.createElement('div');
            wrapper.className = 'sosc-table-wrap';
            table.parentNode.insertBefore(wrapper, table);
            wrapper.appendChild(table);
        }
        table.querySelectorAll('thead th:not([scope])').forEach((header) => header.setAttribute('scope', 'col'));
        table.querySelectorAll('tbody tr > th:first-child:not([scope])').forEach((header) => header.setAttribute('scope', 'row'));
    });

    document.querySelectorAll('[data-sosc-confirm]').forEach((button) => {
        button.form.addEventListener('submit', (event) => {
            if (!window.confirm(button.getAttribute('data-sosc-confirm'))) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('.sosc-shortcode-table .fw-bold').forEach((example) => {
        const documented = example.childNodes[0] ? example.childNodes[0].textContent.trim() : '';
        const shortcode = documented
            .replace(/(\w+)=\*[^\s\]]+/gu, (match, name) => {
				const numeric = /^(?:id|post_id|user_id|list|count|number)$/iu.test(name);
                return `${name}=${numeric ? '1' : 'example'}`;
            })
            .replace(/\s+\w+=[^\x00-\x7F\s\]]+/gu, '');
        if (!shortcode.startsWith('[sosc_')) {
            return;
        }
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'button button-small sosc-copy-shortcode';
        button.textContent = 'コピー';
        button.setAttribute('aria-label', '実行可能なショートコード例をコピー');
        button.addEventListener('click', async () => {
            try {
                if (navigator.clipboard && window.isSecureContext) {
                    await navigator.clipboard.writeText(shortcode);
                } else {
                    const input = document.createElement('textarea');
                    input.value = shortcode;
                    input.setAttribute('readonly', '');
                    input.style.position = 'fixed';
                    input.style.opacity = '0';
                    document.body.appendChild(input);
                    input.select();
                    if (!document.execCommand('copy')) throw new Error('copy failed');
                    input.remove();
                }
                button.textContent = 'コピーしました';
            } catch (error) {
                button.textContent = 'コピーできませんでした';
            }
            window.setTimeout(() => { button.textContent = 'コピー'; }, 2000);
        });
        example.appendChild(document.createTextNode(' '));
        example.appendChild(button);
    });

    document.querySelectorAll('form[data-sosc-busy-label]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('button, input[type="submit"]');
            if (!button) return;
            button.disabled = true;
            if (button.tagName === 'INPUT') button.value = form.dataset.soscBusyLabel;
            else button.textContent = form.dataset.soscBusyLabel;
            form.setAttribute('aria-busy', 'true');
        });
    });

    const activate = (tab, moveFocus = false) => {
            const targetId = tab.getAttribute('data-sosc-tab-target');

            tabs.forEach((item) => {
                const selected = item === tab;
                item.classList.toggle('active', selected);
                item.setAttribute('aria-selected', selected ? 'true' : 'false');
                item.setAttribute('tabindex', selected ? '0' : '-1');
            });

            document.querySelectorAll('.sosc-tab-pane').forEach((panel) => {
                const selected = panel.id === targetId;
                panel.classList.toggle('show', selected);
                panel.classList.toggle('active', selected);
            });
            const key = tab.getAttribute('data-sosc-tab-key');
            if (key && window.history && window.history.replaceState) {
                const url = new URL(window.location.href);
                url.searchParams.set('tab', key);
                window.history.replaceState({}, '', url);
            }
            if (moveFocus) {
                tab.focus();
            }
    };

    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => activate(tab));
        tab.addEventListener('keydown', (event) => {
            let next = null;
            if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
            if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
            if (event.key === 'Home') next = 0;
            if (event.key === 'End') next = tabs.length - 1;
            if (next !== null) {
                event.preventDefault();
                activate(tabs[next], true);
            }
        });
    });
});
