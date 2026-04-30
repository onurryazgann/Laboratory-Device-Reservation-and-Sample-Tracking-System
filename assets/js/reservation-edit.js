'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('reservationEditForm');

    if (!form || !window.LabAjax) {
        return;
    }

    const DATE_WINDOW_DAYS = 15;
    const VALID_START_HOURS = [8, 10, 12, 14, 16, 18, 20, 22];

    const reservationId = form.dataset.reservationId || '';
    const stationId = form.dataset.stationId || '';
    const currentStart = form.dataset.currentStart || '';
    const currentEnd = form.dataset.currentEnd || '';

    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');

    const datePicker = document.getElementById('reservationEditDatePicker');
    const slotSection = document.getElementById('reservationEditSlotSection');
    const slotGrid = document.getElementById('reservationEditSlotGrid');
    const selectedSlotBox = document.getElementById('reservationEditSelectedSlot');
    const selectedSlotText = document.getElementById('reservationEditSelectedSlotText');
    const clientMessage = document.getElementById('reservationEditClientMessage');

    let selectedSlotButton = null;

    function pad2(value) {
        return String(value).padStart(2, '0');
    }

    function toLocalDateValue(date) {
        return `${date.getFullYear()}-${pad2(date.getMonth() + 1)}-${pad2(date.getDate())}`;
    }

    function parseLocalDate(dateValue) {
        const parts = String(dateValue || '').split('-').map(Number);

        if (parts.length !== 3 || parts.some(Number.isNaN)) {
            return null;
        }

        return new Date(parts[0], parts[1] - 1, parts[2]);
    }

    function getTodayStart() {
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        return today;
    }

    function getLastSelectableDateStart() {
        const lastDate = getTodayStart();
        lastDate.setDate(lastDate.getDate() + DATE_WINDOW_DAYS - 1);
        return lastDate;
    }

    function isDateInsideReservationWindow(dateValue) {
        const date = parseLocalDate(dateValue);

        if (!date) {
            return false;
        }

        return date >= getTodayStart() && date <= getLastSelectableDateStart();
    }

    function formatShortDate(date) {
        return new Intl.DateTimeFormat('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric'
        }).format(date);
    }

    function showClientMessage(type, message) {
        if (!clientMessage) {
            return;
        }

        const alertClass = type === 'success' ? 'alert-success' : 'alert-error';

        clientMessage.style.display = 'block';
        clientMessage.className = `alert ${alertClass} reservation-availability-message`;
        clientMessage.textContent = message;
    }

    function clearClientMessage() {
        if (!clientMessage) {
            return;
        }

        clientMessage.style.display = 'none';
        clientMessage.textContent = '';
        clientMessage.className = 'reservation-availability-message';
    }

    function clearSelectedSlot() {
        if (selectedSlotButton) {
            selectedSlotButton.classList.remove('is-selected');
        }

        selectedSlotButton = null;

        if (selectedSlotBox) {
            selectedSlotBox.hidden = true;
        }

        if (selectedSlotText) {
            selectedSlotText.textContent = '-';
        }
    }

    function validateSlotClientSide(startTime, endTime) {
        const startDate = new Date(startTime);
        const endDate = new Date(endTime);

        if (Number.isNaN(startDate.getTime()) || Number.isNaN(endDate.getTime())) {
            return {
                valid: false,
                message: 'Please select a valid reservation slot.'
            };
        }

        if (startDate <= new Date()) {
            return {
                valid: false,
                message: 'Reservation start time must be in the future.'
            };
        }

        const startDateValue = toLocalDateValue(startDate);

        if (!isDateInsideReservationWindow(startDateValue)) {
            return {
                valid: false,
                message: 'Reservation date must be within the next 15 days.'
            };
        }

        const durationMs = endDate.getTime() - startDate.getTime();

        if (durationMs !== 2 * 60 * 60 * 1000) {
            return {
                valid: false,
                message: 'Reservation duration must be exactly 2 hours.'
            };
        }

        if (
            !VALID_START_HOURS.includes(startDate.getHours()) ||
            startDate.getMinutes() !== 0 ||
            startDate.getSeconds() !== 0
        ) {
            return {
                valid: false,
                message: 'Reservation must start at a valid 2-hour slot.'
            };
        }

        return {
            valid: true,
            message: ''
        };
    }

    function renderDatePicker() {
        if (!datePicker) {
            return;
        }

        const today = getTodayStart();
        let html = '';

        for (let index = 0; index < DATE_WINDOW_DAYS; index += 1) {
            const date = new Date(today);
            date.setDate(today.getDate() + index);

            const dateValue = toLocalDateValue(date);
            const label = formatShortDate(date);
            const isToday = index === 0;

            html += `
                <button
                    type="button"
                    class="reservation-date-card"
                    data-date="${dateValue}"
                    aria-label="Select ${window.LabAjax.escapeHtml(label)}"
                >
                    <span class="reservation-date-card-day">
                        ${window.LabAjax.escapeHtml(label)}
                    </span>
                    <span class="reservation-date-card-meta">
                        ${isToday ? 'Today' : dateValue}
                    </span>
                </button>
            `;
        }

        datePicker.innerHTML = html;
    }

    function renderSlotGrid(slots) {
        if (!slotGrid) {
            return;
        }

        if (!Array.isArray(slots) || slots.length === 0) {
            slotGrid.innerHTML = `
                <div class="reservation-slot-empty">
                    No slot found for this date.
                </div>
            `;
            return;
        }

        const now = new Date();

        slotGrid.innerHTML = slots.map((slot) => {
            const startTime = slot.start_time || '';
            const endTime = slot.end_time || '';
            const label = slot.label || '';
            const startDate = new Date(startTime);
            const isPastSlot = !Number.isNaN(startDate.getTime()) && startDate <= now;
            const isAvailable = Boolean(slot.available) && !isPastSlot;

            const currentStartComparable = currentStart.length === 16
                ? `${currentStart}:00`
                : currentStart;

            const isCurrentReservationSlot = startTime === currentStartComparable;

            const reason = isPastSlot
                ? 'Past slot'
                : (slot.reason || 'Unavailable');

            return `
                <button
                    type="button"
                    class="reservation-slot-card ${isAvailable ? 'is-available' : 'is-disabled'} ${isCurrentReservationSlot && isAvailable ? 'is-current-slot' : ''}"
                    data-start-time="${window.LabAjax.escapeHtml(startTime)}"
                    data-end-time="${window.LabAjax.escapeHtml(endTime)}"
                    data-label="${window.LabAjax.escapeHtml(label)}"
                    ${isAvailable ? '' : 'disabled'}
                >
                    <span class="reservation-slot-time">
                        ${window.LabAjax.escapeHtml(label)}
                    </span>
                    <span class="reservation-slot-status">
                        ${isCurrentReservationSlot && isAvailable ? 'Current' : (isAvailable ? 'Available' : window.LabAjax.escapeHtml(reason))}
                    </span>
                </button>
            `;
        }).join('');
    }

    async function loadSlotsForDate(dateValue) {
        if (!slotSection || !slotGrid) {
            return;
        }

        clearSelectedSlot();
        clearClientMessage();

        if (!isDateInsideReservationWindow(dateValue)) {
            showClientMessage('error', 'Please select a date within the next 15 days.');
            return;
        }

        slotSection.hidden = false;
        slotGrid.innerHTML = `
            <div class="reservation-slot-empty">
                Loading available slots...
            </div>
        `;

        try {
            const response = await window.LabAjax.get('get-day-slots.php', {
                station_id: stationId,
                date: dateValue,
                exclude_reservation_id: reservationId
            });

            const slots = response.data && Array.isArray(response.data.slots)
                ? response.data.slots
                : [];

            renderSlotGrid(slots);
        } catch (error) {
            slotGrid.innerHTML = `
                <div class="reservation-slot-empty reservation-slot-error">
                    ${window.LabAjax.escapeHtml(error.message || 'Time slots could not be loaded.')}
                </div>
            `;

            if (window.LabAjax.showToast) {
                window.LabAjax.showToast(error.message || 'Time slots could not be loaded.', 'error');
            }
        }
    }

    function selectDate(dateValue, button) {
        const buttons = datePicker.querySelectorAll('.reservation-date-card');

        buttons.forEach((item) => item.classList.remove('is-selected'));

        if (button) {
            button.classList.add('is-selected');
        }

        loadSlotsForDate(dateValue);
    }

    function selectSlot(button) {
        if (!button || button.disabled) {
            return;
        }

        const startTime = button.dataset.startTime || '';
        const endTime = button.dataset.endTime || '';
        const label = button.dataset.label || '';

        const validation = validateSlotClientSide(startTime, endTime);

        if (!validation.valid) {
            showClientMessage('error', validation.message);
            return;
        }

        if (selectedSlotButton) {
            selectedSlotButton.classList.remove('is-selected');
        }

        selectedSlotButton = button;
        selectedSlotButton.classList.add('is-selected');

        startTimeInput.value = startTime;
        endTimeInput.value = endTime;

        if (selectedSlotBox && selectedSlotText) {
            selectedSlotBox.hidden = false;
            selectedSlotText.textContent = label;
        }

        clearClientMessage();
    }

    datePicker.addEventListener('click', (event) => {
        const button = event.target.closest('.reservation-date-card');

        if (!button) {
            return;
        }

        selectDate(button.dataset.date || '', button);
    });

    slotGrid.addEventListener('click', (event) => {
        const button = event.target.closest('.reservation-slot-card');

        if (!button) {
            return;
        }

        selectSlot(button);
    });

    form.addEventListener('submit', (event) => {
        const startTime = startTimeInput.value;
        const endTime = endTimeInput.value;

        const validation = validateSlotClientSide(startTime, endTime);

        if (!validation.valid) {
            event.preventDefault();
            showClientMessage('error', validation.message);

            if (window.LabAjax.showToast) {
                window.LabAjax.showToast(validation.message, 'error');
            }
        }
    });

    renderDatePicker();

    if (currentStart) {
        const currentDate = currentStart.slice(0, 10);

        if (isDateInsideReservationWindow(currentDate)) {
            const dateButton = datePicker.querySelector(`[data-date="${currentDate}"]`);

            if (dateButton) {
                selectDate(currentDate, dateButton);
            }
        }
    }
});