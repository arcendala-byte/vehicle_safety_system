/* ============================================================
   VEHISAFE — LIVE MONITORING
   Webcam capture + AI detection + auto-alerts on violations.
   ============================================================ */

(function () {
    'use strict';

    var root = document.getElementById('liveMonitor');
    if (!root) return;

    var apiUrl   = root.dataset.apiUrl;
    var tripId   = parseInt(root.dataset.tripId, 10) || 0;
    var interval = parseInt(root.dataset.interval, 10) || 800;
    var running  = false;
    var timer    = null;
    var frameCount = 0;
    var startTime  = 0;
    var canvas     = document.getElementById('liveCaptureCanvas');
    var video      = document.getElementById('liveVideo');
    var statusEl   = document.getElementById('liveStatus');
    var fpsEl      = document.getElementById('liveFps');
    var frameEl    = document.getElementById('liveFrameCount');
    var resultsEl  = document.getElementById('liveResults');
    var eventsEl   = document.getElementById('liveEvents');

    var ctx = canvas.getContext('2d');

    function setStatus(text, kind) {
        if (!statusEl) return;
        statusEl.textContent = text;
        statusEl.className = 'live-status live-status--' + (kind || 'info');
    }

    function startWebcam() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            setStatus('Webcam not supported.', 'danger');
            return;
        }
        setStatus('Requesting webcam…', 'info');
        navigator.mediaDevices.getUserMedia({ video: { width: 640, height: 480 }, audio: false })
            .then(function (stream) {
                video.srcObject = stream;
                video.play();
                video.onloadedmetadata = function () {
                    canvas.width  = video.videoWidth;
                    canvas.height = video.videoHeight;
                    setStatus('Webcam ready — starting monitoring…', 'success');
                    setTimeout(startLoop, 500);
                };
            })
            .catch(function (err) {
                setStatus('Webcam error: ' + err.message, 'danger');
            });
    }

    function captureFrame() {
        if (!video.videoWidth) return null;
        ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
        return canvas.toDataURL('image/jpeg', 0.75);
    }

    function sendFrame(dataUrl) {
        fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                trip_id: tripId || null,
                image_base64: dataUrl
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                setStatus('Detection error: ' + (data.error || 'unknown'), 'danger');
                return;
            }
            frameCount++;
            if (frameEl) frameEl.textContent = frameCount;

            var elapsed = (Date.now() - startTime) / 1000;
            if (fpsEl && elapsed > 0) fpsEl.textContent = (frameCount / elapsed).toFixed(1);

            renderDetections(data.detections || []);
            if (data.logged_violations && data.logged_violations.length > 0) {
                data.logged_violations.forEach(addEvent);
            }
        })
        .catch(function (err) {
            setStatus('Network error: ' + err.message, 'danger');
        });
    }

    function renderDetections(detections) {
        if (!resultsEl) return;
        var html = '';
        detections.forEach(function (d) {
            var cls = 'live-badge--neutral';
            var txt = d.status;
            var unsafe = false;

            if (d.type === 'seatbelt') {
                if (d.status === 'detected') { cls = 'live-badge--success'; txt = 'DETECTED'; }
                else if (d.status === 'not_detected') { cls = 'live-badge--danger'; txt = 'NOT DETECTED'; unsafe = true; }
                else { cls = 'live-badge--warning'; txt = 'UNCERTAIN'; }
            } else if (d.type === 'phone') {
                if (d.status === 'detected') { cls = 'live-badge--danger'; txt = 'USED'; unsafe = true; }
                else if (d.status === 'not_detected') { cls = 'live-badge--success'; txt = 'CLEAR'; }
                else { cls = 'live-badge--warning'; txt = 'UNCERTAIN'; }
            } else if (d.type === 'drowsiness') {
                if (d.status === 'alert') { cls = 'live-badge--success'; txt = 'ALERT'; }
                else if (d.status === 'eyes_closed') { cls = 'live-badge--danger'; txt = 'EYES CLOSED'; unsafe = true; }
                else if (d.status === 'yawning') { cls = 'live-badge--warning'; txt = 'YAWNING'; unsafe = true; }
                else { cls = 'live-badge--warning'; txt = 'UNCERTAIN'; }
            } else if (d.type === 'distraction') {
                if (d.status === 'attentive') { cls = 'live-badge--success'; txt = 'ATTENTIVE'; }
                else if (d.status === 'distracted') { cls = 'live-badge--danger'; txt = 'DISTRACTED'; unsafe = true; }
                else { cls = 'live-badge--warning'; txt = 'UNCERTAIN'; }
            }

            var conf = (d.confidence * 100).toFixed(0);
            html += '<div class="live-result">' +
                    '<div class="live-result__label">' + capitalize(d.type) + '</div>' +
                    '<div class="live-badge ' + cls + '">' + txt + '</div>' +
                    '<div class="live-result__conf">' + conf + '%</div>' +
                    '</div>';

            if (unsafe && window.VehiSafeAlerts) {
                var sev = (d.type === 'seatbelt' || d.type === 'drowsiness') ? 'critical' : 'high';
                var alertType = d.type === 'seatbelt' ? 'seatbelt_violation'
                              : d.type === 'phone' ? 'phone_usage'
                              : d.type === 'drowsiness' ? 'drowsiness'
                              : 'distraction';
                window.VehiSafeAlerts.trigger(alertType, { severity: sev, speak: sev === 'critical' });
            }
        });
        resultsEl.innerHTML = html;
    }

    function capitalize(s) { return s.charAt(0).toUpperCase() + s.slice(1); }

    function addEvent(eventType) {
        if (!eventsEl) return;
        var now = new Date();
        var timeStr = String(now.getHours()).padStart(2, '0') + ':' +
                      String(now.getMinutes()).padStart(2, '0') + ':' +
                      String(now.getSeconds()).padStart(2, '0');
        var label = eventType.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
        var item = document.createElement('div');
        item.className = 'live-event';
        item.innerHTML = '<div class="live-event__time">' + timeStr + '</div>' +
                         '<div class="live-event__label">' + label + '</div>' +
                         '<div class="live-event__badge">Logged</div>';
        eventsEl.insertBefore(item, eventsEl.firstChild);
        while (eventsEl.children.length > 20) eventsEl.removeChild(eventsEl.lastChild);
    }

    function loop() {
        if (!running) return;
        var frame = captureFrame();
        if (frame) sendFrame(frame);
        timer = setTimeout(loop, interval);
    }

    function startLoop() {
        running = true;
        startTime = Date.now();
        setStatus('Monitoring active', 'success');
        loop();
    }

    function stopLoop() {
        running = false;
        if (timer) clearTimeout(timer);
        setStatus('Stopped', 'info');
    }

    function simulateFrame() {
        var testImg = document.createElement('img');
        testImg.crossOrigin = 'anonymous';
        testImg.onload = function () {
            canvas.width = testImg.width;
            canvas.height = testImg.height;
            ctx.drawImage(testImg, 0, 0);
            startTime = startTime || Date.now();
            sendFrame(canvas.toDataURL('image/jpeg', 0.75));
        };
        testImg.onerror = function () {
            setStatus('Test image not found at ' + root.dataset.testImageUrl, 'danger');
        };
        testImg.src = root.dataset.testImageUrl;
    }

    document.getElementById('liveStartBtn').addEventListener('click', startWebcam);
    document.getElementById('liveStopBtn').addEventListener('click', stopLoop);
    var simBtn = document.getElementById('liveSimBtn');
    if (simBtn) simBtn.addEventListener('click', simulateFrame);

    setStatus('Ready', 'info');

})();
