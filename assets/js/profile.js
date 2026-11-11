/* ============================================================
   VEHISAFE — PROFILE PAGE
   Handles update profile + change password AJAX
   ============================================================ */

(function () {
    'use strict';

    var page = document.getElementById('profilePage');
    if (!page) return;

    var apiUpdate   = page.dataset.apiUpdate;
    var apiPassword = page.dataset.apiPassword;
    var csrf        = window.__VEHISAFE_CSRF__ || '';
    var updateForm  = document.getElementById('updateProfileForm');
    var passForm    = document.getElementById('changePasswordForm');

    function postJson(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf,
            },
            credentials: 'same-origin',
            body: JSON.stringify(Object.assign({ csrf_token: csrf }, body)),
        }).then(function (r) { return r.json(); });
    }

    function setLoading(form, isLoading) {
        var btn = form.querySelector('button[type="submit"]');
        if (!btn) return;
        if (isLoading) {
            btn.dataset.originalText = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span> Saving...';
        } else {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalText || btn.innerHTML;
        }
    }

    // ---- Update profile ----
    if (updateForm) {
        updateForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(updateForm);
            var payload = {
                full_name: fd.get('full_name') || '',
                email:     fd.get('email') || '',
                phone:     fd.get('phone') || '',
            };
            setLoading(updateForm, true);

            postJson(apiUpdate, payload).then(function (res) {
                setLoading(updateForm, false);
                if (res.success) {
                    if (window.showToast) window.showToast('Profile updated successfully', 'success');
                    setTimeout(function () { location.reload(); }, 800);
                } else {
                    var msg = res.error || 'Update failed';
                    if (res.errors) {
                        msg = Object.values(res.errors).join(' ');
                    }
                    if (window.showToast) window.showToast(msg, 'error');
                    else alert(msg);
                }
            }).catch(function () {
                setLoading(updateForm, false);
                if (window.showToast) window.showToast('Network error', 'error');
            });
        });
    }

    // ---- Change password ----
    if (passForm) {
        passForm.addEventListener('submit', function (e) {
            e.preventDefault();
            var fd = new FormData(passForm);
            var payload = {
                current_password: fd.get('current_password') || '',
                new_password:     fd.get('new_password') || '',
                confirm_password: fd.get('confirm_password') || '',
            };

            if (payload.new_password.length < 8) {
                if (window.showToast) window.showToast('Password must be at least 8 characters', 'error');
                return;
            }
            if (payload.new_password !== payload.confirm_password) {
                if (window.showToast) window.showToast('Passwords do not match', 'error');
                return;
            }

            setLoading(passForm, true);

            postJson(apiPassword, payload).then(function (res) {
                setLoading(passForm, false);
                if (res.success) {
                    if (window.showToast) window.showToast('Password updated successfully', 'success');
                    passForm.reset();
                } else {
                    if (window.showToast) window.showToast(res.error || 'Password change failed', 'error');
                }
            }).catch(function () {
                setLoading(passForm, false);
                if (window.showToast) window.showToast('Network error', 'error');
            });
        });
    }
})();