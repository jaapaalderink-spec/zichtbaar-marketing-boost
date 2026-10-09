(() => {
  'use strict';
  const iso = (y, m, d) => `${y}-${String(m + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
  const monthDate = value => new Date(Number(value.slice(0, 4)), Number(value.slice(5, 7)) - 1, 1, 12);
  const labelDate = value => new Date(`${value}T12:00:00`).toLocaleDateString('nl-NL', {weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'});
  document.querySelectorAll('.zm-date-picker').forEach(root => {
    const form = root.closest('form');
    const source = form.querySelector('[data-calendar-source]');
    const slotsMode = root.dataset.mode === 'slots';
    const options = slotsMode ? Array.from(source.options).filter(o => o.dataset.day) : [];
    const dates = [...new Set(options.map(o => o.dataset.day))].sort();
    if (slotsMode && !dates.length) return;
    let selected = slotsMode ? options.find(o => o.selected)?.dataset.day || dates[0] : source.value;
    const min = slotsMode ? dates[0] : source.min;
    const max = slotsMode ? dates[dates.length - 1] : source.max;
    let month = monthDate(selected || root.dataset.month || min);
    if (month < monthDate(min)) month = monthDate(min);
    if (month > monthDate(max)) month = monthDate(max);
    const button = (text, action) => {
      const b = document.createElement('button'); b.type = 'button'; b.textContent = text;
      b.addEventListener('click', action); return b;
    };
    const heading = document.createElement('h3'); heading.textContent = 'Kies een datum';
    const navigation = document.createElement('div'); navigation.className = 'zm-cal-nav';
    const title = document.createElement('strong'); title.setAttribute('aria-live', 'polite');
    const prev = button('‹', () => {month.setMonth(month.getMonth() - 1); render();});
    const next = button('›', () => {month.setMonth(month.getMonth() + 1); render();});
    prev.setAttribute('aria-label', 'Vorige maand'); next.setAttribute('aria-label', 'Volgende maand');
    navigation.append(prev, title, next);
    const grid = document.createElement('div'); grid.className = 'zm-cal-grid'; grid.setAttribute('role', 'group'); grid.setAttribute('aria-label', 'Datum kiezen');
    const feedback = document.createElement('p'); feedback.setAttribute('role', 'status'); feedback.className = 'zm-cal-status';
    const times = document.createElement('div'); times.className = 'zm-cal-times'; times.setAttribute('role', 'group'); times.setAttribute('aria-label', 'Beschikbaar tijdstip kiezen');
    root.append(heading, navigation, grid, feedback, times);
    function render() {
      title.textContent = month.toLocaleDateString('nl-NL', {month: 'long', year: 'numeric'});
      prev.disabled = month <= monthDate(min); next.disabled = month >= monthDate(max);
      grid.replaceChildren();
      ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo'].forEach(day => {
        const el = document.createElement('span'); el.textContent = day; el.className = 'zm-cal-weekday'; grid.append(el);
      });
      const y = month.getFullYear(), m = month.getMonth();
      const offset = (month.getDay() + 6) % 7;
      for (let i = 0; i < offset; i++) grid.append(document.createElement('span'));
      const count = new Date(y, m + 1, 0).getDate();
      for (let day = 1; day <= count; day++) {
        const value = iso(y, m, day);
        const b = button(String(day), () => {
          selected = value;
          if (slotsMode) source.value = ''; else source.value = value;
          source.dispatchEvent(new Event('change', {bubbles: true})); render();
        });
        b.disabled = value < min || value > max || (slotsMode && !dates.includes(value));
        b.setAttribute('aria-label', labelDate(value) + (b.disabled ? ', niet beschikbaar' : ''));
        b.setAttribute('aria-pressed', String(value === selected));
        grid.append(b);
      }
      times.replaceChildren();
      feedback.textContent = selected ? labelDate(selected) : 'Klik op een datum in de kalender.';
      if (slotsMode && selected) {
        feedback.textContent += source.value ? ' · Tijdstip geselecteerd.' : ' · Kies hieronder een tijdstip.';
        options.filter(o => o.dataset.day === selected).forEach(o => {
          const b = button(o.textContent.trim(), () => {
            source.value = o.value; source.dispatchEvent(new Event('change', {bubbles: true})); render();
          });
          b.setAttribute('aria-pressed', String(source.value === o.value)); times.append(b);
        });
      }
    }
    render();
    source.required = false;
    source.closest('[data-calendar-fallback]').hidden = true;
    form.addEventListener('submit', event => {
      if (!source.value) {
        event.preventDefault(); feedback.textContent = slotsMode ? 'Kies eerst een datum en een tijdstip.' : 'Kies eerst een datum.';
        (times.querySelector('button') || grid.querySelector('button:not(:disabled)'))?.focus();
      }
    });
  });
})();
