(() => {
  const search = document.getElementById('visibilitySearch');
  if (!search) return;

  const groups = document.querySelectorAll('.visibility-group');
  const total = document.getElementById('visibilityTotal');
  search.addEventListener('input', () => {
    const value = search.value.trim().toLocaleLowerCase('ru');
    groups.forEach(group => {
      let found = 0;
      group.querySelectorAll('.visibility-item').forEach(item => {
        const visible = !value || item.dataset.search.includes(value);
        item.hidden = !visible;
        if (visible) found++;
      });
      group.hidden = found === 0;
      if (value && found) group.open = true;
    });
  });

  document.querySelectorAll('.visibility-item input').forEach(input => {
    input.addEventListener('change', () => {
      const status = input.closest('.visibility-item').querySelector('.visibility-status');
      status.textContent = input.checked ? 'Включено' : 'Отключено';
      const enabled = document.querySelectorAll('.visibility-item input:checked').length;
      total.textContent = `${enabled} из ${total.dataset.total}`;
    });
  });
})();
