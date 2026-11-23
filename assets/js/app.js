document.addEventListener('DOMContentLoaded', () => {
  const body = document.body;

  // Sidebar: collapse on desktop (remembered), slide-in on mobile
  try { if (localStorage.getItem('sidebarCollapsed') === '1') body.classList.add('sidebar-collapsed'); } catch (e) {}
  document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    if (window.innerWidth < 992) {
      body.classList.toggle('sidebar-open');
    } else {
      body.classList.toggle('sidebar-collapsed');
      try { localStorage.setItem('sidebarCollapsed', body.classList.contains('sidebar-collapsed') ? '1' : '0'); } catch (e) {}
    }
  });
  document.getElementById('sidebarOverlay')?.addEventListener('click', () => body.classList.remove('sidebar-open'));

  // Auto-dismiss flash alerts
  document.querySelectorAll('.content > .alert-dismissible').forEach(el => {
    setTimeout(() => bootstrap.Alert.getOrCreateInstance(el).close(), 5000);
  });

  // Confirmation dialog for buttons with data-confirm
  let pendingForm = null;
  const okBtn = document.getElementById('confirmOk');
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-confirm]');
    if (!btn) return;
    e.preventDefault();
    pendingForm = btn.closest('form');
    document.getElementById('confirmMessage').textContent = btn.dataset.confirm;
    okBtn.className = 'btn ' + (btn.dataset.confirmClass || 'btn-danger');
    okBtn.textContent = btn.dataset.confirmLabel || 'Confirm';
    bootstrap.Modal.getOrCreateInstance('#confirmModal').show();
  });
  okBtn?.addEventListener('click', () => { if (pendingForm) pendingForm.submit(); });
});

/**
 * Reusable add/edit modal.
 * cfg: modalId, formId, titleId, addBtnId, editClass (buttons carry data-item JSON),
 *      fields[] (form element names to fill), defaults{}, titles[add, edit],
 *      onOpen(data, isEdit, form), reopen (data to re-open with after a validation error)
 */
window.setupEntityModal = function (cfg) {
  const form = document.getElementById(cfg.formId);
  const modal = new bootstrap.Modal(document.getElementById(cfg.modalId));
  function open(data) {
    form.reset();
    const isEdit = !!(data && data.id);
    cfg.fields.forEach(f => {
      const el = form.elements[f];
      if (el) el.value = (data && data[f] != null) ? data[f] : ((cfg.defaults || {})[f] ?? '');
    });
    document.getElementById(cfg.titleId).textContent = isEdit ? cfg.titles[1] : cfg.titles[0];
    if (cfg.onOpen) cfg.onOpen(data, isEdit, form);
    modal.show();
  }
  document.getElementById(cfg.addBtnId)?.addEventListener('click', () => open(null));
  document.querySelectorAll('.' + cfg.editClass).forEach(b =>
    b.addEventListener('click', () => open(JSON.parse(b.dataset.item))));
  if (cfg.reopen) open(cfg.reopen);
  return { open };
};
