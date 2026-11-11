/* ============================================================
   VEHISAFE — TRIP MAP
   Renders GPS trail + violation markers on a Leaflet map.
   ============================================================ */

(function () {
    'use strict';

    var dataEl = document.getElementById('trip-map-data');
    var mapEl  = document.getElementById('tripMap');

    if (!dataEl || !mapEl || typeof L === 'undefined') return;

    var data;
    try { data = JSON.parse(dataEl.textContent); } catch (e) { return; }

    // ---- Init map ----
    var start = data.start && data.start.lat ? data.start : { lat: -1.2921, lng: 36.8219 };
    var map = L.map('tripMap', {
        center: [start.lat, start.lng],
        zoom: 13,
        zoomControl: true,
        attributionControl: true
    });

    // ---- Base tile layer ----
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    // ---- GPS trail (polyline) ----
    var coords = (data.gps || []).map(function (p) { return [p.lat, p.lng]; });

    if (coords.length >= 2) {
        var trail = L.polyline(coords, {
            color: '#2563eb',
            weight: 4,
            opacity: 0.85,
            lineJoin: 'round',
            lineCap: 'round'
        }).addTo(map);
        map.fitBounds(trail.getBounds(), { padding: [30, 30] });
    } else if (coords.length === 1) {
        map.setView(coords[0], 14);
    }

    // ---- Start marker (green) ----
    if (data.start && data.start.lat) {
        L.marker([data.start.lat, data.start.lng], {
            icon: L.divIcon({
                className: '',
                html: '<div style="background:#16a34a;width:16px;height:16px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);"></div>',
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            })
        }).addTo(map).bindPopup('<strong>Trip start</strong>');
    }

    // ---- End marker (red) ----
    if (data.end && data.end.lat) {
        L.marker([data.end.lat, data.end.lng], {
            icon: L.divIcon({
                className: '',
                html: '<div style="background:#dc2626;width:16px;height:16px;border-radius:50%;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.3);"></div>',
                iconSize: [16, 16],
                iconAnchor: [8, 8]
            })
        }).addTo(map).bindPopup('<strong>Trip end</strong>');
    }

    // ---- Violation markers (amber/red based on severity) ----
    (data.violations || []).forEach(function (v) {
        if (!v.lat || !v.lng) return;
        var color = (v.sev === 'critical' || v.sev === 'high') ? '#dc2626'
                  : (v.sev === 'medium' ? '#d97706' : '#0284c7');
        var label = String(v.type || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });

        L.circleMarker([v.lat, v.lng], {
            radius: 8,
            fillColor: color,
            color: '#ffffff',
            weight: 2,
            opacity: 1,
            fillOpacity: 1
        }).addTo(map).bindPopup(
            '<strong>' + label + '</strong><br>' +
            'Severity: ' + (v.sev || '—') + '<br>' +
            (v.desc ? v.desc + '<br>' : '') +
            '<small>' + (v.t || '') + '</small>'
        );
    });

})();