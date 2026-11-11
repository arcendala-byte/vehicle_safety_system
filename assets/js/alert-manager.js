/* ============================================================
   VEHISAFE — ALERT MANAGER
   Audio + visual toasts + screen flash + optional voice.
   With per-type cooldowns to prevent alert spam.
   ============================================================ */

(function () {
    'use strict';

    var ALERT_COOLDOWN_MS = 15000;
    var AUDIO_BASE_URL = window.__VEHISAFE_AUDIO_BASE__ || '/assets/audio/';

    var lastAlertTime = {};
    var audioCache = {};

    function preloadAudio() {
        ['alert-info', 'alert-warning', 'alert-critical', 'alert-phone'].forEach(function (name) {
            try {
                var a = new Audio(AUDIO_BASE_URL + name + '.wav');
                a.preload = 'auto';
                audioCache[name] = a;
            } catch (e) {}
        });
    }

    function playSound(name) {
        var a = audioCache[name];
        if (!a) return;
        try {
            a.currentTime = 0;
            a.play().catch(function () {});
        } catch (e) {}
    }

    function ensureFlash() {
        var o = document.getElementById('alertFlashOverlay');
        if (o) return o;
        o = document.createElement('div');
        o.id = 'alertFlashOverlay';
        o.className = 'alert-flash-overlay';
        document.body.appendChild(o);
        return o;
    }

    function flashScreen(severity) {
        var o = ensureFlash();
        o.className = 'alert-flash-overlay alert-flash-overlay--' + severity;
        void o.offsetWidth;
        o.classList.add('alert-flash-overlay--active');
        setTimeout(function () { o.classList.remove('alert-flash-overlay--active'); }, 900);
    }

    function ensureToasts() {
        var c = document.getElementById('alertToastContainer');
        if (c) return c;
        c = document.createElement('div');
        c.id = 'alertToastContainer';
        c.className = 'alert-toast-container';
        document.body.appendChild(c);
        return c;
    }

    function showToast(message, severity, icon) {
        var c = ensureToasts();
        var t = document.createElement('div');
        t.className = 'alert-toast alert-toast--' + (severity || 'info');
        var iconHtml = icon ? '<i data-lucide="' + icon + '"></i>' : '';
        t.innerHTML = iconHtml + '<span>' + message + '</span>';
        c.appendChild(t);
        if (window.lucide) lucide.createIcons();

        setTimeout(function () {
            t.style.opacity = '0';
            t.style.transform = 'translateX(100%)';
            setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 300);
        }, 6000);

        t.addEventListener('click', function () {
            if (t.parentNode) t.parentNode.removeChild(t);
        });
    }

    function speak(text) {
        if (!window.speechSynthesis) return;
        try {
            var u = new SpeechSynthesisUtterance(text);
            u.rate = 1.0; u.pitch = 1.0; u.volume = 0.9;
            window.speechSynthesis.speak(u);
        } catch (e) {}
    }

    function soundFor(type) {
        switch (type) {
            case 'seatbelt_violation': return 'alert-critical';
            case 'phone_usage':        return 'alert-phone';
            case 'drowsiness':         return 'alert-critical';
            case 'distraction':        return 'alert-warning';
            default:                   return 'alert-info';
        }
    }

    function iconFor(type) {
        switch (type) {
            case 'seatbelt_violation': return 'shield-alert';
            case 'phone_usage':        return 'smartphone';
            case 'drowsiness':         return 'moon';
            case 'distraction':        return 'eye-off';
            default:                   return 'alert-circle';
        }
    }

    function messageFor(type) {
        switch (type) {
            case 'seatbelt_violation': return 'Seatbelt not detected — please fasten your seatbelt.';
            case 'phone_usage':        return 'Phone usage detected — focus on the road.';
            case 'drowsiness':         return 'Drowsiness detected — consider pulling over to rest.';
            case 'distraction':        return 'Distraction detected — keep your eyes on the road.';
            default:                   return 'Safety alert triggered.';
        }
    }

    window.VehiSafeAlerts = {
        trigger: function (type, opts) {
            opts = opts || {};
            var now = Date.now();

            if (lastAlertTime[type] && (now - lastAlertTime[type] < ALERT_COOLDOWN_MS)) {
                return false;
            }
            lastAlertTime[type] = now;

            var severity = opts.severity || 'warning';
            var message  = opts.message || messageFor(type);

            playSound(soundFor(type));

            if (severity === 'critical' || severity === 'high') {
                flashScreen(severity);
            }

            showToast(message, severity, iconFor(type));

            if (opts.speak && severity === 'critical') {
                speak(message);
            }

            return true;
        },

        test: function () {
            // Bypass cooldown for test
            lastAlertTime = {};
            this.trigger('seatbelt_violation', {
                severity: 'critical',
                message: 'TEST: Seatbelt violation detected',
                speak: true
            });
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        var unlock = function () {
            preloadAudio();
            document.removeEventListener('click', unlock);
            document.removeEventListener('keydown', unlock);
        };
        document.addEventListener('click', unlock);
        document.addEventListener('keydown', unlock);
        preloadAudio();
    });

})();
