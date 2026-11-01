export function mountAmbient() {
    const root = document.querySelector('.ambient');
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    const pointer = window.matchMedia('(hover: hover) and (pointer: fine)');
    let stop = () => {};
    function configure() {
        stop();
        root.removeAttribute('style');
        if (motion.matches || !pointer.matches) return;
        const controller = new AbortController();
        let frame = 0;
        let current = { x: innerWidth / 2, y: 80, opacity: 0 };
        let target = { ...current };
        let lastTime = 0;
        function animate(time) {
            const easing = 1 - Math.exp(-Math.min(time - lastTime || 16, 64) / 110);
            lastTime = time;
            for (const key of ['x', 'y', 'opacity']) current[key] += (target[key] - current[key]) * easing;
            root.style.setProperty('--cursor-x', `${current.x}px`);
            root.style.setProperty('--cursor-y', `${current.y}px`);
            root.style.setProperty('--glow-opacity', current.opacity);
            root.style.setProperty('--shift-x', `${(current.x / innerWidth - .5) * 24}px`);
            root.style.setProperty('--shift-y', `${(current.y / innerHeight - .5) * 24}px`);
            frame = Object.keys(current).some(key => Math.abs(current[key] - target[key]) > .01) ? requestAnimationFrame(animate) : 0;
        }
        function start() { if (!frame) { lastTime = 0; frame = requestAnimationFrame(animate); } }
        document.addEventListener('pointermove', event => {
            if (event.pointerType !== 'mouse') return;
            target = { x: event.clientX, y: event.clientY, opacity: event.target.closest('.map-stage') ? 0 : 1 };
            start();
        }, { passive: true, signal: controller.signal });
        document.documentElement.addEventListener('pointerleave', () => { target.opacity = 0; start(); }, { signal: controller.signal });
        window.addEventListener('blur', () => { target.opacity = 0; start(); }, { signal: controller.signal });
        stop = () => { controller.abort(); cancelAnimationFrame(frame); };
    }
    motion.addEventListener('change', configure);
    pointer.addEventListener('change', configure);
    configure();
    return () => { stop(); motion.removeEventListener('change', configure); pointer.removeEventListener('change', configure); };
}
