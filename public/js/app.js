/**
 * app.js
 *
 * Application bootstrap and navigation controller.
 *
 * Implements a single-page navigation model backed by the browser history API
 * (pushState / popstate) so the URL reflects the current view.
 *
 * Navigation state machine:
 *   contracts        ← default
 *   workers
 *   contract-form    (id optional)
 *   worker-form      (id optional)
 *   preview          (html required)
 *
 * Usage from other modules:
 *   App.navigateTo('contract-form', { id: '...' });
 *   App.navigateTo('preview', { html: '<div>…</div>' });
 *
 * Depends on: api.js, forms.js, workers.js, contracts.js
 */

'use strict';

(function (global) {

  // -------------------------------------------------------------------------
  // State
  // -------------------------------------------------------------------------

  let _currentView  = 'contracts';
  let _previewState = null; // { html, contractId?, source? }

  // -------------------------------------------------------------------------
  // Navigation
  // -------------------------------------------------------------------------

  /**
   * Navigate to a named view.
   *
   * @param {string} view   One of: contracts | workers | contract-form | worker-form | preview
   * @param {object} [data] Extra data for the view (id, html, …)
   */
  function navigateTo(view, data = {}) {
    _currentView = view;

    // Update sidebar active state
    document.querySelectorAll('.nav-item').forEach(el => {
      el.classList.remove('active');
      el.removeAttribute('aria-current');
    });

    // Hide all views
    document.querySelectorAll('.view').forEach(el => el.classList.remove('active'));

    switch (view) {

      case 'contracts':
        activateNavItem('contracts');
        showView('view-contracts');
        ContractsUI.renderList(document.getElementById('contract-status-filter')?.value || '');
        break;

      case 'workers':
        activateNavItem('workers');
        showView('view-workers');
        WorkersUI.renderList();
        break;

      case 'contract-form':
          activateNavItem('contracts');
          showView('view-contract-form');
          ContractsUI.renderForm(data.id || null, data.blank || false);
          break;

      case 'worker-form':
        activateNavItem('workers');
        showView('view-worker-form');
        WorkersUI.renderForm(data.id || null);
        break;
      
      

      case 'preview': {
        activateNavItem('contracts');
        showView('view-preview');

        if (data.html) {
          _previewState = data;
          renderPreview(data.html, 'both');
        }
        break;
      }

      default:
        navigateTo('contracts');
    }
  }

  // -------------------------------------------------------------------------
  // Preview rendering
  // -------------------------------------------------------------------------

  function renderPreview(html, language) {
    const content = document.getElementById('preview-content');
    if (!content) return;

    // The server already rendered the contract-document wrapper.
    // We wrap it in the .preview-wrap styling. If language filtering is
    // requested client-side, toggle visibility of .contract-column elements.
    content.innerHTML = html;

    const nlCol = content.querySelector('.contract-nl');
    const enCol = content.querySelector('.contract-en');

    if (nlCol) nlCol.style.display = (language === 'en') ? 'none' : '';
    if (enCol) enCol.style.display = (language === 'nl') ? 'none' : '';
  }

  // -------------------------------------------------------------------------
  // Helpers
  // -------------------------------------------------------------------------

  function activateNavItem(view) {
    const btn = document.querySelector(`.nav-item[data-view="${view}"]`);
    if (btn) {
      btn.classList.add('active');
      btn.setAttribute('aria-current', 'page');
    }
  }

  function showView(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('active');
  }

  // -------------------------------------------------------------------------
  // Event wiring (runs once on DOMContentLoaded)
  // -------------------------------------------------------------------------

  function init() {
    // Sidebar navigation buttons
    document.querySelectorAll('.nav-item[data-view]').forEach(btn => {
      btn.addEventListener('click', () => navigateTo(btn.dataset.view));
    });

    // New contract button
    document.getElementById('btn-new-contract')?.addEventListener('click', () => {
      navigateTo('contract-form');
    });

    // New worker button
    document.getElementById('btn-new-worker')?.addEventListener('click', () => {
      navigateTo('worker-form');
    });

    // Back buttons
    document.getElementById('btn-back-from-contract')?.addEventListener('click', () => navigateTo('contracts'));
    document.getElementById('btn-back-from-worker')?.addEventListener('click',   () => navigateTo('workers'));
    document.getElementById('btn-back-from-preview')?.addEventListener('click', () => {
      // Go back to where we came from
      if (_previewState?.source === 'form') {
        navigateTo('contract-form');
      } else {
        navigateTo('contracts');
      }
    });

    // Contract status filter
    document.getElementById('contract-status-filter')?.addEventListener('change', e => {
      ContractsUI.renderList(e.target.value);
    });

    // Worker search (debounced)
    const workerSearch = document.getElementById('worker-search');
    if (workerSearch) {
      let searchTimer;
      workerSearch.addEventListener('input', () => {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(() => WorkersUI.renderList(workerSearch.value), 280);
      });
    }

    // Language toggle in preview
    document.querySelectorAll('.lang-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        document.querySelectorAll('.lang-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        if (_previewState?.html) {
          renderPreview(_previewState.html, btn.dataset.lang);
        }
      });
    });

    // Print button
    document.getElementById('btn-print')?.addEventListener('click', () => window.print());

    // Keyboard: Escape closes modal
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        const overlay = document.getElementById('modal-overlay');
        if (overlay && !overlay.classList.contains('hidden')) {
          overlay.classList.add('hidden');
        }
      }
    });

    // Initial view
    navigateTo('contracts');
  }

  // -------------------------------------------------------------------------
  // DOMContentLoaded
  // -------------------------------------------------------------------------

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  // -------------------------------------------------------------------------
  // Expose
  // -------------------------------------------------------------------------

  global.App = { navigateTo };

}(window));
