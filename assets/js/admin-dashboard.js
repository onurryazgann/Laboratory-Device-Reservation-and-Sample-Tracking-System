'use strict';

document.addEventListener('DOMContentLoaded', function () {
    if (!document.querySelector('[data-dashboard-stat]')) {
        return;
    }

    function updateDashboardUI(data) {
        var stats = document.querySelectorAll('[data-dashboard-stat]');
        stats.forEach(function (el) {
            var key = el.getAttribute('data-dashboard-stat');
            if (data[key] !== undefined) {
                el.textContent = data[key];
            }
        });

        var tbody = document.getElementById('latestReservationsBody');
        if (!tbody || !data.latest_reservations) {
            return;
        }

        var statusClasses = {
            active: 'badge-success',
            cancelled: 'badge-warning',
            completed: 'badge-info'
        };

        var rows = '';
        data.latest_reservations.forEach(function (r) {
            var status = (r.status || '').toLowerCase();
            var badgeClass = statusClasses[status] || 'badge-info';
            rows +=
                '<tr>' +
                    '<td>' + window.LabAjax.escapeHtml(r.user_full_name) + '</td>' +
                    '<td>' + window.LabAjax.escapeHtml(r.lab_name) + '</td>' +
                    '<td>' + window.LabAjax.escapeHtml(r.station_code + ' - ' + r.station_name) + '</td>' +
                    '<td>' + window.LabAjax.escapeHtml(r.start_time) + '</td>' +
                    '<td>' + window.LabAjax.escapeHtml(r.end_time) + '</td>' +
                    '<td><span class="badge ' + window.LabAjax.escapeHtml(badgeClass) + '">' + window.LabAjax.escapeHtml(r.status) + '</span></td>' +
                '</tr>';
        });
        tbody.innerHTML = rows;
    }

    function fetchDashboardStats() {
        try {
            window.LabAjax.get('admin-dashboard-stats.php')
                .then(function (response) {
                    if (response.success) {
                        updateDashboardUI(response.data);
                    }
                })
                .catch(function (err) {
                    console.error('Dashboard stats fetch failed:', err);
                });
        } catch (err) {
            console.error('Dashboard stats fetch failed:', err);
        }
    }

    fetchDashboardStats();
    setInterval(fetchDashboardStats, 15000);
});
