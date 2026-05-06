'use strict';

document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('.js-admin-reservation-status-form');

    if (!forms.length || !window.LabAjax) {
        return;
    }

    function getUrlParam(name) {
        var params = new URLSearchParams(window.location.search);
        return params.get(name) || '';
    }

    function capitalize(str) {
        return String(str || '').charAt(0).toUpperCase() + String(str || '').slice(1);
    }

    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var reservationId = form.getAttribute('data-reservation-id');
            var select = form.querySelector('select[name="new_status"]');
            var newStatus = select ? select.value : '';

            if (!reservationId || !newStatus) {
                return;
            }

            if (!confirm('Are you sure you want to update this reservation status?')) {
                return;
            }

            var payload = {
                reservation_id: reservationId,
                new_status: newStatus,
                status: getUrlParam('status'),
                lab_id: getUrlParam('lab_id'),
                q: getUrlParam('q'),
                date_from: getUrlParam('date_from'),
                date_to: getUrlParam('date_to')
            };

            var button = form.querySelector('button[type="submit"]');
            var originalText = '';

            if (button) {
                originalText = button.textContent;
                button.disabled = true;
                button.textContent = 'Updating...';
            }

            window.LabAjax.post('admin-update-reservation-status.php', payload)
                .then(function (response) {
                    var badge = document.querySelector('[data-admin-reservation-status="' + reservationId + '"]');

                    if (badge) {
                        badge.textContent = capitalize(newStatus);

                        if (newStatus === 'active') {
                            badge.className = 'badge badge-success';
                        } else if (newStatus === 'cancelled') {
                            badge.className = 'badge badge-warning';
                        } else if (newStatus === 'completed') {
                            badge.className = 'badge badge-secondary';
                        }
                    }

                    var actionCell = form.parentElement;

                    if (actionCell) {
                        actionCell.innerHTML = '<span style="color:var(--color-muted);">Locked</span>';
                    }

                    if (response.data && response.data.summary) {
                        var summary = response.data.summary;
                        var kpiTotal = document.querySelector('[data-admin-reservation-kpi="total"]');
                        var kpiActive = document.querySelector('[data-admin-reservation-kpi="active"]');
                        var kpiCancelled = document.querySelector('[data-admin-reservation-kpi="cancelled"]');
                        var kpiCompleted = document.querySelector('[data-admin-reservation-kpi="completed"]');

                        if (kpiTotal && summary.total !== undefined) {
                            kpiTotal.textContent = summary.total;
                        }

                        if (kpiActive && summary.active !== undefined) {
                            kpiActive.textContent = summary.active;
                        }

                        if (kpiCancelled && summary.cancelled !== undefined) {
                            kpiCancelled.textContent = summary.cancelled;
                        }

                        if (kpiCompleted && summary.completed !== undefined) {
                            kpiCompleted.textContent = summary.completed;
                        }
                    }

                    window.LabAjax.showToast('Reservation status updated successfully.', 'success');
                })
                .catch(function (error) {
                    window.LabAjax.showToast(error.message || 'Reservation status update failed.', 'error');

                    if (button) {
                        button.disabled = false;
                        button.textContent = originalText;
                    }
                });
        });
    });
});
