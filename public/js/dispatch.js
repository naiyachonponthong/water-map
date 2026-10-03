/* กระดานสั่งการ: ลากการ์ดตามขั้นตอน, เมนูบนการ์ด, รีเฟรชอัตโนมัติ */
(function () {
  'use strict';
  const cfg = window.DISPATCH;
  const wrap = document.getElementById('boardWrap');
  if (!cfg) return;
  const url = (tpl, id) => tpl.replace('__ID__', id);
  let busy = false;

  /* ---------- ส่งฟอร์มการกระทำ ---------- */
  const actForm = document.getElementById('actForm');
  function post(action, fields = {}) {
    actForm.action = action;
    actForm.querySelector('[name=accept]').value = fields.accept ?? '';
    actForm.querySelector('[name=step]').value = fields.step ?? '';
    actForm.querySelector('[name=accept]').disabled = fields.accept === undefined;
    actForm.querySelector('[name=step]').disabled = fields.step === undefined;
    actForm.submit();
  }

  async function openAssign(caseId) {
    const body = document.getElementById('assignBody');
    body.innerHTML = '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm"></span> กำลังหาทีมที่เหมาะ...</div>';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('assignModal')).show();
    const res = await fetch(url(cfg.suggestUrl, caseId), { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
    body.innerHTML = res.ok ? await res.text() : '<div class="text-danger">โหลดรายชื่อทีมไม่ได้</div>';
  }

  function openComplete(aId, people, code) {
    const f = document.getElementById('completeForm');
    f.action = url(cfg.completeUrl, aId);
    f.people_rescued.value = people || '';
    document.getElementById('completeCode').textContent = code || '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('completeModal')).show();
  }

  function openReason(aId, kind) {
    const f = document.getElementById('reasonForm');
    const decline = kind === 'decline';
    f.action = url(decline ? cfg.respondUrl : cfg.cancelUrl, aId);
    f.accept.disabled = !decline;
    document.getElementById('reasonSelect').classList.toggle('d-none', !decline);
    document.getElementById('reasonSelect').disabled = !decline;
    const note = document.getElementById('reasonNote');
    note.name = decline ? 'note' : 'reason';
    document.getElementById('reasonTitle').textContent = decline ? 'ทีมปฏิเสธงาน' : 'ยกเลิกงาน คืนเคสเข้าคิว';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('reasonModal')).show();
  }

  document.addEventListener('click', e => {
    const assign = e.target.closest('[data-assign]');
    if (assign) return openAssign(assign.dataset.assign);
    const done = e.target.closest('[data-complete]');
    if (done) return openComplete(done.dataset.complete, done.dataset.people, done.dataset.code);
    const act = e.target.closest('[data-act]');
    if (!act) return;
    const id = act.dataset.a;
    if (act.dataset.act === 'respond') {
      if (act.dataset.accept === '1') post(url(cfg.respondUrl, id), { accept: 1 });
      else openReason(id, 'decline');
    } else if (act.dataset.act === 'progress') post(url(cfg.progressUrl, id), { step: act.dataset.step });
    else if (act.dataset.act === 'cancel') openReason(id, 'cancel');
  });

  /* ---------- ลากการ์ด ---------- */
  // จากคอลัมน์ => ไปคอลัมน์ => การกระทำ
  const moves = {
    'queued>offered': c => openAssign(c.dataset.case),
    'queued>accepted': c => openAssign(c.dataset.case),
    'offered>accepted': c => post(url(cfg.respondUrl, c.dataset.assignment), { accept: 1 }),
    'offered>queued': c => openReason(c.dataset.assignment, 'decline'),
    'accepted>en_route': c => post(url(cfg.progressUrl, c.dataset.assignment), { step: 'en_route' }),
    'accepted>on_site': c => post(url(cfg.progressUrl, c.dataset.assignment), { step: 'on_site' }),
    'en_route>on_site': c => post(url(cfg.progressUrl, c.dataset.assignment), { step: 'on_site' }),
    'accepted>rescued': c => openComplete(c.dataset.assignment, '', c.dataset.code),
    'en_route>rescued': c => openComplete(c.dataset.assignment, '', c.dataset.code),
    'on_site>rescued': c => openComplete(c.dataset.assignment, '', c.dataset.code),
  };

  function initSortable() {
    if (!window.Sortable) return;
    document.querySelectorAll('.kb-list').forEach(list => {
      Sortable.create(list, {
        group: 'kb', animation: 150, sort: false, delay: 120, delayOnTouchOnly: true,
        filter: '.dropdown, a, button', preventOnFilter: false,
        onStart: () => { busy = true; },
        onEnd: evt => {
          busy = false;
          const from = evt.from.dataset.col, to = evt.to.dataset.col;
          if (from === to) return;
          const fn = moves[`${from}>${to}`];
          // ย้ายกลับก่อน แล้วค่อยให้เซิร์ฟเวอร์ยืนยัน (กระดานโหลดใหม่หลังส่ง)
          evt.from.insertBefore(evt.item, evt.from.children[evt.oldIndex] || null);
          if (fn) fn(evt.item);
          else toast('ย้ายข้ามขั้นแบบนี้ไม่ได้ ใช้เมนู ... บนการ์ด');
        },
      });
    });
  }

  function toast(msg) {
    const wrapEl = document.querySelector('.flash-wrap');
    if (!wrapEl) return;
    const el = document.createElement('div');
    el.className = 'flash error';
    el.innerHTML = `<i class="bi bi-exclamation-circle-fill"></i><div class="flex-grow-1">${msg}</div>`;
    wrapEl.appendChild(el);
    setTimeout(() => el.remove(), 3000);
  }

  /* ---------- รีเฟรชกระดาน ---------- */
  async function refresh() {
    if (!wrap || busy || document.querySelector('.modal.show') || document.querySelector('.kb-card .dropdown-menu.show') || document.visibilityState !== 'visible') return;
    try {
      const res = await fetch(wrap.dataset.url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
      if (!res.ok) return;
      wrap.innerHTML = await res.text();
      const triage = wrap.querySelector('#kanban')?.dataset.triage;
      if (triage !== undefined) document.getElementById('triageBadge').textContent = triage;
      initSortable();
    } catch (_) {}
  }

  if (wrap) {
    initSortable();
    setInterval(refresh, 20000);
    // realtime: เคสหรือทีมเปลี่ยน รีเฟรชกระดานทันที (ถ้าไม่ได้ลากหรือเปิดเมนูอยู่)
    if (window.FloodLive && cfg.provinceId) {
      let t = null;
      const soon = () => { clearTimeout(t); t = setTimeout(refresh, 700); };
      FloodLive.channel('province.' + cfg.provinceId).on('case.changed', soon).on('team.changed', soon);
    }
  }
})();
