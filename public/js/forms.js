/**
 * forms.js
 *
 * Reusable form-building helpers shared between WorkersUI and ContractsUI.
 *
 * Responsibilities:
 *  - Build form field HTML from a declarative schema
 *  - Collect form data into a plain object
 *  - Display / clear server-side validation errors
 *  - Provide a simple toast notification system
 *
 * All helpers are attached to the global `window.Forms` namespace.
 */

'use strict';

(function (global) {

  // -------------------------------------------------------------------------
  // Toast notifications
  // -------------------------------------------------------------------------

  /**
   * Show a transient notification at the bottom-right of the screen.
   *
   * @param {string} message
   * @param {'success'|'error'|'info'} [type]
   * @param {number} [duration]  Milliseconds before auto-dismiss
   */
  function toast(message, type = 'info', duration = 3500) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const el = document.createElement('div');
    el.className = `toast toast-${type}`;
    el.textContent = message;

    container.appendChild(el);

    setTimeout(() => {
      el.style.opacity = '0';
      el.style.transform = 'translateX(20px)';
      el.style.transition = 'opacity .3s, transform .3s';
      setTimeout(() => el.remove(), 350);
    }, duration);
  }

  // -------------------------------------------------------------------------
  // Generic form data collector
  // -------------------------------------------------------------------------

  /**
   * Collect all form input / select / textarea values from a container.
   *
   * @param {HTMLElement} container
   * @returns {object}  name → value map (strings only)
   */
  function collectFormData(container) {
    const data = {};
    const elements = container.querySelectorAll('[name]');
    elements.forEach(el => {
      const name  = el.getAttribute('name');
      const value = el.value ?? '';
      data[name]  = value.trim();
    });
    return data;
  }

  // -------------------------------------------------------------------------
  // Validation error display
  // -------------------------------------------------------------------------

  /**
   * Mark fields as invalid and show error messages.
   *
   * @param {HTMLElement} container
   * @param {string[]}    errors    List of "field_name: message" strings
   */
  function showErrors(container, errors) {
    clearErrors(container);

    errors.forEach(msg => {
      // Try to match "Field 'field_name' is required." pattern
      const match = msg.match(/Field '([^']+)'/);
      const fieldName = match ? match[1] : null;

      if (fieldName) {
        const input = container.querySelector(`[name="${fieldName}"]`);
        if (input) {
          input.classList.add('is-invalid');
          const errEl = document.createElement('p');
          errEl.className = 'form-error';
          errEl.textContent = msg;
          input.closest('.form-group')?.appendChild(errEl);
          return;
        }
      }

      // Generic error shown as toast
      toast(msg, 'error', 5000);
    });
  }

  /**
   * Remove all validation error indicators from a container.
   */
  function clearErrors(container) {
    container.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    container.querySelectorAll('.form-error').forEach(el => el.remove());
  }

  // -------------------------------------------------------------------------
  // Confirm modal
  // -------------------------------------------------------------------------

  /**
   * Show a modal asking for confirmation before a destructive action.
   *
   * @param {object} options
   * @param {string} options.title
   * @param {string} options.body
   * @param {string} [options.confirmLabel]
   * @returns {Promise<boolean>}  Resolves true if confirmed, false if cancelled
   */
  function confirm({ title, body, confirmLabel = 'Confirmer' }) {
    return new Promise(resolve => {
      const overlay    = document.getElementById('modal-overlay');
      const titleEl    = document.getElementById('modal-title');
      const bodyEl     = document.getElementById('modal-body');
      const cancelBtn  = document.getElementById('modal-cancel');
      const confirmBtn = document.getElementById('modal-confirm');
      const closeBtn   = overlay.querySelector('.modal-close');

      titleEl.textContent    = title;
      bodyEl.textContent     = body;
      confirmBtn.textContent = confirmLabel;

      overlay.classList.remove('hidden');

      const cleanup = (result) => {
        overlay.classList.add('hidden');
        cancelBtn.removeEventListener('click', onCancel);
        confirmBtn.removeEventListener('click', onConfirm);
        closeBtn.removeEventListener('click', onCancel);
        resolve(result);
      };

      const onCancel  = () => cleanup(false);
      const onConfirm = () => cleanup(true);

      cancelBtn.addEventListener('click', onCancel);
      confirmBtn.addEventListener('click', onConfirm);
      closeBtn.addEventListener('click', onCancel);
    });
  }

  // -------------------------------------------------------------------------
  // Field builder helpers
  // -------------------------------------------------------------------------

  /**
   * Create a form-group div for a text / date / email / number input.
   *
   * @param {object} opts
   * @param {string} opts.name
   * @param {string} opts.label
   * @param {string} [opts.type]      Default 'text'
   * @param {boolean} [opts.required]
   * @param {string} [opts.value]
   * @param {string} [opts.placeholder]
   * @returns {HTMLElement}
   */
  function makeInputField({ name, label, type = 'text', required = false, value = '', placeholder = '' }) {
    const group = document.createElement('div');
    group.className = 'form-group';

    const lbl = document.createElement('label');
    lbl.className = 'form-label';
    lbl.setAttribute('for', `field-${name}`);
    lbl.innerHTML = escHtml(label) + (required ? ' <span class="required">*</span>' : '');

    const input = document.createElement('input');
    input.className   = 'form-input';
    input.type        = type;
    input.name        = name;
    input.id          = `field-${name}`;
    input.value       = value;
    input.placeholder = placeholder;
    if (required) input.required = true;

    group.appendChild(lbl);
    group.appendChild(input);
    return group;
  }

  /**
   * Create a form-group div for a <select>.
   *
   * @param {object} opts
   * @param {string}   opts.name
   * @param {string}   opts.label
   * @param {Array<{value:string,label:string}>} opts.options
   * @param {boolean}  [opts.required]
   * @param {string}   [opts.value]      Pre-selected value
   * @returns {HTMLElement}
   */
  function makeSelectField({ name, label, options, required = false, value = '' }) {
    const group = document.createElement('div');
    group.className = 'form-group';

    const lbl = document.createElement('label');
    lbl.className = 'form-label';
    lbl.setAttribute('for', `field-${name}`);
    lbl.innerHTML = escHtml(label) + (required ? ' <span class="required">*</span>' : '');

    const sel = document.createElement('select');
    sel.className = 'form-select';
    sel.name      = name;
    sel.id        = `field-${name}`;
    if (required) sel.required = true;

    options.forEach(opt => {
      const o = document.createElement('option');
      o.value       = opt.value;
      o.textContent = opt.label;
      if (opt.value === value) o.selected = true;
      sel.appendChild(o);
    });

    group.appendChild(lbl);
    group.appendChild(sel);
    return group;
  }

  // -------------------------------------------------------------------------
  // Utility
  // -------------------------------------------------------------------------

  function escHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // -------------------------------------------------------------------------
  // Expose
  // -------------------------------------------------------------------------

  global.Forms = {
    toast,
    collectFormData,
    showErrors,
    clearErrors,
    confirm,
    makeInputField,
    makeSelectField,
    escHtml,
  };

}(window));
