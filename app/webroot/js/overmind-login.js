(function () {
    'use strict';

    var root = document.documentElement;

    var pw = document.getElementById('UserPassword');
    var toggle = document.getElementById('omlPasswordToggle');
    if (pw && toggle) {
        toggle.addEventListener('click', function () {
            var show = pw.type === 'password';
            pw.type = show ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', show ? 'true' : 'false');
            toggle.setAttribute('aria-label', show ? toggle.dataset.labelHide : toggle.dataset.labelShow);
            var icon = toggle.querySelector('i');
            icon.classList.toggle('fa-eye', !show);
            icon.classList.toggle('fa-eye-slash', show);
        });
    }

    var canvas = document.getElementById('omlSky');
    var ctx = canvas && canvas.getContext ? canvas.getContext('2d') : null;
    if (!ctx) {
        return;
    }
    var host = canvas.closest('.oml') || root;
    var mqReduce = window.matchMedia('(prefers-reduced-motion: reduce)');

    var W = 0, H = 0, dpr = 1;
    var nodes = [], pulses = [];
    var pal = {};
    var pointer = {x: -9999, y: -9999, active: false};
    var raf = 0, last = 0, nextPulse = 0, resizeTimer = 0;
    var LINK = 150, POINTER_R = 170, MAX_NODES = 110;

    function readPalette() {
        var cs = getComputedStyle(host);
        function v(n) { return cs.getPropertyValue(n).trim(); }
        pal = {
            a: v('--oml-c-node-a'), b: v('--oml-c-node-b'), c: v('--oml-c-node-c'),
            link: v('--oml-c-link'), pulse: v('--oml-c-pulse'),
            linkAlpha: parseFloat(v('--oml-c-link-alpha')) || 0.25,
            nodeAlpha: parseFloat(v('--oml-c-node-alpha')) || 0.6
        };
    }

    function rand(a, b) { return a + Math.random() * (b - a); }

    function seed() {
        var n = Math.max(24, Math.min(MAX_NODES, Math.round((W * H) / 15000)));
        nodes = [];
        for (var i = 0; i < n; i++) {
            var big = Math.random() < 0.12;
            nodes.push({
                x: rand(0, W), y: rand(0, H),
                vx: rand(-0.12, 0.12), vy: rand(-0.12, 0.12),
                ox: 0, oy: 0,
                r: big ? rand(6, 8) : rand(2.2, 4.6),
                hollow: big || Math.random() < 0.2,
                tone: Math.random() < 0.5 ? 'a' : (Math.random() < 0.6 ? 'b' : 'c'),
                glow: 0
            });
        }
        pulses = [];
    }

    function resize() {
        dpr = Math.min(window.devicePixelRatio || 1, 2);
        W = window.innerWidth;
        H = window.innerHeight;
        canvas.width = Math.round(W * dpr);
        canvas.height = Math.round(H * dpr);
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
        seed();
        if (!raf) {
            staticFrame();
        }
    }

    function hexPath(x, y, r) {
        ctx.beginPath();
        for (var k = 0; k < 6; k++) {
            var ang = Math.PI / 3 * k - Math.PI / 2;
            var px = x + r * Math.cos(ang), py = y + r * Math.sin(ang);
            if (k === 0) {
                ctx.moveTo(px, py);
            } else {
                ctx.lineTo(px, py);
            }
        }
        ctx.closePath();
    }

    function draw() {
        ctx.clearRect(0, 0, W, H);
        var i, j, a, b, dx, dy, d2, L2 = LINK * LINK;

        ctx.lineWidth = 0.8;
        for (i = 0; i < nodes.length; i++) {
            a = nodes[i];
            for (j = i + 1; j < nodes.length; j++) {
                b = nodes[j];
                dx = (a.x + a.ox) - (b.x + b.ox);
                dy = (a.y + a.oy) - (b.y + b.oy);
                d2 = dx * dx + dy * dy;
                if (d2 < L2) {
                    var t = 1 - Math.sqrt(d2) / LINK;
                    ctx.strokeStyle = 'rgba(' + pal.link + ',' + (t * pal.linkAlpha).toFixed(3) + ')';
                    ctx.beginPath();
                    ctx.moveTo(a.x + a.ox, a.y + a.oy);
                    ctx.lineTo(b.x + b.ox, b.y + b.oy);
                    ctx.stroke();
                }
            }
        }

        for (i = 0; i < pulses.length; i++) {
            var p = pulses[i];
            a = nodes[p.a];
            b = nodes[p.b];
            var ax = a.x + a.ox, ay = a.y + a.oy, bx = b.x + b.ox, by = b.y + b.oy;
            var px = ax + (bx - ax) * p.t, py = ay + (by - ay) * p.t;
            var tail = Math.max(0, p.t - 0.18);
            var tx = ax + (bx - ax) * tail, ty = ay + (by - ay) * tail;
            var grad = ctx.createLinearGradient(tx, ty, px, py);
            grad.addColorStop(0, 'rgba(' + pal.pulse + ',0)');
            grad.addColorStop(1, 'rgba(' + pal.pulse + ',0.85)');
            ctx.strokeStyle = grad;
            ctx.lineWidth = 1.6;
            ctx.beginPath();
            ctx.moveTo(tx, ty);
            ctx.lineTo(px, py);
            ctx.stroke();
            ctx.fillStyle = 'rgba(' + pal.pulse + ',0.25)';
            ctx.beginPath();
            ctx.arc(px, py, 5, 0, Math.PI * 2);
            ctx.fill();
            ctx.fillStyle = 'rgba(' + pal.pulse + ',0.95)';
            ctx.beginPath();
            ctx.arc(px, py, 1.8, 0, Math.PI * 2);
            ctx.fill();
        }

        for (i = 0; i < nodes.length; i++) {
            a = nodes[i];
            var x = a.x + a.ox, y = a.y + a.oy;
            var col = pal[a.tone];
            var alpha = Math.min(1, pal.nodeAlpha + a.glow * 0.5);
            if (a.glow > 0.02) {
                ctx.strokeStyle = 'rgba(' + pal.pulse + ',' + (a.glow * 0.6).toFixed(3) + ')';
                ctx.lineWidth = 1;
                hexPath(x, y, a.r + 4 + (1 - a.glow) * 8);
                ctx.stroke();
            }
            hexPath(x, y, a.r);
            if (a.hollow) {
                ctx.strokeStyle = 'rgba(' + col + ',' + alpha.toFixed(3) + ')';
                ctx.lineWidth = 1.2;
                ctx.stroke();
            } else {
                ctx.fillStyle = 'rgba(' + col + ',' + alpha.toFixed(3) + ')';
                ctx.fill();
            }
        }
    }

    function neighbours(i) {
        var out = [], a = nodes[i], L2 = LINK * LINK * 0.8;
        for (var j = 0; j < nodes.length; j++) {
            if (j === i) {
                continue;
            }
            var dx = a.x - nodes[j].x, dy = a.y - nodes[j].y;
            if (dx * dx + dy * dy < L2) {
                out.push(j);
            }
        }
        return out;
    }

    function spawnPulse(from, hops) {
        for (var tries = 0; tries < 6; tries++) {
            var i = from !== null ? from : Math.floor(Math.random() * nodes.length);
            var nb = neighbours(i);
            if (nb.length) {
                pulses.push({
                    a: i,
                    b: nb[Math.floor(Math.random() * nb.length)],
                    t: 0,
                    speed: rand(0.6, 0.95),
                    hops: hops
                });
                return;
            }
            from = null;
        }
    }

    function step(now) {
        var dt = last ? Math.min(50, now - last) : 16;
        last = now;
        var f = dt / 16.67;
        var i, a;

        for (i = 0; i < nodes.length; i++) {
            a = nodes[i];
            a.x += a.vx * f;
            a.y += a.vy * f;
            if (a.x < -20) { a.x = W + 20; } else if (a.x > W + 20) { a.x = -20; }
            if (a.y < -20) { a.y = H + 20; } else if (a.y > H + 20) { a.y = -20; }

            var tx = 0, ty = 0;
            if (pointer.active) {
                var dx = a.x - pointer.x, dy = a.y - pointer.y;
                var d = Math.sqrt(dx * dx + dy * dy);
                if (d < POINTER_R && d > 0.01) {
                    var push = 1 - d / POINTER_R;
                    push = push * push * 26;
                    tx = dx / d * push;
                    ty = dy / d * push;
                }
            }
            a.ox += (tx - a.ox) * 0.08 * f;
            a.oy += (ty - a.oy) * 0.08 * f;
            if (a.glow > 0) {
                a.glow = Math.max(0, a.glow - 0.012 * f);
            }
        }

        if (now > nextPulse && pulses.length < 6) {
            spawnPulse(null, 1 + Math.floor(Math.random() * 2));
            nextPulse = now + rand(700, 1600);
        }
        for (i = pulses.length - 1; i >= 0; i--) {
            var p = pulses[i];
            p.t += 0.012 * p.speed * f;
            if (p.t >= 1) {
                nodes[p.b].glow = 1;
                pulses.splice(i, 1);
                if (p.hops > 0 && pulses.length < 8) {
                    spawnPulse(p.b, p.hops - 1);
                }
            }
        }

        draw();
        raf = requestAnimationFrame(step);
    }

    function staticFrame() {
        pulses = [];
        for (var i = 0; i < nodes.length; i++) {
            nodes[i].ox = nodes[i].oy = 0;
            nodes[i].glow = 0;
        }
        for (var k = Math.min(4, nodes.length); k > 0; k--) {
            nodes[Math.floor(Math.random() * nodes.length)].glow = 0.7;
        }
        draw();
    }

    function start() {
        if (raf || mqReduce.matches || document.hidden) {
            return;
        }
        last = 0;
        raf = requestAnimationFrame(step);
    }

    function stop() {
        if (raf) {
            cancelAnimationFrame(raf);
        }
        raf = 0;
    }

    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(resize, 120);
    });
    window.addEventListener('pointermove', function (e) {
        pointer.x = e.clientX;
        pointer.y = e.clientY;
        pointer.active = true;
    }, {passive: true});
    document.addEventListener('pointerleave', function () { pointer.active = false; });
    window.addEventListener('blur', function () { pointer.active = false; });
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stop();
        } else {
            start();
        }
    });
    if (mqReduce.addEventListener) {
        mqReduce.addEventListener('change', function () {
            if (mqReduce.matches) {
                stop();
                staticFrame();
            } else {
                start();
            }
        });
    }
    new MutationObserver(function () {
        readPalette();
        if (!raf) {
            draw();
        }
    }).observe(root, {attributes: true, attributeFilter: ['data-bs-theme']});

    readPalette();
    resize();
    start();
})();
