/* ศูนย์ช่วยเหลือน้ำท่วม : สคริปต์กลาง (ไม่พึ่ง build tool) */
(function () {
  'use strict';

  const $ = (s, el = document) => el.querySelector(s);
  const $$ = (s, el = document) => Array.from(el.querySelectorAll(s));

  /* ---------- ค้นหาเมนู Ctrl K ---------- */
  function initSearch() {
    const modalEl = $('#searchModal');
    if (!modalEl || !window.bootstrap) return;
    const modal = new bootstrap.Modal(modalEl);
    const input = $('.sm-input', modalEl);
    const list = $('.sm-list', modalEl);
    const items = window.__MENU || [];
    let active = 0;

    const render = () => {
      const q = input.value.trim().toLowerCase();
      const found = items.filter(i => !q || (i.label + ' ' + (i.group || '')).toLowerCase().includes(q));
      active = Math.min(active, Math.max(found.length - 1, 0));
      list.innerHTML = found.length
        ? found.map((i, n) => `<a class="sm-item${n === active ? ' active' : ''}" href="${i.url}"><span class="sm-ico"><i class="bi bi-${i.icon}"></i></span><span>${i.label}</span><span class="sm-group">${i.group || ''}</span></a>`).join('')
        : '<div class="empty py-4"><div class="em-title">ไม่พบเมนูที่ค้นหา</div></div>';
    };

    const open = () => { input.value = ''; active = 0; render(); modal.show(); setTimeout(() => input.focus(), 150); };
    $$('[data-open-search]').forEach(b => b.addEventListener('click', open));
    document.addEventListener('keydown', e => {
      if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); open(); }
    });
    input.addEventListener('input', () => { active = 0; render(); });
    input.addEventListener('keydown', e => {
      const links = $$('.sm-item', list);
      if (e.key === 'ArrowDown') { e.preventDefault(); active = Math.min(active + 1, links.length - 1); render(); }
      if (e.key === 'ArrowUp') { e.preventDefault(); active = Math.max(active - 1, 0); render(); }
      if (e.key === 'Enter' && links[active]) { e.preventDefault(); window.location = links[active].href; }
    });
  }

  /* ---------- modal เพิ่ม/แก้ไข แบบใช้ซ้ำ ----------
     ปุ่ม: data-form-modal="#userModal" data-action="/admin/users/5" data-method="PUT"
           data-title="แก้ไขผู้ใช้" data-fill='{"name":"..."}'
     ไม่มี data-fill = โหมดเพิ่ม (ล้างฟอร์ม) */
  function initFormModals() {
    document.addEventListener('click', e => {
      const btn = e.target.closest('[data-form-modal]');
      if (!btn) return;
      e.preventDefault();
      const modalEl = $(btn.dataset.formModal);
      if (!modalEl) return;
      const form = $('form', modalEl);
      form.reset();
      $$('.is-invalid', form).forEach(el => el.classList.remove('is-invalid'));
      form.action = btn.dataset.action || form.dataset.defaultAction || form.action;
      const actionField = $('input[name="_action"]', form);
      if (actionField) actionField.value = form.action;

      let method = $('input[name="_method"]', form);
      if (!method) { method = document.createElement('input'); method.type = 'hidden'; method.name = '_method'; form.prepend(method); }
      method.value = (btn.dataset.method || 'POST').toUpperCase();

      const title = $('.modal-title', modalEl);
      if (title && btn.dataset.title) title.textContent = btn.dataset.title;

      // ช่องที่แสดงเฉพาะตอนเพิ่ม/แก้ไข
      const isEdit = !!btn.dataset.fill;
      $$('[data-only="create"]', form).forEach(el => el.classList.toggle('d-none', isEdit));
      $$('[data-only="edit"]', form).forEach(el => el.classList.toggle('d-none', !isEdit));

      const data = btn.dataset.fill ? JSON.parse(btn.dataset.fill) : {};
      $$('[name]', form).forEach(el => {
        const key = el.name.replace(/\[\]$/, '');
        if (key.startsWith('_') || el.type === 'hidden') return;
        if (!(key in data)) {
          if (el.dataset.default !== undefined) setVal(el, el.dataset.default);
          return;
        }
        setVal(el, data[key]);
      });
      bootstrap.Modal.getOrCreateInstance(modalEl).show();
    });

    function setVal(el, v) {
      if (el.type === 'checkbox') el.checked = Array.isArray(v) ? v.map(String).includes(el.value) : (v === true || v === 1 || v === '1');
      else if (el.multiple && Array.isArray(v)) { [...el.options].forEach(o => (o.selected = v.map(String).includes(o.value))); if (el.tomselect) el.tomselect.setValue(v.map(String), true); }
      else if (el.type === 'radio') el.checked = String(el.value) === String(v);
      else if (el.type !== 'file') el.value = v ?? '';
      if (el.tomselect) el.tomselect.setValue(v ?? '', true);
      el.dispatchEvent(new Event('change', { bubbles: true }));
    }

    // เปิด modal เดิมซ้ำเมื่อ validate ไม่ผ่าน
    const reopen = document.body.dataset.reopenModal;
    if (reopen && $(reopen)) bootstrap.Modal.getOrCreateInstance($(reopen)).show();
  }

  /* ---------- ยืนยันก่อนส่งฟอร์ม ---------- */
  function initConfirm() {
    document.addEventListener('submit', e => {
      const form = e.target;
      if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) e.preventDefault();
      else if (!e.defaultPrevented) $$('button[type="submit"]', form).forEach(b => { b.disabled = true; setTimeout(() => (b.disabled = false), 4000); });
    });
  }

  /* ---------- dropdown ค้นหาได้ (Tom Select) ---------- */
  function initSelects(root = document) {
    if (!window.TomSelect) return;
    $$('select[data-search]:not(.tomselected)', root).forEach(el => {
      new TomSelect(el, {
        allowEmptyOption: true,
        maxOptions: 500,
        placeholder: el.dataset.placeholder || 'พิมพ์เพื่อค้นหา',
        render: { no_results: () => '<div class="no-results">ไม่พบข้อมูล</div>' },
        onFocus() { this.open(); },
      });
    });
  }

  /* ---------- flash ปิดเอง ---------- */
  function initFlash() {
    $$('.flash[data-autohide]').forEach(el => setTimeout(() => { el.style.transition = 'opacity .3s'; el.style.opacity = 0; setTimeout(() => el.remove(), 300); }, 5000));
    $$('.flash .btn-close').forEach(b => b.addEventListener('click', () => b.closest('.flash').remove()));
  }

  /* ---------- ติดตั้งแอป (PWA) ---------- */
  function initInstall() {
    let deferred = null;
    window.addEventListener('beforeinstallprompt', e => {
      e.preventDefault(); deferred = e;
      $$('[data-install-app]').forEach(b => b.classList.remove('d-none'));
    });
    $$('[data-install-app]').forEach(b => b.addEventListener('click', async () => {
      if (!deferred) return;
      deferred.prompt(); await deferred.userChoice; deferred = null; b.classList.add('d-none');
    }));
  }

  /* ---------- ปุ่มคัดลอก ---------- */
  function initCopy() {
    document.addEventListener('click', async e => {
      const b = e.target.closest('[data-copy]');
      if (!b) return;
      try { await navigator.clipboard.writeText(b.dataset.copy); const t = b.innerHTML; b.innerHTML = '<i class="bi bi-check2"></i> คัดลอกแล้ว'; setTimeout(() => (b.innerHTML = t), 1500); } catch (_) {}
    });
  }

  window.FloodUI = { initSelects };

  document.addEventListener('DOMContentLoaded', () => {
    initSearch(); initFormModals(); initConfirm(); initSelects(); initFlash(); initInstall(); initCopy();
  });
})();
