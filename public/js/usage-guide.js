(function (root) {
  'use strict';
  const normalize = value => String(value || '').normalize('NFC').toLocaleLowerCase('th-TH').replace(/\s+/g, ' ').trim();
  const matches = (text, query, audience, selected) => (selected === 'all' || audience === selected) && normalize(query).split(' ').filter(Boolean).every(word => normalize(text).includes(word));
  function init(document, window) {
    if (!document.getElementById('usageGuide')) return;
    const $ = id => document.getElementById(id);
    const topics = [...document.querySelectorAll('[data-guide-section]')], buttons = [...document.querySelectorAll('[data-guide-audience]')];
    let audience = 'all';
    function filter() {
      let count = 0;
      topics.forEach(topic => {
        const visible = matches(topic.textContent, $('guideSearch').value, topic.dataset.audience, audience);
        topic.hidden = !visible;
        const link = document.querySelector(`[data-guide-toc="${topic.id}"]`); if (link) link.hidden = !visible;
        if (visible) { count++; if ($('guideSearch').value.trim()) topic.querySelector('details').open = true; }
      });
      $('guideCount').textContent = `${count} หัวข้อ`;
      $('guideEmpty').hidden = count !== 0;
    }
    buttons.forEach(button => button.addEventListener('click', () => {
      audience = button.dataset.guideAudience;
      buttons.forEach(b => b.setAttribute('aria-pressed', String(b === button))); filter();
    }));
    $('guideSearch').addEventListener('input', filter);
    $('guideReset').addEventListener('click', () => { $('guideSearch').value = ''; audience = 'all'; buttons.forEach(b => b.setAttribute('aria-pressed', String(b.dataset.guideAudience === 'all'))); filter(); $('guideSearch').focus(); });
    $('guideExpand').addEventListener('click', () => {
      const visible = topics.filter(t => !t.hidden), expand = visible.some(t => !t.querySelector('details').open);
      visible.forEach(t => t.querySelector('details').open = expand);
      $('guideExpand').textContent = expand ? 'ย่อรายละเอียดทั้งหมด' : 'เปิดรายละเอียดทั้งหมด';
    });
    function revealHash() {
      const id = window.location.hash.slice(1), topic = topics.find(t => t.id === id); if (!topic) return;
      if (topic.hidden) { $('guideSearch').value = ''; audience = 'all'; buttons.forEach(b => b.setAttribute('aria-pressed', String(b.dataset.guideAudience === 'all'))); filter(); }
      topic.querySelector('details').open = true;
    }
    window.addEventListener('hashchange', revealHash); revealHash();
    let printState = null;
    window.addEventListener('beforeprint', () => { printState = topics.map(t => ({topic:t,hidden:t.hidden,open:t.querySelector('details').open})); topics.forEach(t => { t.hidden = false; t.querySelector('details').open = true; }); });
    window.addEventListener('afterprint', () => { printState?.forEach(s => { s.topic.hidden = s.hidden; s.topic.querySelector('details').open = s.open; }); printState = null; });
    $('guidePrint').addEventListener('click', () => window.print());
  }
  root.FloodUsageGuide = { matches, init };
  if (typeof module !== 'undefined') module.exports = root.FloodUsageGuide;
  if (typeof document !== 'undefined') init(document, root);
})(typeof window !== 'undefined' ? window : globalThis);
