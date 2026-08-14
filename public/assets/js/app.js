/* ==========================================================================
   Initial JavaScript structure.
   Uses an IIFE namespace "SHR" (Student Health Records). No inline scripts
   are used in views; all behaviour attaches here after DOMContentLoaded.
   ========================================================================== */
'use strict';

window.SHR = (function () {
    var csrfToken = null;

    function init() {
        readCsrfToken();
        initAppointmentAvailability();
        initAnalyticsCharts();

        // Future: progressive enhancement hooks for forms, toasts,
        // accessible confirmations, and server calls (all non-inline).
        document.addEventListener('click', function (event) {
            var confirmEl = event.target.closest('[data-confirm]');
            if (confirmEl && !window.confirm(confirmEl.getAttribute('data-confirm'))) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    }

    /* Appointment availability (PROMPT 6): progressively enhance the request
       form by refreshing the availability table when the staff member or date
       changes, and keeping the "Show availability calendar" link in sync with
       the chosen staff member. The server always renders the table for a valid
       staff+date, so this is an enhancement only and never required for
       correctness. */
    function initAppointmentAvailability() {
        var staff = document.getElementById('staff_id');
        var date = document.getElementById('scheduled_at');
        var duration = document.getElementById('duration_minutes');
        var availLink = document.getElementById('show-availability-link');
        if (!staff || !date) {
            return;
        }
        var statusEl = document.getElementById('availability-status');

        function syncAvailabilityLink() {
            if (!availLink) {
                return;
            }
            var month = availLink.getAttribute('data-month') || '';
            var sid = staff.value ? staff.value : '0';
            var mins = duration && duration.value ? duration.value : '30';
            availLink.href = baseUrl('/appointments/new?staff_id=' + encodeURIComponent(sid) +
                '&month=' + encodeURIComponent(month) +
                '&duration=' + encodeURIComponent(mins));
        }

        function refresh() {
            var staffId = staff.value;
            var dateValue = date.value ? date.value.slice(0, 10) : '';
            if (!staffId || !dateValue || !statusEl) {
                return;
            }
            fetch(baseUrl('/appointments/availability?staff_id=' + encodeURIComponent(staffId) +
                '&date=' + encodeURIComponent(dateValue) +
                '&duration=' + (duration && duration.value ? duration.value : 30)))
                .then(function (response) { return response.json().catch(function () { return null; }); })
                .then(function (data) {
                    if (!data || !data.success) {
                        statusEl.textContent = 'Availability could not be loaded. The form will validate your slot on submission.';
                        return;
                    }
                    var free = data.slots.filter(function (s) { return s.available; }).length;
                    statusEl.textContent = free + ' free slot(s) on this date. Availability shown below is indicative.';
                });
        }

        staff.addEventListener('change', function () {
            syncAvailabilityLink();
            refresh();
        });
        date.addEventListener('change', refresh);
        syncAvailabilityLink();
    }

    function baseUrl(path) {
        var base = document.querySelector('meta[name="base-url"]');
        var prefix = base ? base.getAttribute('content') : '';
        return prefix + path;
    }

    /* Visit history analytics (PROMPT 7): progressively render Chart.js charts
       from the JSON block emitted by the server. Charts are a progressive
       enhancement only — every chart has an accompanying table with the same
       figures, so the page remains fully usable without JavaScript. */
    var ANALYTICS_COLORS = ['#16324f', '#0d9488', '#7c3aed', '#c49a27', '#16a34a', '#dc2626', '#0891b2', '#9333ea'];

    function initAnalyticsCharts() {
        var dataEl = document.getElementById('analytics-chart-data');
        if (!dataEl || typeof window.Chart === 'undefined') {
            return;
        }
        var data;
        try {
            data = JSON.parse(dataEl.textContent);
        } catch (e) {
            data = null;
        }
        if (!data) {
            return;
        }
        var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var animation = reduceMotion ? false : 300;

        if (document.getElementById('chart-attendance') && data.attendance) {
            new Chart(document.getElementById('chart-attendance').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: data.attendance.labels,
                    datasets: [
                        { label: 'Visits', data: data.attendance.visits, backgroundColor: '#16324f' },
                        { label: 'Unique students', data: data.attendance.students, backgroundColor: '#0d9488' }
                    ]
                },
                options: { responsive: true, maintainAspectRatio: false, animation: animation }
            });
        }

        if (document.getElementById('chart-weekday') && data.weekday) {
            new Chart(document.getElementById('chart-weekday').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: data.weekday.labels,
                    datasets: [{ label: 'Visits', data: data.weekday.visits, backgroundColor: ANALYTICS_COLORS }]
                },
                options: { responsive: true, maintainAspectRatio: false, animation: animation }
            });
        }

        if (document.getElementById('chart-hourly') && data.hourly) {
            new Chart(document.getElementById('chart-hourly').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: data.hourly.labels,
                    datasets: [{ label: 'Visits', data: data.hourly.visits, backgroundColor: '#7c3aed' }]
                },
                options: { responsive: true, maintainAspectRatio: false, animation: animation }
            });
        }

        if (document.getElementById('chart-illness') && data.illness) {
            new Chart(document.getElementById('chart-illness').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: data.illness.labels,
                    datasets: [
                        { label: 'Visits', data: data.illness.visits, backgroundColor: '#c49a27' },
                        { label: 'Students', data: data.illness.students.map(function (v) { return v === 'N/A' ? null : v; }), backgroundColor: '#16a34a' }
                    ]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: animation,
                    scales: { x: { beginAtZero: true, ticks: { precision: 0 } } }
                }
            });
        }
    }

    function readCsrfToken() {
        var el = document.querySelector('meta[name="csrf-token"]');
        csrfToken = el ? el.getAttribute('content') : null;
    }

    function getCsrfToken() {
        return csrfToken;
    }

    /* Sends a fetch JSON request, attaching the CSRF token and the
       SameSite/HttpOnly session cookie automatically. Returns a promise
       resolving to the parsed JSON body. */
    function post(url, payload) {
        var options = {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify(payload || {})
        };
        if (csrfToken) {
            options.headers['X-CSRF-Token'] = csrfToken;
        }
        return fetch(url, options)
            .then(function (response) {
                return response.json().catch(function () { return null; });
            });
    }

    /* Force-reflow-safe focus helper for moving focus to main content. */
    function focusMain() {
        var main = document.getElementById('main-content');
        if (main) {
            main.setAttribute('tabindex', '-1');
            main.focus({ preventScroll: false });
        }
    }

    // DFP: avoid duplicate initialisation if the script is loaded twice.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    return {
        getCsrfToken: getCsrfToken,
        post: post,
        focusMain: focusMain
    };
})();