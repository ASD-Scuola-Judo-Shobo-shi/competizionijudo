# Judoka animation

The header judogi button sits immediately beside the language selector on all
pages using the application layout. It supports mouse, touch, Enter and Space;
while active, the same button or Escape stops the animation. Labels are localized
in English and Italian. The animation does not receive focus or pointer input.

The two equal-height judoka enter from a random edge, bow to each other, briefly
spar using standing grips, bow again, and walk back to that edge. The stationary
routine takes 3.5 seconds: 20% greeting, 60% sparring, 20% farewell. Only one visit
can run at a time. An automatic visit is scheduled after a random 90–150 second
pause, including after a manual visit or cancellation.

Automatic visits pause while the page is hidden or reduced motion is enabled.
A manual visit with reduced motion uses the same short stationary routine,
without walking across the screen. Resizing or rotating the screen keeps an
active visit centered within the new viewport. The overlay respects the device's
bottom safe area and is excluded from printing.

`public/assets/judoka-greet-spar.png` is the organizer-approved transparent
Tatamatic sprite sheet generated from the supplied judo illustration using the
built-in image generation tool. It is copied unchanged, including the optional
`TatamaticRoutine=greet-spar` PNG metadata. Its grid has four columns and three
rows, with 362 × 362 square frames. Frames 1–8 walk right, frame 9 stands facing
the partner, frame 10 bows, and frames 11–12 are complementary sparring poses.

The browser script is `public/assets/js/judoka-animation.js`; styles belong to
the shared stylesheet. Both script and sprite URLs use the existing asset helper
for content hashes and installations below a URL prefix such as `/prod`.
The sprite is fetched lazily on the first requested visit. No additional runtime
library, build step, server endpoint, database change, or external host is needed.

Browser regression coverage is in `tests/browser/judoka-animation.cjs` and can be
run with Node and Playwright installed. Set `PLAYWRIGHT_MODULE` to an existing
Playwright module path and `PLAYWRIGHT_CHROMIUM_EXECUTABLE` when Chromium is not
available at `/usr/bin/chromium`. The test uses synthetic rendered pages and
verifies desktop, phone portrait/landscape, both locales, prefixed paths,
choreography, scheduling, cancellation, visibility, and reduced motion.
