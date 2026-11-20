const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const zlib = require('node:zlib');
const { execFileSync } = require('node:child_process');
const { chromium } = require(process.env.PLAYWRIGHT_MODULE || 'playwright');

const root = path.resolve(__dirname, '../..');
const nonce = '0123456789abcdef0123456789abcdef';
const start = new Date('2026-01-01T00:00:00Z');
const php = String.raw`
require $argv[1] . '/tests/bootstrap.php';
\App\Localization::setLocale($argv[2]);
$view = new \App\Core\View($argv[1] . '/views');
$view->setCspNonce('0123456789abcdef0123456789abcdef');
echo $view->render('static/index', array_merge([
    'appName' => 'Competizioni Judo',
    'locale' => $argv[2],
    'isLoggedIn' => false,
    'isAdmin' => false,
    'clubEmail' => null,
    'privacyControllerName' => 'Synthetic Judo Club',
    'privacyControllerAddress' => 'Synthetic address',
    'privacyControllerEmail' => 'privacy@example.test',
    'privacyControllerFiscalCode' => 'SYNTHETIC-TEST-CODE',
], \App\Presentation\Navigation::context('/', '', false, false)));
`;

function render(locale, prefix) {
    return execFileSync(process.env.PHP_BINARY || 'php', ['-r', php, root, locale], {
        env: { ...process.env, APP_ENV: 'testing', APP_URL: `https://judoka.test${prefix}` },
        encoding: 'utf8',
    });
}

(async () => {
    const browser = await chromium.launch({
        executablePath: process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE || '/usr/bin/chromium',
        headless: true,
        args: ['--no-sandbox'],
    });
    const errors = [];

    async function fixture({ locale = 'en', prefix = '', width = 1440, height = 900, mobile = false, reduced = false, dark = false, random = .5 } = {}) {
        const context = await browser.newContext({
            viewport: { width, height }, isMobile: mobile, hasTouch: mobile,
            reducedMotion: reduced ? 'reduce' : 'no-preference',
        });
        const page = await context.newPage();
        page.on('pageerror', error => errors.push(error.message));
        await page.addInitScript(({ random, dark }) => {
            Math.random = () => random;
            if (dark) localStorage.setItem('competizioni-judo-theme', 'dark');
        }, { random, dark });
        await page.clock.install({ time: start });
        await page.clock.pauseAt(new Date(start.getTime() + 60000));
        let images = 0;
        await page.route('https://judoka.test/**', route => {
            const pathname = new URL(route.request().url()).pathname.replace(/^\/prod(?=\/)/, '');
            if (pathname.startsWith('/assets/')) {
                const file = path.join(root, 'public', pathname);
                assert.ok(file.startsWith(path.join(root, 'public/assets/')));
                if (pathname.endsWith('/judoka-greet-spar.png')) images++;
                const extension = path.extname(file);
                const types = { '.js': 'text/javascript', '.css': 'text/css', '.png': 'image/png', '.jpg': 'image/jpeg', '.webp': 'image/webp', '.svg': 'image/svg+xml', '.svgz': 'image/svg+xml' };
                const body = extension === '.svgz' ? zlib.gunzipSync(fs.readFileSync(file)) : fs.readFileSync(file);
                return route.fulfill({ contentType: types[extension], body });
            }
            return route.fulfill({
                contentType: 'text/html', body: render(locale, prefix),
                headers: { 'Content-Security-Policy': `default-src 'self'; script-src 'self' 'nonce-${nonce}'; style-src 'self' 'nonce-${nonce}'; img-src 'self' data:; object-src 'none'; base-uri 'self'` },
            });
        });
        await page.goto(`https://judoka.test${prefix}/`);
        return {
            context, page, stage: page.locator('[data-judoka-animation]'),
            button: page.locator('#judoka-trigger'), images: () => images,
        };
    }

    async function reach(page, stage, phase, limit = 10000) {
        for (let elapsed = 0; elapsed < limit && await stage.getAttribute('data-state') !== phase; elapsed += 40) {
            await page.clock.runFor(40);
        }
        assert.equal(await stage.getAttribute('data-state'), phase);
    }

    try {
        for (const options of [
            { locale: 'en', width: 1440, height: 900 },
            { locale: 'it', prefix: '/prod', width: 390, height: 844, mobile: true },
            { locale: 'en', width: 844, height: 390, mobile: true, random: .25 },
            { locale: 'it', prefix: '/prod', width: 320, height: 640, mobile: true, dark: true },
        ]) {
            const { context, page, stage, button, images } = await fixture(options);
            assert.equal(images(), 0, 'The sprite must load lazily');
            if (options.dark) assert.equal(await page.locator('html').getAttribute('data-theme'), 'dark');
            assert.equal(await stage.isVisible(), false);
            assert.equal(await button.getAttribute('aria-pressed'), 'false');
            const controls = await button.evaluate(node => ({
                neighbor: node.previousElementSibling?.classList.contains('lang-switch'),
                button: node.getBoundingClientRect().toJSON(),
                selector: document.querySelector('#locale-select').getBoundingClientRect().toJSON(),
                theme: document.getElementById('theme-toggle').getBoundingClientRect().toJSON(),
                width: document.documentElement.scrollWidth,
            }));
            assert.ok(controls.neighbor, 'The button must sit immediately beside the language selector');
            assert.ok(Math.abs(controls.button.width - controls.theme.width) < 1, 'The mascot and theme buttons must have the same width');
            assert.ok(Math.abs(controls.button.height - controls.theme.height) < 1, 'The mascot and theme buttons must have the same height');
            assert.ok(controls.button.right <= options.width && controls.button.left >= 0, 'Header control must fit the mobile viewport');
            assert.ok(controls.button.left >= controls.selector.right, 'The animation control must not overlap the selector');
            assert.ok(controls.width <= options.width, 'The page must not scroll horizontally');
            if (options.mobile) await button.tap();
            else { await button.focus(); await page.keyboard.press('Enter'); }
            await stage.waitFor({ state: 'visible' });
            assert.equal(await button.getAttribute('aria-pressed'), 'true');
            assert.match(await button.getAttribute('aria-label'), options.locale === 'it' ? /Ferma/ : /Stop/);
            assert.equal(await stage.getAttribute('aria-hidden'), 'true');
            assert.equal(await stage.evaluate(node => getComputedStyle(node).pointerEvents), 'none');
            assert.ok((await stage.getAttribute('data-sprite-url')).startsWith(`${options.prefix || ''}/assets/`));
            const entry = await stage.getAttribute('data-entry');
            await reach(page, stage, 'greeting');
            const pose = () => page.locator('.judoka-animation__sprite').evaluate(node => parseFloat(node.style.backgroundPosition));
            await page.clock.runFor(250);
            assert.ok(Math.abs(await pose() - 100 / 3) < .01, 'The first greeting must include a bow');
            await page.clock.runFor(500);
            assert.equal(await stage.getAttribute('data-state'), 'sparring');
            const before = await pose();
            await page.clock.runFor(160);
            assert.notEqual(await pose(), before, 'Sparring must alternate the grip poses');
            if (process.env.JUDOKA_SCREENSHOTS) {
                fs.mkdirSync(process.env.JUDOKA_SCREENSHOTS, { recursive: true });
                await page.screenshot({ path: path.join(process.env.JUDOKA_SCREENSHOTS, `judoka-${options.width}.png`), fullPage: true });
            }
            // Rotation or a responsive resize must keep the pair centered.
            const originalViewport = page.viewportSize();
            await page.setViewportSize({ width: originalViewport.height, height: originalViewport.width });
            await page.clock.runFor(20);
            let centered;
            // Native viewport/ResizeObserver notifications are asynchronous even
            // when the animation's JavaScript clock is paused for the test.
            for (let attempt = 0; attempt < 50; attempt++) {
                centered = await page.locator('.judoka-animation__walker').evaluate(node => ({
                    center: node.getBoundingClientRect().left + node.getBoundingClientRect().width / 2,
                    expected: innerWidth / 2,
                    stageWidth: node.parentElement.clientWidth,
                    phase: node.parentElement.dataset.state,
                }));
                if (Math.abs(centered.center - centered.expected) < 2) break;
                await new Promise(resolve => setTimeout(resolve, 20));
            }
            assert.ok(Math.abs(centered.center - centered.expected) < 2, `Rotation must preserve centering: ${JSON.stringify(centered)}`);
            await page.setViewportSize(originalViewport);
            await page.clock.runFor(1980);
            assert.equal(await stage.getAttribute('data-state'), 'farewell');
            await page.clock.runFor(250);
            assert.ok(Math.abs(await pose() - 100 / 3) < .01, 'The second greeting must include another bow');
            await page.clock.runFor(500);
            assert.equal(await stage.getAttribute('data-state'), 'returning');
            const expected = entry === 'left' ? -1 : 1;
            assert.equal(await page.locator('.judoka-animation__sprite').evaluate(node => node.style.transform), `scaleX(${expected})`);
            await page.clock.runFor(10000);
            assert.equal(await stage.isVisible(), false);
            assert.equal(await button.getAttribute('aria-pressed'), 'false');
            assert.match(await button.getAttribute('aria-label'), options.locale === 'it' ? /Avvia/ : /Play/);
            assert.equal(images(), 1);
            await context.close();
        }

        // Deterministic random midpoint gives a two-minute automatic pause.
        const scheduled = await fixture();
        await scheduled.page.clock.runFor(118000);
        assert.equal(scheduled.images(), 0);
        await scheduled.page.clock.runFor(2000);
        await scheduled.stage.waitFor({ state: 'visible' });
        await scheduled.button.click();
        assert.equal(await scheduled.stage.isVisible(), false, 'The button must stop an automatic visit');
        await scheduled.page.clock.runFor(119000);
        assert.equal(await scheduled.stage.isVisible(), false);
        await scheduled.page.clock.runFor(2000);
        await scheduled.stage.waitFor({ state: 'visible' });
        await scheduled.page.keyboard.press('Escape');
        assert.equal(await scheduled.stage.isVisible(), false);
        await scheduled.page.evaluate(() => {
            Object.defineProperty(document, 'hidden', { configurable: true, value: true });
            document.dispatchEvent(new Event('visibilitychange'));
        });
        await scheduled.page.clock.runFor(300000);
        assert.equal(await scheduled.stage.isVisible(), false);
        await scheduled.page.evaluate(() => {
            Object.defineProperty(document, 'hidden', { configurable: true, value: false });
            document.dispatchEvent(new Event('visibilitychange'));
        });
        await scheduled.page.clock.runFor(118000);
        assert.equal(await scheduled.stage.isVisible(), false);
        await scheduled.page.clock.runFor(3000);
        await scheduled.stage.waitFor({ state: 'visible' });
        await scheduled.context.close();

        const reduced = await fixture({ reduced: true, mobile: true, width: 390, height: 844 });
        await reduced.page.clock.runFor(300000);
        assert.equal(reduced.images(), 0, 'Reduced motion must suppress automatic visits');
        await reduced.button.tap();
        await reduced.stage.waitFor({ state: 'visible' });
        assert.equal(await reduced.stage.getAttribute('data-state'), 'greeting');
        const stationaryX = await reduced.page.locator('.judoka-animation__walker').evaluate(node => node.getBoundingClientRect().left);
        await reduced.page.clock.runFor(1000);
        assert.equal(await reduced.page.locator('.judoka-animation__walker').evaluate(node => node.getBoundingClientRect().left), stationaryX);
        await reduced.page.clock.runFor(2600);
        assert.equal(await reduced.stage.isVisible(), false);
        await reduced.context.close();

        const loading = await fixture();
        let deliver;
        await loading.page.route('**/judoka-greet-spar.png?*', route => new Promise(resolve => {
            deliver = async () => {
                await route.fulfill({ contentType: 'image/png', body: fs.readFileSync(path.join(root, 'public/assets/judoka-greet-spar.png')) });
                resolve();
            };
        }));
        await loading.button.click();
        await loading.page.waitForFunction(() => document.getElementById('judoka-trigger').getAttribute('aria-pressed') === 'true');
        while (!deliver) await new Promise(resolve => setTimeout(resolve, 10));
        await loading.button.click();
        await deliver();
        await loading.page.clock.runFor(1000);
        assert.equal(await loading.stage.isVisible(), false, 'A cancelled pending load must not start later');
        await loading.button.click();
        await loading.stage.waitFor({ state: 'visible' });
        await loading.context.close();

        assert.deepEqual(errors, []);
        console.log('Judoka browser checks passed: desktop/mobile, locales, prefixed URLs, ordered routine, return, touch/keyboard controls, random scheduling, visibility, resize, reduced motion, and lazy-load cancellation.');
    } finally {
        await browser.close();
    }
})().catch(error => { console.error(error); process.exitCode = 1; });
