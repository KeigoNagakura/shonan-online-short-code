document.addEventListener('click', async function (event) {
    const button = event.target.closest('.sosc-manual-copy');
    if (!button) return;
    try {
        await navigator.clipboard.writeText(button.dataset.code || '');
        button.textContent = 'コピーしました';
        window.setTimeout(function () { button.textContent = 'ショートコードをコピー'; }, 2500);
    } catch (error) {
        button.textContent = 'コピーできませんでした。記述例を選択してください';
    }
});
