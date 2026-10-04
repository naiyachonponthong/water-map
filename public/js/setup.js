/* Keep the current setup step visible in the compact horizontal navigation. */
(() => {
    const nav = document.querySelector('.setup-nav');
    const active = nav?.querySelector('[aria-current="step"]');
    if (!active) return;
    const reveal = () => {
        if (window.matchMedia('(max-width: 900px)').matches) {
            nav.scrollLeft = active.offsetLeft - nav.offsetLeft - (nav.clientWidth - active.offsetWidth) / 2;
        }
    };
    reveal();
    window.addEventListener('resize', reveal, { passive: true });
})();
