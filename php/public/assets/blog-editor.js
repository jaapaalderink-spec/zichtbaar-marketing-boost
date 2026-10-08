(() => {
  const body = document.getElementById('blog-body');
  const toolbar = document.querySelector('.blog-formatting');
  if (!body || !toolbar) return;
  toolbar.hidden = false;
  function replace(start, end, text, selectionStart, selectionEnd) {
    if (body.value.length - (end - start) + text.length > body.maxLength) return;
    body.setRangeText(text, start, end, 'select');
    body.focus();
    body.setSelectionRange(selectionStart, selectionEnd);
    body.dispatchEvent(new Event('input', { bubbles: true }));
  }
  function heading(level) {
    const start = body.selectionStart === 0 ? 0 : body.value.lastIndexOf('\n', body.selectionStart - 1) + 1;
    let end = body.selectionEnd;
    if (end > start && body.value[end - 1] === '\n') end--;
    const next = body.value.indexOf('\n', end);
    end = next === -1 ? body.value.length : next;
    const prefix = level ? '#'.repeat(level) + ' ' : '';
    const text = body.value.slice(start, end).split('\n').map(line =>
      prefix + line.replace(/^#{2,4} /, '')
    ).join('\n');
    replace(start, end, text, start, start + text.length);
  }
  function bold() {
    let start = body.selectionStart, end = body.selectionEnd;
    const selected = body.value.slice(start, end);
    if (selected.startsWith('**') && selected.endsWith('**') && selected.length > 4) {
      replace(start, end, selected.slice(2, -2), start, end - 4);
    } else if (body.value.slice(start - 2, start) === '**' && body.value.slice(end, end + 2) === '**') {
      replace(start - 2, end + 2, selected, start - 2, end - 2);
    } else {
      const text = selected || 'vetgedrukte tekst';
      // Bold cannot cross a paragraph or heading boundary.
      const formatted = text.split('\n').map(line => line.replace(/^(\s*)(.*?)(\s*)$/, (_, before, inner, after) =>
        before + (inner ? '**' + inner + '**' : '') + after
      )).join('\n');
      replace(start, end, formatted, start, start + formatted.length);
    }
  }
  toolbar.addEventListener('click', event => {
    const button = event.target.closest('button');
    if (!button) return;
    if (button.hasAttribute('data-heading')) heading(Number(button.dataset.heading));
    else bold();
  });
  body.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'b') {
      event.preventDefault(); bold();
    }
  });
})();
