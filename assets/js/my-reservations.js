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

                            var activeKpi = document.querySelector('[data-reservation-kpi="active"]');

                            if (activeKpi) {
                                activeKpi.textContent = Math.max(0, parseInt(activeKpi.textContent, 10) - 1);
                            }

                            var cancelledKpi = document.querySelector('[data-reservation-kpi="cancelled"]');

                            if (cancelledKpi) {
                                cancelledKpi.textContent = parseInt(cancelledKpi.textContent, 10) + 1;
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
