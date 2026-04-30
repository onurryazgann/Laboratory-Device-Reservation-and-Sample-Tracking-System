'use strict';

/**
 * Reservation page AJAX integrations.
 *
 * Çalıştığı sayfa:
 * - public/reserve.php
 *
 * Kullanılan API endpointleri:
 * - get-stations.php
 * - get-station-equipment.php
 * - get-day-slots.php
 * - check-availability.php
 * - create-reservation.php
 */

document.addEventListener('DOMContentLoaded', () => {
    const reservationPage = document.querySelector('[data-reservation-page="reserve"]');

    if (!reservationPage || !window.LabAjax) {
        return;
    }

    const DATE_WINDOW_DAYS = 15;
    const SLOT_DURATION_HOURS = 2;
    const VALID_START_HOURS = [8, 10, 12, 14, 16, 18, 20, 22];

    const selectionForm = document.getElementById('reservationSelectionForm');
    const labSelect = document.getElementById('lab_id');
    const stationSelect = document.getElementById('station_id');
    const stationSelectFeedback = document.getElementById('stationSelectFeedback');

    const stationEquipmentPanel = document.getElementById('stationEquipmentPanel');
    const stationEquipmentList = document.getElementById('stationEquipmentList');

    const reservationForm = document.getElementById('reservationForm');
    const reservationLabInput = document.getElementById('reservation_lab_id');
    const reservationStationInput = document.getElementById('reservation_station_id');

    const startTimeInput = document.getElementById('start_time');
    const endTimeInput = document.getElementById('end_time');
    const purposeInput = document.getElementById('purpose');

    const reservationDatePicker = document.getElementById('reservationDatePicker');
    const reservationSlotSection = document.getElementById('reservationSlotSection');
    const reservationSlotGrid = document.getElementById('reservationSlotGrid');
    const reservationSelectedSlot = document.getElementById('reservationSelectedSlot');
    const reservationSelectedSlotText = document.getElementById('reservationSelectedSlotText');

    const checkAvailabilityButton = document.getElementById('checkAvailabilityButton');
    const createReservationButton = document.getElementById('createReservationButton');
    const availabilityMessage = document.getElementById('availabilityMessage');

    let selectedDate = null;
    let selectedSlotButton = null;
    let lastAvailabilityState = null;
    let isSubmittingReservation = false;

    function isPositiveInteger(value) {
        return /^[1-9]\d*$/.test(String(value || ''));
    }

    function valueOf(field) {
        return field ? field.value.trim() : '';
    }

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

        const today = getTodayStart();
        const lastDate = getLastSelectableDateStart();

        return date >= today && date <= lastDate;
    }

    function formatShortDate(date) {
        return new Intl.DateTimeFormat('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric'
        }).format(date);
    }

    function formatSlotDateTime(dateTimeValue) {
        const date = new Date(dateTimeValue);

        if (Number.isNaN(date.getTime())) {
            return dateTimeValue || '-';
        }

        return new Intl.DateTimeFormat('en-US', {
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(date);
    }

    function setButtonLoading(button, loadingText) {
        if (!button) {
            return;
        }

        if (!button.dataset.originalText) {
            button.dataset.originalText = button.textContent.trim();
        }

        button.disabled = true;
        button.textContent = loadingText;
    }

    function resetButton(button) {
        if (!button) {
            return;
        }

        button.disabled = false;

        if (button.dataset.originalText) {
            button.textContent = button.dataset.originalText;
        }
    }

    function setStationFeedback(type, message) {
        if (!stationSelectFeedback) {
            return;
        }

        stationSelectFeedback.textContent = message;
        stationSelectFeedback.className = `field-feedback field-feedback-${type || 'info'}`;
    }

    function showAvailabilityMessage(type, message, extraHtml = '') {
        if (!availabilityMessage) {
            return;
        }

        const alertClass = type === 'success' ? 'alert-success' : 'alert-error';

        availabilityMessage.style.display = 'block';
        availabilityMessage.className = `alert ${alertClass} reservation-availability-message`;
        availabilityMessage.innerHTML = `
            <div>${window.LabAjax.escapeHtml(message)}</div>
            ${extraHtml}
        `;
    }

    function clearAvailabilityMessage() {
        if (!availabilityMessage) {
            return;
        }

        availabilityMessage.style.display = 'none';
        availabilityMessage.className = 'reservation-availability-message';
        availabilityMessage.innerHTML = '';
    }

    function resetAvailabilityState() {
        lastAvailabilityState = null;
        clearAvailabilityMessage();
    }

    function clearSelectedSlot() {
        if (selectedSlotButton) {
            selectedSlotButton.classList.remove('is-selected');
        }

        selectedSlotButton = null;

        if (startTimeInput) {
            startTimeInput.value = '';
        }

        if (endTimeInput) {
            endTimeInput.value = '';
        }

        if (reservationSelectedSlot) {
            reservationSelectedSlot.hidden = true;
        }

        if (reservationSelectedSlotText) {
            reservationSelectedSlotText.textContent = '-';
        }

        resetAvailabilityState();
    }

    function clearSlotGrid(message = 'Select a date to view available time slots.') {
        clearSelectedSlot();

        if (reservationSlotGrid) {
            reservationSlotGrid.innerHTML = `
                <div class="reservation-slot-empty">
                    ${window.LabAjax.escapeHtml(message)}
                </div>
            `;
        }

        if (reservationSlotSection) {
            reservationSlotSection.hidden = true;
        }
    }

    function resetDateAndSlotSelection() {
        selectedDate = null;
        clearSelectedSlot();

        if (reservationDatePicker) {
            const activeDateButtons = reservationDatePicker.querySelectorAll('.reservation-date-card.is-selected');
            activeDateButtons.forEach((button) => button.classList.remove('is-selected'));
        }

        clearSlotGrid();
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

        const now = new Date();

        if (startDate <= now) {
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
        const expectedDurationMs = SLOT_DURATION_HOURS * 60 * 60 * 1000;

        if (durationMs !== expectedDurationMs) {
            return {
                valid: false,
                message: 'Reservation slot must be exactly 2 hours.'
            };
        }

        const startHour = startDate.getHours();

        if (
            !VALID_START_HOURS.includes(startHour) ||
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

    function validateReservationFields() {
        let valid = true;

        const stationId = valueOf(reservationStationInput);
        const startTime = valueOf(startTimeInput);
        const endTime = valueOf(endTimeInput);

        if (!isPositiveInteger(stationId)) {
            valid = false;
            showAvailabilityMessage('error', 'Please select a valid station first.');
        }

        if (startTime === '' || endTime === '') {
            valid = false;
            showAvailabilityMessage('error', 'Please select a reservation date and time slot.');
        }

        if (startTime !== '' && endTime !== '') {
            const slotValidation = validateSlotClientSide(startTime, endTime);

            if (!slotValidation.valid) {
                valid = false;
                showAvailabilityMessage('error', slotValidation.message);
            }
        }

        if (purposeInput && valueOf(purposeInput).length > 255) {
            valid = false;
            window.LabAjax.setFieldState(purposeInput, 'error', 'Purpose can be maximum 255 characters.');
        } else if (purposeInput) {
            window.LabAjax.clearFieldState(purposeInput);
        }

        return valid;
    }

    function buildConflictHtml(conflicts) {
        if (!Array.isArray(conflicts) || conflicts.length === 0) {
            return '';
        }

        const rows = conflicts.map((conflict) => {
            const user = conflict.user_full_name || '-';
            const start = conflict.start_time || '-';
            const end = conflict.end_time || '-';
            const status = conflict.status || '-';

            return `
                <tr>
                    <td>${window.LabAjax.escapeHtml(user)}</td>
                    <td>${window.LabAjax.escapeHtml(start)}</td>
                    <td>${window.LabAjax.escapeHtml(end)}</td>
                    <td>${window.LabAjax.escapeHtml(status)}</td>
                </tr>
            `;
        }).join('');

        return `
            <div class="reservation-conflict-table-wrap">
                <table class="reservation-conflict-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Start</th>
                            <th>End</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rows}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderDatePicker() {
        if (!reservationDatePicker) {
            return;
        }

        const today = getTodayStart();
        let html = '';

        for (let index = 0; index < DATE_WINDOW_DAYS; index += 1) {
            const date = new Date(today);
            date.setDate(today.getDate() + index);

            const dateValue = toLocalDateValue(date);
            const isToday = index === 0;
            const label = formatShortDate(date);

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

        reservationDatePicker.innerHTML = html;
    }

    async function loadSlotsForDate(dateValue) {
        if (!reservationSlotGrid || !reservationSlotSection) {
            return;
        }

        const stationId = valueOf(reservationStationInput);

        clearSelectedSlot();

        if (!isPositiveInteger(stationId)) {
            showAvailabilityMessage('error', 'Please select a station first.');
            clearSlotGrid('Please select a station first.');
            return;
        }

        if (!isDateInsideReservationWindow(dateValue)) {
            showAvailabilityMessage('error', 'Please select a date within the next 15 days.');
            clearSlotGrid('Please select a valid date.');
            return;
        }

        reservationSlotSection.hidden = false;
        reservationSlotGrid.innerHTML = `
            <div class="reservation-slot-empty">
                Loading available slots...
            </div>
        `;

        try {
            const response = await window.LabAjax.get('get-day-slots.php', {
                station_id: stationId,
                date: dateValue
            });

            const slots = response.data && Array.isArray(response.data.slots)
                ? response.data.slots
                : [];

            renderSlotGrid(slots);
        } catch (error) {
            reservationSlotGrid.innerHTML = `
                <div class="reservation-slot-empty reservation-slot-error">
                    ${window.LabAjax.escapeHtml(error.message || 'Time slots could not be loaded.')}
                </div>
            `;

            window.LabAjax.showToast(error.message || 'Time slots could not be loaded.', 'error');
        }
    }

    function renderSlotGrid(slots) {
        if (!reservationSlotGrid) {
            return;
        }

        if (!Array.isArray(slots) || slots.length === 0) {
            reservationSlotGrid.innerHTML = `
                <div class="reservation-slot-empty">
                    No slot found for this date.
                </div>
            `;
            return;
        }

        const now = new Date();
        let activeSlotCount = 0;

        const slotButtons = slots.map((slot) => {
            const startTime = slot.start_time || '';
            const endTime = slot.end_time || '';
            const label = slot.label || `${formatSlotDateTime(startTime)} - ${formatSlotDateTime(endTime)}`;

            const startDate = new Date(startTime);
            const isPastSlot = !Number.isNaN(startDate.getTime()) && startDate <= now;
            const isAvailable = Boolean(slot.available) && !isPastSlot;

            if (isAvailable) {
                activeSlotCount += 1;
            }

            const unavailableReason = isPastSlot
                ? 'Past slot'
                : (slot.reason || slot.unavailable_reason || 'Unavailable');

            return `
                <button
                    type="button"
                    class="reservation-slot-card ${isAvailable ? 'is-available' : 'is-disabled'}"
                    data-start-time="${window.LabAjax.escapeHtml(startTime)}"
                    data-end-time="${window.LabAjax.escapeHtml(endTime)}"
                    data-label="${window.LabAjax.escapeHtml(label)}"
                    ${isAvailable ? '' : 'disabled'}
                >
                    <span class="reservation-slot-time">
                        ${window.LabAjax.escapeHtml(label)}
                    </span>
                    <span class="reservation-slot-status">
                        ${isAvailable ? 'Available' : window.LabAjax.escapeHtml(unavailableReason)}
                    </span>
                </button>
            `;
        }).join('');

        const emptyMessage = activeSlotCount === 0
            ? '<div class="reservation-slot-empty">No available slot remains for this date.</div>'
            : '';

        reservationSlotGrid.innerHTML = slotButtons + emptyMessage;
    }

    function selectDate(dateValue, clickedButton) {
        selectedDate = dateValue;

        if (reservationDatePicker) {
            const dateButtons = reservationDatePicker.querySelectorAll('.reservation-date-card');
            dateButtons.forEach((button) => button.classList.remove('is-selected'));
        }

        if (clickedButton) {
            clickedButton.classList.add('is-selected');
        }

        resetAvailabilityState();
        loadSlotsForDate(dateValue);
    }

    function selectSlot(slotButton) {
        if (!slotButton || slotButton.disabled) {
            return;
        }

        const startTime = slotButton.dataset.startTime || '';
        const endTime = slotButton.dataset.endTime || '';
        const label = slotButton.dataset.label || '';

        const slotValidation = validateSlotClientSide(startTime, endTime);

        if (!slotValidation.valid) {
            showAvailabilityMessage('error', slotValidation.message);
            return;
        }

        if (selectedSlotButton) {
            selectedSlotButton.classList.remove('is-selected');
        }

        selectedSlotButton = slotButton;
        selectedSlotButton.classList.add('is-selected');

        if (startTimeInput) {
            startTimeInput.value = startTime;
        }

        if (endTimeInput) {
            endTimeInput.value = endTime;
        }

        if (reservationSelectedSlot && reservationSelectedSlotText) {
            reservationSelectedSlot.hidden = false;
            reservationSelectedSlotText.textContent = label;
        }

        resetAvailabilityState();
    }

    async function loadStationsByLab() {
        if (!labSelect || !stationSelect) {
            return;
        }

        const labId = labSelect.value;

        resetDateAndSlotSelection();
        resetAvailabilityState();

        if (reservationLabInput) {
            reservationLabInput.value = isPositiveInteger(labId) ? labId : '';
        }

        if (reservationStationInput) {
            reservationStationInput.value = '';
        }

        stationSelect.innerHTML = '<option value="">Select station</option>';
        stationSelect.disabled = true;

        if (!isPositiveInteger(labId)) {
            stationSelect.innerHTML = '<option value="">Select laboratory first</option>';
            setStationFeedback('info', 'Select a laboratory to list available stations.');
            return;
        }

        setStationFeedback('info', 'Loading stations...');

        try {
            const response = await window.LabAjax.get('get-stations.php', {
                lab_id: labId
            });

            const stations = response.data && Array.isArray(response.data.stations)
                ? response.data.stations
                : [];

            stationSelect.innerHTML = '<option value="">Select station</option>';

            stations.forEach((station) => {
                const option = document.createElement('option');

                option.value = station.station_id;
                option.dataset.status = station.status || '';
                option.dataset.code = station.station_code || '';
                option.dataset.name = station.station_name || '';
                option.textContent = `${station.station_code || ''} - ${station.station_name || ''} (${station.status || '-'})`;

                if (station.status !== 'active') {
                    option.disabled = true;
                }

                stationSelect.appendChild(option);
            });

            stationSelect.disabled = false;

            if (stations.length === 0) {
                setStationFeedback('info', 'No station found for this laboratory.');
            } else {
                setStationFeedback('success', 'Stations loaded. Select an active station.');
            }
        } catch (error) {
            stationSelect.innerHTML = '<option value="">Stations could not be loaded</option>';
            stationSelect.disabled = false;
            setStationFeedback('error', error.message || 'Stations could not be loaded.');
        }
    }

    function renderEquipmentList(equipment) {
        if (!stationEquipmentList) {
            return;
        }

        if (!Array.isArray(equipment) || equipment.length === 0) {
            stationEquipmentList.innerHTML = `
                <div class="empty-state">
                    No equipment is assigned to this station.
                </div>
            `;
            return;
        }

        const items = equipment.map((item) => {
            const name = item.equipment_name || item.type_name || item.name || 'Equipment';
            const assetCode = item.asset_code || '-';
            const brand = item.brand || '-';
            const model = item.model || '-';
            const status = item.status || '-';

            return `
                <div class="equipment-item">
                    <div>
                        <strong>${window.LabAjax.escapeHtml(name)}</strong>
                        <p>
                            Asset: ${window.LabAjax.escapeHtml(assetCode)}
                            · Brand: ${window.LabAjax.escapeHtml(brand)}
                            · Model: ${window.LabAjax.escapeHtml(model)}
                        </p>
                    </div>
                    <span class="status-pill">
                        ${window.LabAjax.escapeHtml(status)}
                    </span>
                </div>
            `;
        }).join('');

        stationEquipmentList.innerHTML = items;
    }

    async function loadStationEquipment(stationId) {
        if (!stationEquipmentList || !isPositiveInteger(stationId)) {
            return;
        }

        stationEquipmentList.innerHTML = `
            <div class="empty-state">
                Loading station equipment...
            </div>
        `;

        try {
            const response = await window.LabAjax.get('get-station-equipment.php', {
                station_id: stationId
            });

            const equipment = response.data && Array.isArray(response.data.equipment)
                ? response.data.equipment
                : [];

            renderEquipmentList(equipment);
        } catch (error) {
            stationEquipmentList.innerHTML = `
                <div class="empty-state empty-state-error">
                    ${window.LabAjax.escapeHtml(error.message || 'Station equipment could not be loaded.')}
                </div>
            `;
        }
    }

    async function checkAvailability() {
        if (!reservationForm || !validateReservationFields()) {
            return false;
        }

        const payload = {
            station_id: valueOf(reservationStationInput),
            start_time: valueOf(startTimeInput),
            end_time: valueOf(endTimeInput)
        };

        setButtonLoading(checkAvailabilityButton, 'Checking...');
        resetButton(createReservationButton);

        try {
            const response = await window.LabAjax.post('check-availability.php', payload);
            const data = response.data || {};
            const available = Boolean(data.available);

            lastAvailabilityState = {
                checked: true,
                available,
                station_id: payload.station_id,
                start_time: payload.start_time,
                end_time: payload.end_time
            };

            if (available) {
                showAvailabilityMessage('success', 'This station is available for the selected time slot.');
                window.LabAjax.showToast('Station is available.', 'success');
            } else {
                showAvailabilityMessage(
                    'error',
                    'This station is not available for the selected time slot.',
                    buildConflictHtml(data.conflicts || [])
                );

                window.LabAjax.showToast('Selected time slot is not available.', 'error');
            }

            return available;
        } catch (error) {
            lastAvailabilityState = null;

            const conflicts = error.payload && error.payload.data
                ? error.payload.data.conflicts
                : [];

            showAvailabilityMessage(
                'error',
                error.message || 'Availability check failed.',
                buildConflictHtml(conflicts || [])
            );

            window.LabAjax.showToast(error.message || 'Availability check failed.', 'error');

            return false;
        } finally {
            resetButton(checkAvailabilityButton);
        }
    }

    function isCurrentAvailabilityStillValid() {
        if (!lastAvailabilityState || lastAvailabilityState.available !== true) {
            return false;
        }

        return (
            lastAvailabilityState.station_id === valueOf(reservationStationInput) &&
            lastAvailabilityState.start_time === valueOf(startTimeInput) &&
            lastAvailabilityState.end_time === valueOf(endTimeInput)
        );
    }

    async function createReservation() {
        if (!reservationForm || !validateReservationFields()) {
            return;
        }

        let available = isCurrentAvailabilityStillValid();

        if (!available) {
            available = await checkAvailability();
        }

        if (!available) {
            return;
        }

        const payload = {
            station_id: valueOf(reservationStationInput),
            start_time: valueOf(startTimeInput),
            end_time: valueOf(endTimeInput),
            purpose: purposeInput ? valueOf(purposeInput) : ''
        };

        setButtonLoading(createReservationButton, 'Creating...');
        setButtonLoading(checkAvailabilityButton, 'Please wait...');

        try {
            const response = await window.LabAjax.post('create-reservation.php', payload);
            const data = response.data || {};
            const reservationId = data.reservation_id || (data.reservation && data.reservation.reservation_id);

            showAvailabilityMessage(
                'success',
                reservationId
                    ? `Reservation created successfully. Reservation ID: ${reservationId}`
                    : 'Reservation created successfully.',
                `
                    <div class="reservation-success-link">
                        <a href="my-reservations.php">Go to My Reservations</a>
                    </div>
                `
            );

            window.LabAjax.showToast('Reservation created successfully.', 'success');

            reservationForm.reset();
            resetDateAndSlotSelection();
            lastAvailabilityState = null;

            if (reservationId) {
                window.setTimeout(() => {
                    window.location.href = 'my-reservations.php';
                }, 1200);
            }
        } catch (error) {
            const conflicts = error.payload && error.payload.data
                ? error.payload.data.conflicts
                : [];

            showAvailabilityMessage(
                'error',
                error.message || 'Reservation could not be created.',
                buildConflictHtml(conflicts || [])
            );

            window.LabAjax.showToast(error.message || 'Reservation could not be created.', 'error');
        } finally {
            resetButton(createReservationButton);
            resetButton(checkAvailabilityButton);
        }
    }

    if (labSelect && stationSelect) {
        labSelect.addEventListener('change', loadStationsByLab);
    }

    if (stationSelect) {
        stationSelect.addEventListener('change', () => {
            resetDateAndSlotSelection();
            resetAvailabilityState();

            const stationId = stationSelect.value;

            if (reservationStationInput) {
                reservationStationInput.value = isPositiveInteger(stationId) ? stationId : '';
            }

            if (stationEquipmentPanel) {
                stationEquipmentPanel.dataset.stationId = stationId;
            }

            if (isPositiveInteger(stationId)) {
                loadStationEquipment(stationId);
            }
        });
    }

    if (selectionForm) {
        selectionForm.addEventListener('submit', (event) => {
            if (!labSelect || !stationSelect) {
                return;
            }

            if (!isPositiveInteger(labSelect.value)) {
                event.preventDefault();
                setStationFeedback('error', 'Please select a laboratory.');
                window.LabAjax.showToast('Please select a laboratory.', 'error');
                return;
            }

            if (!isPositiveInteger(stationSelect.value)) {
                event.preventDefault();
                setStationFeedback('error', 'Please select an active station.');
                window.LabAjax.showToast('Please select an active station.', 'error');
            }
        });
    }

    if (reservationDatePicker) {
        reservationDatePicker.addEventListener('click', (event) => {
            const dateButton = event.target.closest('.reservation-date-card');

            if (!dateButton) {
                return;
            }

            const dateValue = dateButton.dataset.date || '';
            selectDate(dateValue, dateButton);
        });
    }

    if (reservationSlotGrid) {
        reservationSlotGrid.addEventListener('click', (event) => {
            const slotButton = event.target.closest('.reservation-slot-card');

            if (!slotButton) {
                return;
            }

            selectSlot(slotButton);
        });
    }

    if (purposeInput) {
        purposeInput.addEventListener('input', () => {
            if (valueOf(purposeInput).length > 255) {
                window.LabAjax.setFieldState(purposeInput, 'error', 'Purpose can be maximum 255 characters.');
            } else {
                window.LabAjax.clearFieldState(purposeInput);
            }
        });
    }

    if (checkAvailabilityButton) {
        checkAvailabilityButton.addEventListener('click', async (event) => {
            event.preventDefault();
            await checkAvailability();
        });
    }

    if (createReservationButton) {
        createReservationButton.addEventListener('click', async (event) => {
            event.preventDefault();

            if (isSubmittingReservation) {
                return;
            }

            isSubmittingReservation = true;

            try {
                await createReservation();
            } finally {
                isSubmittingReservation = false;
            }
        });
    }

    if (reservationForm) {
        reservationForm.addEventListener('submit', (event) => {
            event.preventDefault();
        });
    }

    renderDatePicker();
    clearSlotGrid();

    const initialStationId = stationEquipmentPanel
        ? stationEquipmentPanel.dataset.stationId
        : valueOf(reservationStationInput);

    if (isPositiveInteger(initialStationId)) {
        loadStationEquipment(initialStationId);
    }
});