/**
 * workers.js
 *
 * UI logic for the Workers (volontaires) section.
 *
 * Responsibilities:
 *  - Render the workers list table
 *  - Build and handle the worker create/edit form
 *  - Handle delete with confirmation
 *
 * Depends on: api.js, forms.js, app.js (for navigation)
 */

'use strict';

(function (global) {

  // -------------------------------------------------------------------------
  // List rendering
  // -------------------------------------------------------------------------

  /**
   * Load and render the workers list table.
   * @param {string} [searchQuery]
   */
  async function renderList(searchQuery = '') {
    const container = document.getElementById('workers-list');
    if (!container) return;

    container.innerHTML = '<div class="loading-state"><div class="spinner"></div><p>Chargement…</p></div>';

    let data;
    try {
      data = await Api.workers.list(searchQuery);
    } catch (err) {
      container.innerHTML = `<div class="empty-state"><p>Erreur: ${Forms.escHtml(err.message)}</p></div>`;
      return;
    }

    const workers = data.data || [];

    if (workers.length === 0) {
      container.innerHTML = `
        <div class="empty-state">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          <p>Aucun volontaire trouvé.</p>
        </div>`;
      return;
    }

    const table = document.createElement('table');
    table.className = 'workers-table';
    table.innerHTML = `
      <thead>
        <tr>
          <th>Nom / Name</th>
          <th>E-mail</th>
          <th>Ville / City</th>
          <th>Modifié</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody></tbody>`;

    const tbody = table.querySelector('tbody');

    workers.forEach(w => {
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td><strong>${Forms.escHtml(w.first_name)} ${Forms.escHtml(w.last_name)}</strong></td>
        <td>${Forms.escHtml(w.email)}</td>
        <td>${Forms.escHtml(w.city || '—')}</td>
        <td>${formatDate(w.updated_at)}</td>
        <td>
          <div class="td-actions">
            <button class="btn btn-ghost btn-sm btn-edit-worker" data-id="${Forms.escHtml(w.id)}" title="Modifier">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
              </svg>
              Modifier
            </button>
            <button class="btn btn-ghost btn-sm btn-delete-worker" data-id="${Forms.escHtml(w.id)}" data-name="${Forms.escHtml(w.first_name + ' ' + w.last_name)}" title="Supprimer">
              <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"/>
                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/>
              </svg>
            </button>
          </div>
        </td>`;
      tbody.appendChild(tr);
    });

    container.innerHTML = '';
    container.appendChild(table);

    // Attach action listeners
    container.querySelectorAll('.btn-edit-worker').forEach(btn => {
      btn.addEventListener('click', () => App.navigateTo('worker-form', { id: btn.dataset.id }));
    });

    container.querySelectorAll('.btn-delete-worker').forEach(btn => {
      btn.addEventListener('click', () => deleteWorker(btn.dataset.id, btn.dataset.name));
    });
  }

  // -------------------------------------------------------------------------
  // Form rendering
  // -------------------------------------------------------------------------

  /**
   * Render the create/edit form into #worker-form-container.
   * @param {string|null} [workerId]  null = create, string = edit
   */
  async function renderForm(workerId = null) {
    const container = document.getElementById('worker-form-container');
    const titleEl   = document.getElementById('worker-form-title');
    if (!container) return;

    container.innerHTML = '<div class="loading-state"><div class="spinner"></div></div>';

    let worker = null;
    if (workerId) {
      try {
        const resp = await Api.workers.get(workerId);
        worker = resp.data;
        titleEl.textContent = `Modifier — ${worker.first_name} ${worker.last_name}`;
      } catch (err) {
        Forms.toast(err.message, 'error');
        return;
      }
    } else {
      titleEl.textContent = 'Nouveau volontaire';
    }

    const v = worker || {};

    container.innerHTML = '';

    // --- Identity card ---
    const card1 = makeCard('Identité / Identity');
    const grid1 = makeGrid();
    grid1.appendChild(Forms.makeInputField({ name: 'first_name', label: 'Prénom / First name', required: true,  value: v.first_name || '' }));
    grid1.appendChild(Forms.makeInputField({ name: 'last_name',  label: 'Nom / Last name',     required: true,  value: v.last_name  || '' }));
    grid1.appendChild(Forms.makeInputField({ name: 'birth_date', label: 'Date de naissance',   required: true,  type: 'date', value: v.birth_date || '' }));
    grid1.appendChild(Forms.makeInputField({ name: 'email',      label: 'E-mail',               required: true,  type: 'email', value: v.email || '' }));
    grid1.appendChild(Forms.makeInputField({ name: 'phone',      label: 'Téléphone / Phone',    required: false, type: 'tel', value: v.phone || '' }));
    card1.querySelector('.form-card-body').appendChild(grid1);
    container.appendChild(card1);

    // --- Address card ---
    const card2 = makeCard('Adresse / Address');
    const grid2 = makeGrid();
    grid2.appendChild(Forms.makeInputField({ name: 'address',     label: 'Rue + numéro / Street', required: true,  value: v.address || '' }));
    grid2.appendChild(Forms.makeInputField({ name: 'postal_code', label: 'Code postal / ZIP',      required: true,  value: v.postal_code || '' }));
    grid2.appendChild(Forms.makeInputField({ name: 'city',        label: 'Ville / City',            required: true,  value: v.city || '' }));
    grid2.appendChild(Forms.makeInputField({ name: 'country',     label: 'Pays / Country',          required: true,  value: v.country || 'Belgique' }));
    card2.querySelector('.form-card-body').appendChild(grid2);
    container.appendChild(card2);

    // --- Financial card ---
    const card3 = makeCard('Données bancaires / Banking');
    const grid3 = makeGrid();
    const ibanGroup = Forms.makeInputField({ name: 'iban', label: 'IBAN', required: true, value: v.iban || '', placeholder: 'BE00 0000 0000 0000' });
    grid3.appendChild(ibanGroup);
    const notesGroup = Forms.makeInputField({ name: 'notes', label: 'Notes internes', required: false, value: v.notes || '' });
    notesGroup.classList.add('full');
    grid3.appendChild(notesGroup);
    card3.querySelector('.form-card-body').appendChild(grid3);
    container.appendChild(card3);

    // --- Actions ---
    const actions = document.createElement('div');
    actions.className = 'form-actions';
    actions.innerHTML = `
      <button class="btn btn-ghost" id="worker-cancel-btn">Annuler</button>
      <span class="spacer"></span>
      <button class="btn btn-secondary" id="worker-preview-btn">Aperçu</button>
      <button class="btn btn-primary" id="worker-save-btn">
        ${worker ? 'Enregistrer les modifications' : 'Créer le volontaire'}
      </button>`;
    container.appendChild(actions);

    // --- Wire events ---
    document.getElementById('worker-cancel-btn').addEventListener('click', () => App.navigateTo('workers'));

    document.getElementById('worker-save-btn').addEventListener('click', async () => {
      Forms.clearErrors(container);
      const formData = Forms.collectFormData(container);
      if (workerId) formData.id = workerId;

      try {
        if (workerId) {
          await Api.workers.update(workerId, formData);
          Forms.toast('Volontaire mis à jour.', 'success');
        } else {
          await Api.workers.create(formData);
          Forms.toast('Volontaire créé.', 'success');
        }
        App.navigateTo('workers');
      } catch (err) {
        const lines = err.message.split('\n');
        if (lines.length > 1) {
          Forms.showErrors(container, lines);
        } else {
          Forms.toast(err.message, 'error');
        }
      }
    });
  }

  // -------------------------------------------------------------------------
  // Delete
  // -------------------------------------------------------------------------

  async function deleteWorker(id, name) {
    const confirmed = await Forms.confirm({
      title         : 'Supprimer le volontaire',
      body          : `Voulez-vous vraiment supprimer "${name}" ? Cette action est irréversible.`,
      confirmLabel  : 'Supprimer',
    });
    if (!confirmed) return;

    try {
      await Api.workers.delete(id);
      Forms.toast('Volontaire supprimé.', 'success');
      renderList();
    } catch (err) {
      Forms.toast(err.message, 'error');
    }
  }

  // -------------------------------------------------------------------------
  // Private helpers
  // -------------------------------------------------------------------------

  function makeCard(title) {
    const card = document.createElement('div');
    card.className = 'form-card';
    const h = document.createElement('h3');
    h.className   = 'form-card-title';
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

  function formatDate(iso) {
    if (!iso) return '—';
    try { return new Date(iso).toLocaleDateString('fr-BE', { day: '2-digit', month: '2-digit', year: 'numeric' }); }
    catch { return iso; }
  }

  // -------------------------------------------------------------------------
  // Expose
  // -------------------------------------------------------------------------

  global.WorkersUI = { renderList, renderForm };

}(window));
