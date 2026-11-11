/* ============================================================
   VEHISAFE — START TRIP GPS CAPTURE
   Tries to fill lat/lng hidden inputs before form submits.
   Falls back gracefully if permission is denied.
   ============================================================ */

(function () {
    'use strict';

    var form = document.getElementById('startTripForm');
    var lat  = document.getElementById('latitudeInput');
    var lng  = document.getElementById('longitudeInput');
    var statusBox = document.getElementById('gpsStatus');
    var statusTxt = document.getElementById('gpsStatusText');

    if (!form || !lat || !lng) return;

    function setStatus(text, kind) {
        if (!statusTxt) return;
        statusTxt.textContent = text;
        if (statusBox) {
            statusBox.className = 'alert alert--' + (kind || 'info');
        }
    }

    // Pre-fetch GPS on page load (non-blocking)
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function (pos) {
                lat.value = pos.coords.latitude.toFixed(7);
                lng.value = pos.coords.longitude.toFixed(7);
                setStatus(
                    'GPS captured: ' +
                    pos.coords.latitude.toFixed(5) + ', ' +
                    pos.coords.longitude.toFixed(5),
                    'success'
                );
            },
            function () {
                setStatus('GPS unavailable. Trip will start without a start location.', 'warning');
            },
            { enableHighAccuracy: true, timeout: 5000, maximumAge: 30000 }
        );
    } else {
        setStatus('Your browser does not support GPS.', 'warning');
    }

    // Guard: require a vehicle before submit
    form.addEventListener('submit', function (e) {
        var picked = form.querySelector('input[name="vehicle_id"]:checked');
        if (!picked) {
            e.preventDefault();
            setStatus('Please select a vehicle before starting the trip.', 'danger');
            window.scrollTo({ top: 0, behavior: 'smooth' });
        }
    });
})();