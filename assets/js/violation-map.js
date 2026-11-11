/* ============================================================
   VEHISAFE — SINGLE VIOLATION MAP
   ============================================================ */

(function () {
    'use strict';

    var dataEl = document.getElementById('violation-map-data');
    var mapEl  = document.getElementById('violationMap');
    if (!dataEl || !mapEl || typeof L === 'undefined') return;

    var data;
    try { data = JSON.parse(dataEl.textContent); } catch (e) { return; }
    if (!data.lat || !data.lng) return;

    var map = L.map('violationMap', {
        center: [data.lat, data.lng],
        zoom: 15
    });

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    var color = (data.sev === 'critical' || data.sev === 'high') ? '#dc2626'
              : (data.sev === 'medium' ? '#d97706' : '#0284c7');

    L.circleMarker([data.lat, data.lng], {
        radius: 10,
        fillColor: color,
        color: '#ffffff',
        weight: 3,
        opacity: 1,
        fillOpacity: 1
    }).addTo(map).bindPopup(
        '<strong>' + String(data.type || '').replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); }) + '</strong><br>' +
        'Severity: ' + (data.sev || '—') + '<br>' +
        'Driver: ' + (data.driver || '—') + '<br>' +
        (data.desc ? data.desc + '<br>' : '') +
        '<small>' + (data.when || '') + '</small>'
    ).openPopup();

})();