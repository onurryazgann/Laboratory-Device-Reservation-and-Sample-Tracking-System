'use strict';

/**
 * Global AJAX helper for the Laboratory Reservation System.
 *
 * Bu dosya backend/database tarafına dokunmaz.
 * Sadece mevcut public/api endpoint'lerine güvenli fetch istekleri gönderir.
 */

(function (window, document) {
  const DEFAULT_TIMEOUT = 15000;

  function getCsrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function isAdminArea() {
    return window.location.pathname.includes('/admin/');
  }

  function apiPath(endpoint) {
    if (!endpoint) {
      throw new Error('API endpoint is required.');
    }

    if (endpoint.startsWith('http://') || endpoint.startsWith('https://') || endpoint.startsWith('/')) {
      return endpoint;
    }

    const cleanEndpoint = endpoint.replace(/^api\//, '');
    return (isAdminArea() ? '../api/' : 'api/') + cleanEndpoint;
  }

  function toQueryString(params = {}) {
    const searchParams = new URLSearchParams();

    Object.keys(params).forEach((key) => {
      const value = params[key];

      if (value !== undefined && value !== null && value !== '') {
        searchParams.append(key, value);
      }
    });

    return searchParams.toString();
  }

  function toFormBody(data = {}) {
    const body = new URLSearchParams();

    Object.keys(data).forEach((key) => {
      const value = data[key];

      if (value !== undefined && value !== null) {
        body.append(key, value);
      }
    });

    return body;
  }

  function timeoutPromise(ms) {
    return new Promise((_, reject) => {
      window.setTimeout(() => {
        reject(new Error('Request timeout. Please try again.'));
      }, ms);
    });
  }

  async function parseJsonResponse(response) {
    let payload;

    try {
      payload = await response.json();
    } catch (error) {
      throw new Error('Invalid server response.');
    }

    if (!response.ok || payload.success === false) {
      const message = payload && payload.message
        ? payload.message
        : 'Request failed.';

      const customError = new Error(message);
      customError.status = response.status;
      customError.payload = payload;
      throw customError;
    }

    return payload;
  }

  async function request(url, options = {}) {
    const { headers: extraHeaders, ...restOptions } = options;

    const fetchPromise = fetch(url, {
      credentials: 'same-origin',
      headers: {
        'X-Requested-With': 'XMLHttpRequest',
        ...(extraHeaders || {})
      },
      ...restOptions
    }).then(parseJsonResponse);

    return Promise.race([
      fetchPromise,
      timeoutPromise(options.timeout || DEFAULT_TIMEOUT)
    ]);
  }

  async function get(endpoint, params = {}, options = {}) {
    const query = toQueryString(params);
    const url = apiPath(endpoint) + (query ? `?${query}` : '');

    return request(url, {
      method: 'GET',
      ...options
    });
  }

  async function post(endpoint, data = {}, options = {}) {
    var csrfToken = getCsrfToken();
    var { headers: extraHeaders, ...restOptions } = options;

    return request(apiPath(endpoint), {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        ...(extraHeaders || {}),
        ...(csrfToken ? { 'X-CSRF-Token': csrfToken } : {})
      },
      body: toFormBody(data),
      ...restOptions
    });
  }

  function debounce(callback, delay = 400) {
    let timerId;

    return function (...args) {
      window.clearTimeout(timerId);

      timerId = window.setTimeout(() => {
        callback.apply(this, args);
      }, delay);
    };
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function getFieldFeedbackElement(field) {
    const formGroup = field.closest('.form-group') || field.parentElement;

    if (!formGroup) {
      return null;
    }

    let feedback = formGroup.querySelector('.field-feedback');

    if (!feedback) {
      feedback = document.createElement('small');
      feedback.className = 'field-feedback';
      formGroup.appendChild(feedback);
    }

    return feedback;
  }

  function setFieldState(field, type, message = '') {
    if (!field) {
      return;
    }

    const feedback = getFieldFeedbackElement(field);

    field.classList.remove('is-valid', 'is-invalid', 'is-info');

    if (type === 'success') {
      field.classList.add('is-valid');
    }

    if (type === 'error') {
      field.classList.add('is-invalid');
    }

    if (type === 'info') {
      field.classList.add('is-info');
    }

    if (feedback) {
      feedback.textContent = message;
      feedback.className = `field-feedback field-feedback-${type || 'default'}`;
    }
  }

  function clearFieldState(field) {
    if (!field) {
      return;
    }

    field.classList.remove('is-valid', 'is-invalid', 'is-info');

    const feedback = getFieldFeedbackElement(field);

    if (feedback) {
      feedback.textContent = '';
      feedback.className = 'field-feedback';
    }
  }

  function showToast(message, type = 'info') {
    let container = document.querySelector('.app-toast-container');

    if (!container) {
      container = document.createElement('div');
      container.className = 'app-toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `app-toast app-toast-${type}`;
    toast.textContent = message;

    container.appendChild(toast);

    window.setTimeout(() => {
      toast.classList.add('is-hidden');

      window.setTimeout(() => {
        toast.remove();
      }, 250);
    }, 3500);
  }

  window.LabAjax = {
    apiPath,
    get,
    post,
    debounce,
    escapeHtml,
    setFieldState,
    clearFieldState,
    showToast
  };
})(window, document);