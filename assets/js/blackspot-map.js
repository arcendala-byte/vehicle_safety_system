/* ============================================================
   VEHISAFE — BLACKSPOT MAP
   Renders all active black spots on a Leaflet map with
   risk-colored markers.
   ============================================================ */

(function () {
    'use strict';

    var dataEl = document.getElementById('blackspot-map-data');
    var mapEl  = document.getElementById('blackspotMap');
    if (!dataEl || !mapEl || typeof L === 'undefined') return;

    var data;
    try { data = JSON.parse(dataEl.textContent); } catch (e) { return; }

    var blackspots = data.blackspots || [];
    if (blackspots.length === 0) {
        mapEl.innerHTML = '<div class="empty-state" style="padding: 40px; text-align: center;">No black spots in the database.</div>';
        return;
    }

    // Center on first spot (or Nairobi default)
    var center = [blackspots[0].lat, blackspots[0].lng];
    var map = L.map('blackspotMap').setView(center, 11);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap contributors'
    }).addTo(map);

    var bounds = L.latLngBounds();
    var riskColors = {
        'CRITICAL': '#7f1d1d',
        'HIGH':     '#dc2626',
        'MEDIUM':   '#d97706',
        'LOW':      '#16a34a'
    };

    blackspots.forEach(function (bs) {
        var color = riskColors[bs.risk] || '#64748b';
        var radius = bs.risk === 'CRITICAL' ? 18 : (bs.risk === 'HIGH' ? 14 : 10);

        L.circleMarker([bs.lat, bs.lng], {
            radius: radius,
            fillColor: color,
            color: '#ffffff',
            weight: 3,
            opacity: 1,
            fillOpacity: 0.9
        })
        .addTo(map)
        .bindPopup(
            '<strong>' + bs.name + '</strong><br>' +
            (bs.desc ? bs.desc + '<br>' : '') +
            'Risk: <strong>' + bs.risk + '</strong> (score ' + bs.score.toFixed(2) + ')<br>' +
            'Accidents: ' + bs.accidents
        );

        bounds.extend([bs.lat, bs.lng]);
    });

    if (blackspots.length > 1) {
        map.fitBounds(bounds, { padding: [30, 30] });
    }

})();