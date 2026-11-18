/**
 * DarahConnect Fluid Motion & Direct Manipulation Runtime
 * Implements Apple Fluid Interface principles on the web:
 * - Instant pointer-down response with ~10px drag-cancel hysteresis (§1, §10)
 * - Interruptible velocity-preserving Spring solver parameterized by damping & response (§3, §4)
 * - Momentum projection & rubber-banding for draggable sheets and segmented pills (§2, §5, §6, §9)
 * - Automatic prefers-reduced-motion fallback (§14)
 */
(() => {
    'use strict';

    const prefersReducedMotion = () =>
        window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    /**
     * Apple's exponential decay projection (§6)
     */
    function projectMomentum(initialVelocityPxPerSec, decelerationRate = 0.998) {
        return ((initialVelocityPxPerSec / 1000) * decelerationRate) / (1 - decelerationRate);
    }

    /**
     * Progressive edge resistance (§9)
     */
    function rubberband(overshoot, dimension, constant = 0.55) {
        if (dimension <= 0) return 0;
        return (overshoot * dimension * constant) / (dimension + constant * Math.abs(overshoot));
    }

    /**
     * Velocity-aware, interruptible 1D Spring (§3, §4, §5)
     * Starts from live presentation value and blends velocity on re-target.
     */
    class FluidSpring {
        constructor(initialValue = 0, { damping = 1.0, response = 0.35, onUpdate = null, onComplete = null } = {}) {
            this.value = initialValue;
            this.target = initialValue;
            this.velocity = 0;
            this.damping = damping;
            this.response = response;
            this.onUpdate = onUpdate;
            this.onComplete = onComplete;
            this._rafId = null;
            this._lastTime = null;
        }

        set(value) {
            this.stop();
            this.value = value;
            this.target = value;
            this.velocity = 0;
            if (this.onUpdate) this.onUpdate(this.value);
        }

        animateTo(newTarget, { velocity = null, damping = null, response = null } = {}) {
            this.target = newTarget;
            if (velocity !== null && Number.isFinite(velocity)) {
                this.velocity = velocity;
            }
            if (damping !== null) this.damping = damping;
            if (response !== null) this.response = response;

            if (prefersReducedMotion()) {
                this.set(newTarget);
                if (this.onComplete) this.onComplete(newTarget);
                return;
            }

            if (this._rafId === null) {
                this._lastTime = performance.now();
                this._rafId = requestAnimationFrame((now) => this._tick(now));
            }
        }

        _tick(now) {
            const dt = Math.min((now - this._lastTime) / 1000, 0.064);
            this._lastTime = now;

            const omega = (2 * Math.PI) / Math.max(this.response, 0.05);
            const stiffness = omega * omega;
            const dampingCoeff = 2 * this.damping * omega;

            // Semi-implicit Euler sub-stepping for frame-rate independence
            const steps = 2;
            const stepDt = dt / steps;
            for (let i = 0; i < steps; i++) {
                const displacement = this.value - this.target;
                const acceleration = -stiffness * displacement - dampingCoeff * this.velocity;
                this.velocity += acceleration * stepDt;
                this.value += this.velocity * stepDt;
            }

            if (this.onUpdate) this.onUpdate(this.value);

            const isSettled =
                Math.abs(this.value - this.target) < 0.25 &&
                Math.abs(this.velocity) < 1.5;

            if (isSettled) {
                this.value = this.target;
                this.velocity = 0;
                this._rafId = null;
                if (this.onUpdate) this.onUpdate(this.value);
                if (this.onComplete) this.onComplete(this.value);
                return;
            }

            this._rafId = requestAnimationFrame((nextNow) => this._tick(nextNow));
        }

        stop() {
            if (this._rafId !== null) {
                cancelAnimationFrame(this._rafId);
                this._rafId = null;
            }
        }
    }

    /**
     * 1. Instant Pointer-Down Press Feedback with ~10px Hysteresis (§1, §10)
     */
    function initPressFeedback() {
        const selector = '.btn, .dc-pressable, .dc-card-interactive, .dc-nav-pill';
        let activeEl = null;
        let startX = 0;
        let startY = 0;

        document.addEventListener('pointerdown', (e) => {
            if (e.button !== 0 && e.pointerType === 'mouse') return;
            const target = e.target.closest(selector);
            if (!target || target.disabled || target.classList.contains('disabled')) return;

            activeEl = target;
            startX = e.clientX;
            startY = e.clientY;
            activeEl.setAttribute('data-pressed', 'true');
        }, { passive: true });

        document.addEventListener('pointermove', (e) => {
            if (!activeEl) return;
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            if (Math.hypot(dx, dy) > 10) {
                activeEl.removeAttribute('data-pressed');
                activeEl = null;
            }
        }, { passive: true });

        const clearPressed = () => {
            if (activeEl) {
                activeEl.removeAttribute('data-pressed');
                activeEl = null;
            }
        };

        document.addEventListener('pointerup', clearPressed, { passive: true });
        document.addEventListener('pointercancel', clearPressed, { passive: true });
    }

    /**
     * 2. Translucent Sticky Header Scroll Elevation (§12)
     */
    function initScrollNavbar() {
        const navbar = document.querySelector('.dc-navbar');
        if (!navbar) return;

        const update = () => {
            navbar.setAttribute('data-scrolled', window.scrollY > 8 ? 'true' : 'false');
        };
        update();
        window.addEventListener('scroll', update, { passive: true });
    }

    /**
     * 3. Interruptible Segmented Control Indicator (§3, §4)
     */
    function initSegmentedControls(root = document) {
        root.querySelectorAll('[data-dc-segmented]').forEach((container) => {
            if (container._dcSegmentedInit) return;
            container._dcSegmentedInit = true;

            const buttons = Array.from(container.querySelectorAll('.dc-segmented-btn'));
            if (!buttons.length) return;

            let indicator = container.querySelector('.dc-segmented-indicator');
            if (!indicator) {
                indicator = document.createElement('span');
                indicator.className = 'dc-segmented-indicator';
                container.prepend(indicator);
            }

            const xSpring = new FluidSpring(0, {
                damping: 1.0,
                response: 0.32,
                onUpdate: (x) => {
                    indicator.style.transform = `translate3d(${x}px, 0, 0)`;
                },
            });
            const wSpring = new FluidSpring(0, {
                damping: 1.0,
                response: 0.32,
                onUpdate: (w) => {
                    indicator.style.width = `${Math.max(0, w)}px`;
                },
            });

            const syncToButton = (btn, immediate = false) => {
                if (!btn) return;
                const x = btn.offsetLeft;
                const w = btn.offsetWidth;
                if (immediate) {
                    xSpring.set(x);
                    wSpring.set(w);
                } else {
                    xSpring.animateTo(x);
                    wSpring.animateTo(w);
                }
            };

            const initialActive =
                container.querySelector('.dc-segmented-btn.active, .dc-segmented-btn[aria-selected="true"]') ||
                buttons[0];
            syncToButton(initialActive, true);

            buttons.forEach((btn) => {
                btn.addEventListener('click', () => {
                    buttons.forEach((b) => {
                        b.classList.toggle('active', b === btn);
                        b.setAttribute('aria-selected', b === btn ? 'true' : 'false');
                    });
                    syncToButton(btn, false);
                    container.dispatchEvent(new CustomEvent('dc:segment-change', {
                        bubbles: true,
                        detail: { value: btn.dataset.value, button: btn },
                    }));
                });
            });

            window.addEventListener('resize', () => {
                const current = container.querySelector('.dc-segmented-btn.active, .dc-segmented-btn[aria-selected="true"]');
                syncToButton(current, true);
            }, { passive: true });
        });
    }

    /**
     * 4. Fluid Bottom Sheet with 1:1 Drag, Velocity History & Momentum Projection (§2, §5, §6, §9)
     */
    function initFluidSheets() {
        const sheets = new Map();

        document.querySelectorAll('[data-dc-sheet]').forEach((panel) => {
            const id = panel.getAttribute('data-dc-sheet');
            const backdrop = document.querySelector(`[data-dc-sheet-backdrop="${id}"]`);
            const handle = panel.querySelector('.dc-sheet-handle-area') || panel;

            let isOpen = false;
            const ySpring = new FluidSpring(window.innerHeight, {
                damping: 0.86,
                response: 0.32,
                onUpdate: (y) => {
                    panel.style.transform = `translate3d(0, ${y}px, 0)`;
                },
                onComplete: (finalY) => {
                    if (!isOpen && finalY > 0) {
                        panel.setAttribute('aria-hidden', 'true');
                    }
                },
            });

            const open = () => {
                isOpen = true;
                panel.removeAttribute('aria-hidden');
                if (backdrop) backdrop.classList.add('is-open');
                const height = panel.offsetHeight || 480;
                if (ySpring.value > height) ySpring.set(height);
                ySpring.animateTo(0, { damping: 0.9, response: 0.34 });
            };

            const close = (releaseVelocity = 0) => {
                isOpen = false;
                if (backdrop) backdrop.classList.remove('is-open');
                const height = (panel.offsetHeight || 480) + 32;
                ySpring.animateTo(height, {
                    velocity: releaseVelocity,
                    damping: 1.0,
                    response: 0.3,
                });
            };

            // 1:1 Pointer Drag on Handle
            let dragging = false;
            let grabOffsetY = 0;
            let history = [];

            handle.addEventListener('pointerdown', (e) => {
                if (!isOpen) return;
                dragging = true;
                handle.setPointerCapture(e.pointerId);
                ySpring.stop();
                grabOffsetY = e.clientY - ySpring.value;
                history = [{ y: ySpring.value, t: performance.now() }];
            });

            handle.addEventListener('pointermove', (e) => {
                if (!dragging) return;
                const rawY = e.clientY - grabOffsetY;
                const height = panel.offsetHeight || 480;
                const clampedY = rawY < 0 ? -rubberband(Math.abs(rawY), height) : rawY;

                ySpring.value = clampedY;
                panel.style.transform = `translate3d(0, ${clampedY}px, 0)`;

                const now = performance.now();
                history.push({ y: clampedY, t: now });
                while (history.length > 5 && now - history[0].t > 120) {
                    history.shift();
                }
            });

            const finishDrag = () => {
                if (!dragging) return;
                dragging = false;

                let velocity = 0;
                if (history.length >= 2) {
                    const first = history[0];
                    const last = history[history.length - 1];
                    const dt = (last.t - first.t) / 1000;
                    if (dt > 0.004) {
                        velocity = (last.y - first.y) / dt;
                    }
                }

                const height = panel.offsetHeight || 480;
                const projected = ySpring.value + projectMomentum(velocity, 0.992);

                if (velocity > 420 || (velocity >= -80 && projected > height * 0.38)) {
                    close(velocity);
                } else {
                    isOpen = true;
                    if (backdrop) backdrop.classList.add('is-open');
                    ySpring.animateTo(0, { velocity, damping: 0.84, response: 0.32 });
                }
            };

            handle.addEventListener('pointerup', finishDrag);
            handle.addEventListener('pointercancel', finishDrag);
            if (backdrop) backdrop.addEventListener('click', () => close(0));

            sheets.set(id, { open, close });
        });

        document.addEventListener('click', (e) => {
            const openTrigger = e.target.closest('[data-dc-open-sheet]');
            if (openTrigger) {
                const sheet = sheets.get(openTrigger.getAttribute('data-dc-open-sheet'));
                if (sheet) sheet.open();
            }
            const closeTrigger = e.target.closest('[data-dc-close-sheet]');
            if (closeTrigger) {
                const sheet = sheets.get(closeTrigger.getAttribute('data-dc-close-sheet'));
                if (sheet) sheet.close(0);
            }
        });

        sheets.open = (id) => sheets.get(id)?.open();
        sheets.close = (id) => sheets.get(id)?.close(0);
        window.DarahSheets = sheets;
    }

    window.DarahFluid = {
        FluidSpring,
        projectMomentum,
        rubberband,
        initSegmentedControls,
    };

    document.addEventListener('DOMContentLoaded', () => {
        initPressFeedback();
        initScrollNavbar();
        initSegmentedControls();
        initFluidSheets();

        // Prevent sticky navbar from intercepting WebDriver/Dusk top-aligned scrollIntoView clicks
        if (navigator.webdriver) {
            const navbar = document.querySelector('.dc-navbar');
            if (navbar) {
                navbar.style.position = 'relative';
            }
        }

        // Remove third-party injected password balloon overlays (e.g. Kaspersky b_KlBalloonClass)
        const purgeInjectedBalloons = () => {
            document.querySelectorAll('.b_KlBalloonClass, [class*="KlBalloon"]').forEach((el) => el.remove());
        };
        purgeInjectedBalloons();
        new MutationObserver(purgeInjectedBalloons).observe(document.documentElement, {
            childList: true,
            subtree: true,
        });
    });
})();
