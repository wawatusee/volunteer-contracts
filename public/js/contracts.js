/**
 * contracts.js
 *
 * UI logic for the Contracts section.
 *
 * Responsibilities:
 *  - Render the contracts list with status filter
 *  - Build and handle the contract create/edit form
 *  - Worker selector with auto-fill
 *  - Preview generation (with language toggle)
 *  - Delete with confirmation
 *
 * Depends on: api.js, forms.js, app.js
 */

'use strict';

(function (global) {

  // -------------------------------------------------------------------------
  // List
  // -------------------------------------------------------------------------

  async function renderList(statusFilter = '') {
    const container = document.getElementById('contracts-list');
    if (!container) return;

    container.innerHTML = '<div class="loading-state"><div class="spinner"></div><p>Chargement…</p></div>';

    let data;
    try {
      data = await Api.contracts.list(statusFilter);
    } catch (err) {
      container.innerHTML = `<div class="empty-state"><p>Erreur: ${Forms.escHtml(err.message)}</p></div>`;
      return;
    }

    const contracts = data.data || [];

    if (contracts.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
            <polyline points="14 2 14 8 20 8"/>
          </svg>
          <p>Aucun contrat trouvé.</p>
        </div>`;
      return;
    }

    container.innerHTML = '';

    contracts.forEach(c => {
      const card = document.createElement('div');
      card.className = 'contract-card';
      card.setAttribute('role', 'listitem');

      const volunteerName = c.volunteer_name?.trim() || '(volontaire non assigné)';
      const amount = c.total_amount
        ? `€ ${parseFloat(c.total_amount).toFixed(2)}`
        : '—';
      const dates = (c.start_date && c.end_date)
        ? `${formatShortDate(c.start_date)} → ${formatShortDate(c.end_date)}`
        : '—';

      card.innerHTML = `
        <div class="card-meta">
          <div class="card-title">${Forms.escHtml(c.event_name || '—')}</div>
          <div class="card-sub">
            <span>${Forms.escHtml(volunteerName)}</span>
            <span>${dates}</span>
            <span>${amount}</span>
            <span><span class="status-badge status-${Forms.escHtml(c.status || 'draft')}">${statusLabel(c.status)}</span></span>
          </div>
        </div>
        <div class="card-actions">
          <button class="btn btn-ghost btn-sm btn-preview-contract" data-id="${Forms.escHtml(c.id)}" title="Aperçu">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
              <circle cx="12" cy="12" r="3"/>
            </svg>
            Aperçu
          </button>
          <button class="btn btn-ghost btn-sm btn-edit-contract" data-id="${Forms.escHtml(c.id)}" title="Modifier">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
              <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
            </svg>
          </button>
          <button class="btn btn-ghost btn-sm btn-delete-contract" data-id="${Forms.escHtml(c.id)}" data-name="${Forms.escHtml(c.event_name || '')}" title="Supprimer">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <polyline points="3 6 5 6 21 6"/>
              <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
            </svg>
          </button>
        </div>`;

      container.appendChild(card);
    });

    // Attach listeners
    container.querySelectorAll('.btn-preview-contract').forEach(btn => {
      btn.addEventListener('click', () => renderSavedPreview(btn.dataset.id));
    });

    container.querySelectorAll('.btn-edit-contract').forEach(btn => {
      btn.addEventListener('click', () => App.navigateTo('contract-form', { id: btn.dataset.id }));
    });

    container.querySelectorAll('.btn-delete-contract').forEach(btn => {
      btn.addEventListener('click', () => deleteContract(btn.dataset.id, btn.dataset.name));
    });
  }

  // -------------------------------------------------------------------------
  // Contract form
  // -------------------------------------------------------------------------

  /** Track the currently selected worker to avoid redundant API calls. */
  let _selectedWorker = null;

  async function renderForm(contractId = null) {
    const container = document.getElementById('contract-form-container');
    const titleEl = document.getElementById('contract-form-title');
    if (!container) return;

    container.innerHTML = '<div class="loading-state"><div class="spinner"></div></div>';

    _selectedWorker = null;

    // Load existing contract if editing
    let contract = null;
    if (contractId) {
      try {
        const resp = await Api.contracts.get(contractId);
        contract = resp.data;
        titleEl.textContent = `Modifier — ${contract.event_name || 'contrat'}`;
      } catch (err) {
        Forms.toast(err.message, 'error');
        return;
      }
    } else {
      titleEl.textContent = 'Nouveau contrat';
    }

    const c = contract || {};

    // Load workers for the selector
    let workerOptions = [{ value: '', label: '— Saisie libre (sans volontaire existant) —' }];
    try {
      const wResp = await Api.workers.list();
      (wResp.data || []).forEach(w => {
        workerOptions.push({ value: w.id, label: `${w.first_name} ${w.last_name} — ${w.email}` });
      });
    } catch { /* non-fatal */ }

    container.innerHTML = '';

    // ── Worker selector ──
    const selectorWrap = document.createElement('div');
    selectorWrap.className = 'form-card';
    selectorWrap.innerHTML = `
      <h3 class="form-card-title">Lier un volontaire existant / Link existing worker</h3>
      <div class="worker-selector">
        <span class="worker-selector-label">Volontaire</span>
        <select class="form-select" name="worker_id" id="worker-id-select"></select>
      </div>
      <p style="font-size:.8rem;color:var(--text-muted)">
        Si vous sélectionnez un volontaire existant, ses données personnelles seront 
        utilisées automatiquement. Sinon, remplissez les champs ci-dessous manuellement.
      </p>`;

    const workerSel = selectorWrap.querySelector('#worker-id-select');
    workerOptions.forEach(opt => {
      const o = document.createElement('option');
      o.value = opt.value;
      o.textContent = opt.label;
      if (opt.value === (c.worker_id || '')) o.selected = true;
      workerSel.appendChild(o);
    });

    container.appendChild(selectorWrap);

    // ── Volunteer data card (manual entry or auto-fill) ──
    const card1 = makeCard('Données du volontaire / Volunteer data');
    const grid1 = makeGrid();
    grid1.appendChild(Forms.makeInputField({ name: 'volunteer_first', label: 'Prénom / First name', value: c.volunteer_first || '' }));
    grid1.appendChild(Forms.makeInputField({ name: 'volunteer_last', label: 'Nom / Last name', value: c.volunteer_last || '' }));
    grid1.appendChild(Forms.makeInputField({ name: 'volunteer_birth', label: 'Date de naissance', type: 'date', value: c.volunteer_birth || '' }));
    grid1.appendChild(Forms.makeInputField({ name: 'volunteer_address', label: 'Adresse complète', value: c.volunteer_address || '' }));
    grid1.appendChild(Forms.makeInputField({ name: 'volunteer_email', label: 'E-mail', type: 'email', value: c.volunteer_email || '' }));
    grid1.appendChild(Forms.makeInputField({ name: 'volunteer_iban', label: 'IBAN', value: c.volunteer_iban || '', placeholder: 'BE00 0000 0000 0000' }));
    card1.querySelector('.form-card-body').appendChild(grid1);
    container.appendChild(card1);

    // ── Contract details card ──
    const card2 = makeCard('Détails du contrat / Contract details');
    const grid2 = makeGrid();
    grid2.appendChild(Forms.makeInputField({ name: 'event_name', label: 'Nom de l\'événement', required: true, value: c.event_name || '' }));
    grid2.appendChild(Forms.makeInputField({ name: 'role', label: 'Fonction / Role', required: true, value: c.role || '' }));
    grid2.appendChild(Forms.makeInputField({ name: 'start_date', label: 'Date de début', required: true, type: 'date', value: c.start_date || '' }));
    grid2.appendChild(Forms.makeInputField({ name: 'end_date', label: 'Date de fin', required: true, type: 'date', value: c.end_date || '' }));
    grid2.appendChild(Forms.makeInputField({ name: 'total_amount', label: 'Montant (€)', required: true, type: 'number', value: c.total_amount || '', placeholder: '0.00' }));
    grid2.appendChild(Forms.makeInputField({ name: 'signature_date', label: 'Date de signature', required: true, type: 'date', value: c.signature_date || '' }));
    grid2.appendChild(Forms.makeInputField({ name: 'payment_date', label: 'Date de paiement', required: true, type: 'date', value: c.payment_date || '' }));
    card2.querySelector('.form-card-body').appendChild(grid2);
    container.appendChild(card2);

    // ── Organisation card ──
    const card3 = makeCard('Organisation');
    const grid3 = makeGrid();
    grid3.appendChild(Forms.makeInputField({ name: 'organisation_name', label: 'Nom de l\'organisation', required: true, value: c.organisation_name || '' }));
    grid3.appendChild(Forms.makeInputField({ name: 'organisation_rep', label: 'Représentant / Rep', required: true, value: c.organisation_rep || '' }));
    card3.querySelector('.form-card-body').appendChild(grid3);
    container.appendChild(card3);

    // ── Options card ──
    const card4 = makeCard('Options');
    const grid4 = makeGrid();
    grid4.appendChild(Forms.makeSelectField({
      name: 'status', label: 'Statut', value: c.status || 'draft',
      options: [
        { value: 'draft', label: 'Brouillon / Draft' },
        { value: 'signed', label: 'Signé / Signed' },
        { value: 'archived', label: 'Archivé / Archived' },
      ],
    }));
    grid4.appendChild(Forms.makeSelectField({
      name: 'language', label: 'Langue / Language', value: c.language || 'both',
      options: [
        { value: 'both', label: 'Bilingue NL + EN' },
        { value: 'nl', label: 'Néerlandais seulement' },
        { value: 'en', label: 'English only' },
      ],
    }));
    card4.querySelector('.form-card-body').appendChild(grid4);
    container.appendChild(card4);

    // ── Actions ──
    const actions = document.createElement('div');
    actions.className = 'form-actions';
    actions.innerHTML = `
      <button class="btn btn-ghost" id="contract-cancel-btn">Annuler</button>
      <span class="spacer"></span>
      <button class="btn btn-secondary" id="contract-preview-btn">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
        </svg>
        Aperçu sans sauvegarder
      </button>
      <button class="btn btn-primary" id="contract-save-btn">
        ${contract ? 'Enregistrer les modifications' : 'Créer le contrat'}
      </button>`;
    container.appendChild(actions);

    // ── Worker auto-fill ──
    const volunteerFields = ['volunteer_first', 'volunteer_last', 'volunteer_birth', 'volunteer_address', 'volunteer_email', 'volunteer_iban'];

    workerSel.addEventListener('change', async () => {
      const workerId = workerSel.value;
      if (!workerId) {
        _selectedWorker = null;
        setVolunteerFieldsReadonly(container, false);
        clearVolunteerFields(container);
        return;
      }
      try {
        const resp = await Api.workers.get(workerId);
        _selectedWorker = resp.data;
        prefillVolunteerFields(container, _selectedWorker);
        setVolunteerFieldsReadonly(container, true);
      } catch (err) {
        Forms.toast(`Impossible de charger le volontaire: ${err.message}`, 'error');
      }
    });

    // Trigger auto-fill if editing and worker_id is set
    if (c.worker_id) {
      workerSel.dispatchEvent(new Event('change'));
    }

    // ── Save ──
    document.getElementById('contract-cancel-btn').addEventListener('click', () => App.navigateTo('contracts'));

    document.getElementById('contract-save-btn').addEventListener('click', async () => {
      Forms.clearErrors(container);
      const formData = Forms.collectFormData(container);
      if (contractId) formData.id = contractId;

      try {
        if (contractId) {
          await Api.contracts.update(contractId, formData);
          Forms.toast('Contrat mis à jour.', 'success');
        } else {
          await Api.contracts.create(formData);
          Forms.toast('Contrat créé.', 'success');
        }
        App.navigateTo('contracts');
      } catch (err) {
        const lines = err.message.split('\n');
        Forms.showErrors(container, lines);
        if (lines.length === 1) Forms.toast(err.message, 'error');
      }
    });

    // ── Preview without saving ──
    document.getElementById('contract-preview-btn').addEventListener('click', async () => {
      const formData = Forms.collectFormData(container);
      try {
        const resp = await Api.contracts.preview(formData);
        App.navigateTo('preview', { html: resp.html, source: 'form' });
      } catch (err) {
        Forms.toast(err.message, 'error');
      }
    });
  }

  // -------------------------------------------------------------------------
  // Preview of saved contract
  // -------------------------------------------------------------------------

  async function renderSavedPreview(contractId) {
    try {
      const resp = await Api.contracts.getPreview(contractId);
      App.navigateTo('preview', { html: resp.html, contractId });
    } catch (err) {
      Forms.toast(err.message, 'error');
    }
  }

  // -------------------------------------------------------------------------
  // Delete
  // -------------------------------------------------------------------------

  async function deleteContract(id, name) {
    const confirmed = await Forms.confirm({
      title: 'Supprimer le contrat',
      body: `Voulez-vous vraiment supprimer le contrat "${name}" ? Cette action est irréversible.`,
      confirmLabel: 'Supprimer',
    });
    if (!confirmed) return;

    try {
      await Api.contracts.delete(id);
      Forms.toast('Contrat supprimé.', 'success');
      renderList(document.getElementById('contract-status-filter')?.value || '');
    } catch (err) {
      Forms.toast(err.message, 'error');
    }
  }

  // -------------------------------------------------------------------------
  // Private helpers
  // -------------------------------------------------------------------------

  function prefillVolunteerFields(container, worker) {
    const fieldMap = {
      volunteer_first: worker.first_name || '',
      volunteer_last: worker.last_name || '',
      volunteer_birth: worker.birth_date || '',
      volunteer_address: [worker.address, worker.postal_code, worker.city, worker.country].filter(Boolean).join(', '),
      volunteer_email: worker.email || '',
      volunteer_iban: worker.iban || '',
    };
    for (const [name, value] of Object.entries(fieldMap)) {
      const el = container.querySelector(`[name="${name}"]`);
      if (el) el.value = value;
    }
  }

  function clearVolunteerFields(container) {
    ['volunteer_first', 'volunteer_last', 'volunteer_birth', 'volunteer_address', 'volunteer_email', 'volunteer_iban']
      .forEach(name => {
        const el = container.querySelector(`[name="${name}"]`);
        if (el) el.value = '';
      });
  }

  function setVolunteerFieldsReadonly(container, readonly) {
    ['volunteer_first', 'volunteer_last', 'volunteer_birth', 'volunteer_address', 'volunteer_email', 'volunteer_iban']
      .forEach(name => {
        const el = container.querySelector(`[name="${name}"]`);
        if (el) {
          el.readOnly = readonly;
          el.style.background = readonly ? 'var(--navy-50)' : '';
        }
      });
  }

  function makeCard(title) {
    const card = document.createElement('div');
    card.className = 'form-card';
    const h = document.createElement('h3');
    h.className = 'form-card-title';
    h.textContent = title;
    const body = document.createElement('div');
    body.className = 'form-card-body';
    card.appendChild(h);
    card.appendChild(body);
    return card;
  }

  function makeGrid() {
    const grid = document.createElement('div');
    grid.className = 'form-grid';
    return grid;
  }

  function statusLabel(s) {
    return { draft: 'Brouillon', signed: 'Signé', archived: 'Archivé' }[s] || s || '—';
  }

  function formatShortDate(ymd) {
    if (!ymd) return '—';
    try {
      const [y, m, d] = ymd.split('-');
      return `${d}/${m}/${y}`;
    } catch { return ymd; }
  }

  // -------------------------------------------------------------------------
  // Expose
  // -------------------------------------------------------------------------

  global.ContractsUI = { renderList, renderForm, renderSavedPreview };

}(window));
