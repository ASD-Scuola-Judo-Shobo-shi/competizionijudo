(() => {
    'use strict';

    const stage = document.querySelector('[data-judoka-animation]');
    const trigger = document.getElementById('judoka-trigger');
    if (!stage || !trigger) return;

    const walker = stage.querySelector('.judoka-animation__walker');
    const sprite = stage.querySelector('.judoka-animation__sprite');
    const motion = matchMedia('(prefers-reduced-motion: reduce)');
    const routineDuration = 3500;
    let timer, animation, imagePromise, run;
    let pending = false;
    let generation = 0;

    function buttonState(playing) {
        trigger.setAttribute('aria-pressed', String(playing));
        const label = playing ? trigger.dataset.stopLabel : trigger.dataset.playLabel;
        trigger.setAttribute('aria-label', label);
        trigger.title = label;
    }

    function schedule() {
        clearTimeout(timer);
        if (!document.hidden && !motion.matches) {
            timer = setTimeout(() => play(false), 90000 + Math.random() * 60000);
        }
    }

    function stop(reschedule = true) {
        generation++;
        clearTimeout(timer);
        cancelAnimationFrame(animation);
        pending = false;
        run = null;
        stage.hidden = true;
        stage.dataset.state = 'idle';
        buttonState(false);
        if (reschedule) schedule();
    }

    function loadSprite() {
        if (!imagePromise) {
            imagePromise = new Promise((resolve, reject) => {
                const image = new Image();
                image.onload = resolve;
                image.onerror = reject;
                image.src = stage.dataset.spriteUrl;
            });
        }
        return imagePromise;
    }

    async function play(manual) {
        if (run || pending || document.hidden || (!manual && motion.matches)) return;
        clearTimeout(timer);
        const request = ++generation;
        pending = true;
        buttonState(true);
        try {
            await loadSprite();
        } catch (_) {
            imagePromise = null;
            if (request === generation) stop();
            return;
        }
        if (request !== generation) return;
        pending = false;
        if (document.hidden) {
            stop(false);
            return;
        }

        stage.hidden = false;
        const width = stage.clientWidth;
        const size = walker.getBoundingClientRect().width;
        const direction = Math.random() < .5 ? 1 : -1;
        const now = performance.now();
        const stationary = motion.matches;
        run = {
            width, size, direction, stationary,
            x: stationary ? (width - size) / 2 : direction === 1 ? -size : width,
            target: (width - size) / 2,
            phase: stationary ? 'greeting' : 'approaching',
            phaseStart: now,
            lastTick: now,
            frame: -1,
        };
        stage.dataset.entry = direction === 1 ? 'left' : 'right';
        sprite.style.backgroundImage = `url("${stage.dataset.spriteUrl}")`;
        sprite.style.transform = `scaleX(${direction})`;
        tick(now);
    }

    function tick(now) {
        if (!run) return;
        const delta = Math.min((now - run.lastTick) / 1000, .1);
        run.lastTick = now;
        let frame;

        if (run.phase === 'approaching' || run.phase === 'returning') {
            run.x += run.direction * Math.max(90, run.width / 8) * delta;
            if (run.phase === 'approaching' && run.direction * (run.target - run.x) <= 0) {
                run.x = run.target;
                run.phase = 'greeting';
                run.phaseStart = now;
            } else if (run.phase === 'returning' && (run.direction === 1 ? run.x > run.width : run.x + run.size < 0)) {
                stop();
                return;
            }
            frame = Math.floor((now - run.phaseStart) / 67) % 8;
        }

        if (run.phase !== 'approaching' && run.phase !== 'returning') {
            const progress = (now - run.phaseStart) / routineDuration;
            if (progress >= 1) {
                if (run.stationary) {
                    stop();
                    return;
                }
                run.phase = 'returning';
                run.phaseStart = now;
                run.direction *= -1;
                sprite.style.transform = `scaleX(${run.direction})`;
                frame = 0;
            } else if (progress < .2 || progress >= .8) {
                run.phase = progress < .2 ? 'greeting' : 'farewell';
                const bowProgress = (progress < .2 ? progress : progress - .8) / .2;
                frame = [8, 9, 8][Math.min(2, Math.floor(bowProgress * 3))];
            } else {
                run.phase = 'sparring';
                frame = 10 + Math.floor((now - run.phaseStart) / 160) % 2;
            }
        }

        if (frame !== run.frame) {
            sprite.style.backgroundPosition = `${frame % 4 * 100 / 3}% ${Math.floor(frame / 4) * 50}%`;
            run.frame = frame;
        }
        stage.dataset.state = run.phase;
        walker.style.transform = `translate3d(${run.x}px,0,0)`;
        animation = requestAnimationFrame(tick);
    }

    trigger.addEventListener('click', () => run || pending ? stop() : play(true));
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && (run || pending)) stop();
    });
    document.addEventListener('visibilitychange', () => document.hidden ? stop(false) : schedule());
    motion.addEventListener('change', () => stop());
    window.addEventListener('pagehide', () => stop(false));
    window.addEventListener('pageshow', event => { if (event.persisted) schedule(); });
    function reflow() {
        if (!run) return;
        const width = stage.clientWidth;
        const size = walker.getBoundingClientRect().width;
        run.x = (run.x + run.size / 2) / run.width * width - size / 2;
        run.width = width;
        run.size = size;
        run.target = (width - size) / 2;
        if (run.phase !== 'approaching' && run.phase !== 'returning') run.x = run.target;
        walker.style.transform = `translate3d(${run.x}px,0,0)`;
    }
    window.addEventListener('resize', reflow);
    if (typeof ResizeObserver === 'function') new ResizeObserver(reflow).observe(stage);
    schedule();
})();
