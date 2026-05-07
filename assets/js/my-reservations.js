'use strict';

document.addEventListener('DOMContentLoaded', function () {
    var forms = document.querySelectorAll('.js-cancel-reservation-form');

    if (forms.length === 0) {
        return;
    }

    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var reservationId = form.getAttribute('data-reservation-id');

            if (!confirm('Are you sure you want to cancel this reservation?')) {
                return;
            }

            var button = form.querySelector('button[type="submit"]');
            var originalText = button.textContent;

            button.disabled = true;
            button.textContent = 'Cancelling...';

            window.LabAjax.post('cancel-reservation.php', { reservation_id: reservationId })
                .then(function (response) {
                    if (response.success) {
                        var card = document.querySelector('[data-reservation-card="' + reservationId + '"]');

                        if (card) {
                            var badge = card.querySelector('[data-reservation-status="' + reservationId + '"]');

                            if (badge) {
                                badge.textContent = 'Cancelled';
                                badge.className = 'badge badge-error';
                            }

                            form.style.display = 'none';

                            var editLink = card.querySelector('.my-reservation-actions a.btn-secondary');

                            if (editLink) {
                                editLink.style.display = 'none';
                            }

                            if (response.data && response.data.summary) {
                                var summary = response.data.summary;
                                var kpiTotal = document.querySelector('[data-reservation-kpi="total"]');
                                var kpiActive = document.querySelector('[data-reservation-kpi="active"]');
                                var kpiCancelled = document.querySelector('[data-reservation-kpi="cancelled"]');
                                var kpiCompleted = document.querySelector('[data-reservation-kpi="completed"]');
                                if (kpiTotal && summary.total !== undefined) kpiTotal.textContent = summary.total;
                                if (kpiActive && summary.active !== undefined) kpiActive.textContent = summary.active;
                                if (kpiCancelled && summary.cancelled !== undefined) kpiCancelled.textContent = summary.cancelled;
                                if (kpiCompleted && summary.completed !== undefined) kpiCompleted.textContent = summary.completed;

                                var activeTabCount = document.querySelector('.my-reservations-tab[href*="status=active"] span');
                                var cancelledTabCount = document.querySelector('.my-reservations-tab[href*="status=cancelled"] span');
                                var allTabCount = document.querySelector('.my-reservations-tab[href*="status=all"] span');
                                var completedTabCount = document.querySelector('.my-reservations-tab[href*="status=completed"] span');
                                if (activeTabCount && summary.active !== undefined) activeTabCount.textContent = summary.active;
                                if (cancelledTabCount && summary.cancelled !== undefined) cancelledTabCount.textContent = summary.cancelled;
                                if (allTabCount && summary.total !== undefined) allTabCount.textContent = summary.total;
                                if (completedTabCount && summary.completed !== undefined) completedTabCount.textContent = summary.completed;
                            } else {
                                var activeKpi = document.querySelector('[data-reservation-kpi="active"]');
                                if (activeKpi) {
                                    activeKpi.textContent = Math.max(0, parseInt(activeKpi.textContent, 10) - 1);
                                }

                                var cancelledKpi = document.querySelector('[data-reservation-kpi="cancelled"]');
                                if (cancelledKpi) {
                                    cancelledKpi.textContent = parseInt(cancelledKpi.textContent, 10) + 1;
                                }

                                var activeTabCount = document.querySelector('.my-reservations-tab[href*="status=active"] span');
                                if (activeTabCount) {
                                    activeTabCount.textContent = Math.max(0, parseInt(activeTabCount.textContent, 10) - 1);
                                }

                                var cancelledTabCount = document.querySelector('.my-reservations-tab[href*="status=cancelled"] span');
                                if (cancelledTabCount) {
                                    cancelledTabCount.textContent = parseInt(cancelledTabCount.textContent, 10) + 1;
                                }
                            }
                        }

                        window.LabAjax.showToast(window.LabAjax.escapeHtml(response.message), 'success');
                    }
                })
                .catch(function (error) {
                    window.LabAjax.showToast(window.LabAjax.escapeHtml(error.message || 'Cancellation failed.'), 'error');
                    button.disabled = false;
                    button.textContent = originalText;
                });
        });
    });
});
