/* Progressive enhancement: useful content remains visible without JavaScript. */
(() => {
  'use strict';
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  document.querySelector('[data-home-province]')?.addEventListener('change', e => window.location.assign(e.target.value));
  if (!reduced && 'IntersectionObserver' in window) {
    const items = document.querySelectorAll('[data-reveal], .fh-content-grid > .card');
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.06 });
    items.forEach((item, index) => {
      item.setAttribute('data-reveal', '');
      item.style.transitionDelay = `${Math.min(index % 3, 2) * 65}ms`;
      observer.observe(item);
    });
    document.body.classList.add('fh-motion');
  }
  const search = document.getElementById('provinceSearch');
  if (search) {
    const regions = document.querySelectorAll('[data-region]');
    const empty = document.getElementById('provinceNoResults');
    const filter = () => {
      const query = search.value.trim();
      let count = 0;
      regions.forEach(region => {
        let visible = 0;
        region.querySelectorAll('[data-pname]').forEach(link => {
          link.hidden = !link.dataset.pname.includes(query);
          if (!link.hidden) visible++;
        });
        region.hidden = !visible;
        count += visible;
      });
      empty.hidden = count > 0;
    };
    search.addEventListener('input', filter);
    filter();
  }
})();
