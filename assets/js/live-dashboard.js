/* ============================================================
   VEHISAFE — LIVE DASHBOARD
   Camera + Detection + GPS + Score + Ignition + Alerts
   ============================================================ */

(function () {
    'use strict';

    var root = document.getElementById('liveDashboard');
    if (!root) return;

    var apiDetect   = root.dataset.apiDetect;
    var apiSnapshot = root.dataset.apiSnapshot;
    var apiGps      = root.dataset.apiGps;
    var tripId      = parseInt(root.dataset.tripId, 10) || 0;
    var mode        = root.dataset.mode || 'simulated';
    var testImgUrl  = root.dataset.testImageUrl;

    var video      = document.getElementById('dashVideo');
    var canvas     = document.getElementById('dashCanvas');
    var startBtn   = document.getElementById('dashStartBtn');
    var stopBtn    = document.getElementById('dashStopBtn');
    var simBtn     = document.getElementById('dashSimBtn');
    var refreshBtn = document.getElementById('dashRefreshBtn');

    var camStatus  = document.getElementById('dashCameraStatus');
    var fpsEl      = document.getElementById('dashFps');
    var framesEl   = document.getElementById('dashFrames');

    var ctx = canvas.getContext('2d');
    var running = false;
    var timer = null;
    var frameCount = 0;
    var startTime = 0;
    var simIdx = 0;

    // ---- Simulated GPS track (Nairobi CBD → Ruiru) ----
    var SIM_TRACK = [
        { lat: -1.29210, lng: 36.82190, speed:  0 },
        { lat: -1.28500, lng: 36.83200, speed: 30 },
        { lat: -1.27500, lng: 36.84500, speed: 45 },
        { lat: -1.26000, lng: 36.86000, speed: 50 },
        { lat: -1.25000, lng: 36.87000, speed: 45 },
        { lat: -1.23500, lng: 36.88500, speed: 55 },
        { lat: -1.22000, lng: 36.89500, speed: 58 },
        { lat: -1.20500, lng: 36.90500, speed: 62 },
        { lat: -1.20000, lng: 36.91000, speed: 62 },
        { lat: -1.18000, lng: 36.92500, speed: 58 },
        { lat: -1.17000, lng: 36.94000, speed: 55 },
        { lat: -1.16000, lng: 36.95000, speed: 48 },
        { lat: -1.14600, lng: 36.96000, speed:  0 }
    ];

    // ---- Detection badges ----
    function setDetectionBadge(id, cls, text) {
        var el = document.getElementById(id);
        if (!el) return;
        el.className = 'dash-detection__badge dash-detection__badge--' + cls;
        el.textContent = text;
    }

    function renderDetections(detections) {
        detections.forEach(function (d) {
            var unsafe = false;

            if (d.type === 'seatbelt') {
                if (d.status === 'detected')     setDetectionBadge('detSeatbelt', 'success', 'OK');
                else if (d.status === 'not_detected') { setDetectionBadge('detSeatbelt', 'danger', 'MISSING'); unsafe = true; }
                else setDetectionBadge('detSeatbelt', 'warning', '?');
            } else if (d.type === 'phone') {
                if (d.status === 'detected') { setDetectionBadge('detPhone', 'danger', 'USED'); unsafe = true; }
                else setDetectionBadge('detPhone', 'success', 'CLEAR');
            } else if (d.type === 'drowsiness') {
                if (d.status === 'alert') setDetectionBadge('detDrowsiness', 'success', 'ALERT');
                else if (d.status === 'eyes_closed') { setDetectionBadge('detDrowsiness', 'danger', 'CLOSED'); unsafe = true; }
                else if (d.status === 'yawning') { setDetectionBadge('detDrowsiness', 'warning', 'YAWN'); unsafe = true; }
                else setDetectionBadge('detDrowsiness', 'warning', '?');
            } else if (d.type === 'distraction') {
                if (d.status === 'attentive') setDetectionBadge('detDistraction', 'success', 'OK');
                else if (d.status === 'distracted') { setDetectionBadge('detDistraction', 'danger', 'AWAY'); unsafe = true; }
                else setDetectionBadge('detDistraction', 'warning', '?');
            }

            // ---- Sync ignition with seatbelt state ----
            if (d.type === 'seatbelt') {
                if (d.status === 'detected') {
                    setIgnition(true);
                } else if (d.status === 'not_detected') {
                    setIgnition(false);
                }
            }

            // ---- Trigger alert on unsafe detection ----
            if (unsafe && window.VehiSafeAlerts) {
                var sev = (d.type === 'seatbelt' || d.type === 'drowsiness') ? 'critical' : 'high';
                var alertType = d.type === 'seatbelt' ? 'seatbelt_violation'
                              : d.type === 'phone' ? 'phone_usage'
                              : d.type === 'drowsiness' ? 'drowsiness'
                              : 'distraction';
                window.VehiSafeAlerts.trigger(alertType, { severity: sev });
            }
        });
    }

    // ---- Camera ----
    function startCamera() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            camStatus.textContent = 'UNSUPPORTED';
            camStatus.className = 'dash-badge dash-badge--danger';
            return;
        }
        navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false })
            .then(function (stream) {
                video.srcObject = stream;
                video.play();
                video.onloadedmetadata = function () {
                    canvas.width  = video.videoWidth;
                    canvas.height = video.videoHeight;
                    camStatus.textContent = 'LIVE';
                    camStatus.className = 'dash-badge dash-badge--success';
                    running = true;
                    startTime = Date.now();
                    loop();
                };
            })
            .catch(function () {
                camStatus.textContent = 'DENIED';
                camStatus.className = 'dash-badge dash-badge--danger';
            });
    }

    function stopCamera() {
        running = false;
        if (timer) clearTimeout(timer);
        if (video.srcObject) {
            video.srcObject.getTracks().forEach(function (t) { t.stop(); });
            video.srcObject = null;
        }
        camStatus.textContent = 'OFFLINE';
        camStatus.className = 'dash-badge dash-badge--neutral';
    }

    function captureFrame() {
        if (!video.videoWidth) return null;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/jpeg', 0.75);
    }

    function loop() {
        if (!running) return;
        var frame = captureFrame();
        if (frame) sendFrame(frame);
        timer = setTimeout(loop, 800);
    }

    function sendFrame(dataUrl) {
        fetch(apiDetect, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ trip_id: tripId || null, image_base64: dataUrl })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;
            frameCount++;
            if (framesEl) framesEl.textContent = frameCount;
            var elapsed = (Date.now() - startTime) / 1000;
            if (elapsed > 0 && fpsEl) fpsEl.textContent = (frameCount / elapsed).toFixed(1);

            renderDetections(data.detections || []);
            if (data.logged_violations && data.logged_violations.length > 0) {
                refreshSnapshot();
            }
        })
        .catch(function () {});
    }

    function simulateFrame() {
        var img = document.createElement('img');
        img.crossOrigin = 'anonymous';
        img.onload = function () {
            canvas.width = img.width;
            canvas.height = img.height;
            ctx.drawImage(img, 0, 0);
            startTime = startTime || Date.now();
            sendFrame(canvas.toDataURL('image/jpeg', 0.75));
        };
        img.onerror = function () {
            if (camStatus) camStatus.textContent = 'TEST IMAGE MISSING';
        };
        img.src = testImgUrl;
    }

    // ---- GPS ----
    function tickGps() {
        if (!tripId) return;
        if (simIdx >= SIM_TRACK.length) simIdx = 0;
        var pt = SIM_TRACK[simIdx++];

        document.getElementById('gpsLat').textContent = pt.lat.toFixed(5);
        document.getElementById('gpsLng').textContent = pt.lng.toFixed(5);
        document.getElementById('gpsSpeed').textContent = pt.speed + ' km/h';

        fetch(apiGps, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                trip_id: tripId,
                latitude: pt.lat,
                longitude: pt.lng,
                speed_kmh: pt.speed
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;
            if (data.nearby && data.nearby.length > 0) {
                var el = document.getElementById('dashNearby');
                var html = '';
                data.nearby.slice(0, 3).forEach(function (bs) {
                    var color = (bs.risk_level === 'CRITICAL' || bs.risk_level === 'HIGH') ? 'danger'
                              : (bs.risk_level === 'MEDIUM' ? 'warning' : 'success');
                    html += '<div class="dash-nearby__row">' +
                            '<span>' + bs.road_name + '</span>' +
                            '<span class="badge badge--' + color + '">' + bs.risk_level + ' · ' + bs.distance_m + 'm</span>' +
                            '</div>';
                });
                el.innerHTML = html;
            }
            if (data.warning && window.VehiSafeAlerts) {
                window.VehiSafeAlerts.trigger('blackspot_warning', {
                    severity: data.warning.level === 'CRITICAL' ? 'critical' : 'high',
                    message: data.warning.message
                });
            }
        })
        .catch(function () {});
    }

    // ---- Snapshot (score + events) ----
    function refreshSnapshot() {
        fetch(apiSnapshot, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                var score = data.driver_score || 0;
                var sv = document.getElementById('scoreValue');
                if (sv) sv.textContent = score;
                var ring = document.getElementById('scoreRing');
                if (ring) ring.style.setProperty('--pct', score);

                if (data.active_trip) {
                    var st = document.getElementById('scoreTrip');
                    if (st) st.textContent = 'Trip #' + data.active_trip.trip_id;
                    var svi = document.getElementById('scoreViolations');
                    if (svi) svi.textContent = data.active_trip.violations;
                    var gc = document.getElementById('gpsCount');
                    if (gc) gc.textContent = data.active_trip.gps_points;
                }

                renderEvents(data.recent_violations || []);
            })
            .catch(function () {});
    }

    function renderEvents(violations) {
        var el = document.getElementById('dashEvents');
        if (!el) return;
        if (!violations || violations.length === 0) {
            el.innerHTML = '<div class="empty-state" style="padding:24px;">' +
                           '<div class="empty-state__title">No events yet</div></div>';
            return;
        }
        var html = '';
        violations.forEach(function (v) {
            var sevClass = v.severity === 'critical' || v.severity === 'high' ? 'danger'
                         : v.severity === 'medium' ? 'warning' : 'info';
            var t = new Date(v.occurred_at.replace(' ', 'T'));
            var timeStr = String(t.getHours()).padStart(2, '0') + ':' +
                          String(t.getMinutes()).padStart(2, '0') + ':' +
                          String(t.getSeconds()).padStart(2, '0');
            var label = (v.event_type || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
            html += '<div class="dash-event dash-event--' + sevClass + '">' +
                    '<span class="dash-event__time">' + timeStr + '</span>' +
                    '<span class="dash-event__label">' + label + '</span>' +
                    '<span class="dash-badge dash-badge--' + sevClass + '">' + v.severity + '</span>' +
                    '</div>';
        });
        el.innerHTML = html;
    }

    // ---- IGNITION (Arduino) ----
    var ignitionStatusEl = document.getElementById('ignitionStatus');
    var ignitionRingEl   = document.getElementById('ignitionRing');
    var ignitionDescEl   = document.getElementById('ignitionDesc');
    var ignitionModeEl   = document.getElementById('dashIgnitionMode');

    function updateIgnition(on) {
        if (!ignitionStatusEl) return;
        if (on) {
            ignitionStatusEl.textContent = 'ENABLED';
            ignitionStatusEl.className = 'dash-ignition__status dash-ignition__status--on';
            if (ignitionDescEl) ignitionDescEl.textContent = 'Seatbelt fastened — engine ready';
            if (ignitionRingEl) ignitionRingEl.className = 'dash-ignition__ring dash-ignition__ring--on';
            var iconEl = ignitionRingEl && ignitionRingEl.querySelector('[data-lucide]');
            if (iconEl) iconEl.setAttribute('data-lucide', 'unlock');
        } else {
            ignitionStatusEl.textContent = 'LOCKED';
            ignitionStatusEl.className = 'dash-ignition__status dash-ignition__status--off';
            if (ignitionDescEl) ignitionDescEl.textContent = 'Seatbelt not fastened — engine disabled';
            if (ignitionRingEl) ignitionRingEl.className = 'dash-ignition__ring dash-ignition__ring--off';
            var iconEl2 = ignitionRingEl && ignitionRingEl.querySelector('[data-lucide]');
            if (iconEl2) iconEl2.setAttribute('data-lucide', 'lock');
        }
        if (window.lucide) lucide.createIcons();
    }

    function fetchIgnitionStatus() {
        var aiUrl = window.__VEHISAFE_AI_URL__;
        var aiKey = window.__VEHISAFE_AI_KEY__;
        if (!aiUrl || !aiKey) return;

        fetch(aiUrl + '/api/arduino/status', {
            headers: { 'X-API-Key': aiKey }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) return;
            var state = data.state || {};
            updateIgnition(!!state.ignition_on);
            if (ignitionModeEl) {
                ignitionModeEl.textContent = state.connected ? 'HARDWARE' : 'SIMULATION';
                ignitionModeEl.className = 'dash-badge dash-badge--' + (state.connected ? 'success' : 'info');
            }
        })
        .catch(function () {});
    }

    function setIgnition(on) {
        var aiUrl = window.__VEHISAFE_AI_URL__;
        var aiKey = window.__VEHISAFE_AI_KEY__;
        if (!aiUrl || !aiKey) return;

        fetch(aiUrl + '/api/arduino/ignition', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-API-Key': aiKey
            },
            body: JSON.stringify({ ignition_on: on })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) updateIgnition(data.ignition_on);
        })
        .catch(function () {});
    }

    // ---- Buttons ----
    if (startBtn) startBtn.addEventListener('click', startCamera);
    if (stopBtn) stopBtn.addEventListener('click', stopCamera);
    if (simBtn) simBtn.addEventListener('click', simulateFrame);
    if (refreshBtn) refreshBtn.addEventListener('click', refreshSnapshot);

    // ---- Boot ----
    refreshSnapshot();
    fetchIgnitionStatus();

    if (tripId) {
        setInterval(tickGps, 4000);
        setInterval(refreshSnapshot, 8000);
        if (mode === 'simulated') setTimeout(tickGps, 1000);
    }

    setInterval(fetchIgnitionStatus, 5000);

})();
