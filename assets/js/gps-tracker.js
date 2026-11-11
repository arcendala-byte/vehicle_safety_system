/* ============================================================
   VEHISAFE — GPS TRACKER
   Real + simulated GPS feed to /api/gps/update.php
   ============================================================ */

(function () {
    'use strict';

    var root = document.getElementById('gpsTracker');
    if (!root) return;

    var tripId   = parseInt(root.dataset.tripId, 10) || 0;
    var mode     = root.dataset.mode || 'real'; // 'real' | 'simulated'
    var apiUrl   = root.dataset.apiUrl;

    if (tripId <= 0 || !apiUrl) return;

    var statusEl = document.getElementById('gpsStatusText');
    var nearbyEl = document.getElementById('gpsNearby');
    var warnEl   = document.getElementById('gpsWarning');

    var lastLat = null, lastLng = null;
    var watchId = null;
    var simIdx  = 0;

    // Simulated track — Nairobi CBD → Ruiru (~18 km)
    // Matches seed data GPS points + a few extra for realism
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

    function setStatus(text, kind) {
        if (!statusEl) return;
        statusEl.textContent = text;
        if (warnEl) {
            warnEl.className = 'gps-alert gps-alert--' + (kind || 'info');
        }
    }

    function updateWarning(warning) {
        if (!warning) {
            if (warnEl) warnEl.style.display = 'none';
            return;
        }
        if (!warnEl) return;
        warnEl.style.display = '';
        warnEl.className = 'gps-alert gps-alert--danger';
        warnEl.innerHTML = '<i data-lucide="alert-triangle"></i> ' +
                           '<strong>' + warning.level + ':</strong> ' +
                           warning.message;
        if (window.lucide) lucide.createIcons();
    }

    function updateNearby(nearby) {
        if (!nearbyEl) return;
        if (!nearby || nearby.length === 0) {
            nearbyEl.innerHTML = '<div class="text-subtle" style="font-size: 12px;">No black spots within 5 km.</div>';
            return;
        }
        var html = '';
        nearby.slice(0, 5).forEach(function (bs) {
            var color = (bs.risk_level === 'CRITICAL' || bs.risk_level === 'HIGH') ? 'danger'
                      : (bs.risk_level === 'MEDIUM' ? 'warning' : 'success');
            html += '<div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--color-border);font-size:13px;">' +
                    '<span>' + bs.road_name + '</span>' +
                    '<span class="badge badge--' + color + '">' + bs.risk_level + ' · ' + bs.distance_m + 'm</span>' +
                    '</div>';
        });
        nearbyEl.innerHTML = html;
    }

    function sendUpdate(lat, lng, speed) {
        fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
                trip_id: tripId,
                latitude: lat,
                longitude: lng,
                speed_kmh: speed
            })
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                setStatus('Update failed: ' + (data.error || 'unknown'), 'danger');
                return;
            }
            lastLat = lat; lastLng = lng;
            setStatus(
                'Tracking · ' + lat.toFixed(5) + ', ' + lng.toFixed(5) +
                (speed ? ' · ' + speed + ' km/h' : ''),
                'success'
            );
            updateNearby(data.nearby);
            updateWarning(data.warning);
        })
        .catch(function (err) {
            setStatus('Network error: ' + err.message, 'danger');
        });
    }

    // ---- Real GPS mode ----
    function startReal() {
        if (!navigator.geolocation) {
            setStatus('Your browser does not support GPS.', 'warning');
            return;
        }
        setStatus('Requesting GPS permission…', 'info');

        watchId = navigator.geolocation.watchPosition(
            function (pos) {
                var speed = pos.coords.speed != null
                    ? Math.round(pos.coords.speed * 3.6)
                    : null;
                sendUpdate(pos.coords.latitude, pos.coords.longitude, speed);
            },
            function (err) {
                setStatus('GPS error: ' + err.message, 'warning');
            },
            { enableHighAccuracy: true, maximumAge: 3000, timeout: 10000 }
        );
    }

    // ---- Simulated mode ----
    function tick() {
        if (simIdx >= SIM_TRACK.length) {
            setStatus('Simulation finished.', 'info');
            return;
        }
        var pt = SIM_TRACK[simIdx++];
        sendUpdate(pt.lat, pt.lng, pt.speed);
    }

    function startSimulated() {
        setStatus('Simulated GPS: starting…', 'info');
        tick();
        setInterval(tick, 4000); // every 4s like a real GPS tick
    }

    // ---- Boot ----
    if (mode === 'simulated') {
        startSimulated();
    } else {
        startReal();
    }

})();