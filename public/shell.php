<?php
/**
 * public/shell.php
 *
 * The HTML shell that hosts the single-page application.
 * This file is PHP so we can inject the APP_VERSION for cache-busting.
 */
$version = '1.0.0';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Vrijwilligerscontracten / Volunteer Contracts</title>

    <!-- Preconnect for Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:ital,wght@0,400;0,700;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/css/app.css?v=<?= $version ?>">
</head>
<body>

<!-- ============================================================
     APP SHELL
     ============================================================ -->
<div id="app">

    <!-- Sidebar navigation -->
    <nav id="sidebar" class="sidebar" role="navigation" aria-label="Main navigation">
        <div class="sidebar-brand">
            <div class="brand-mark">VC</div>
            <div class="brand-text">
                <span class="brand-title">Volunteer</span>
                <span class="brand-subtitle">Contracts</span>
            </div>
        </div>

        <ul class="nav-list" role="list">
            <li>
                <button class="nav-item active" data-view="contracts" aria-current="page">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/>
                        <polyline points="14 2 14 8 20 8"/>
                        <line x1="16" y1="13" x2="8" y2="13"/>
                        <line x1="16" y1="17" x2="8" y2="17"/>
                        <polyline points="10 9 9 9 8 9"/>
                    </svg>
                    <span>Contrats / Contracts</span>
                </button>
            </li>
            <li>
                <button class="nav-item" data-view="workers">
                    <svg class="nav-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <span>Volontaires / Workers</span>
                </button>
            </li>
        </ul>

        <div class="sidebar-footer">
            <span class="version-badge">v<?= $version ?></span>
        </div>
    </nav>

    <!-- Main content area -->
    <main id="main" class="main-content" role="main">

        <!-- ── TOAST NOTIFICATIONS ── -->
        <div id="toast-container" aria-live="polite" aria-atomic="true"></div>

        <!-- ── CONTRACTS VIEW ── -->
        <section id="view-contracts" class="view active" aria-label="Contracts">
            <header class="view-header">
                <div class="view-header-text">
                    <h1>Contrats de volontariat</h1>
                    <p class="view-subtitle">Gérez et prévisualisez les overeenkomsten</p>
                </div>
                <button class="btn btn-primary" id="btn-new-contract">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Nouveau contrat
                </button>
            </header>

            <!-- Filters -->
            <div class="toolbar">
                <div class="filter-group">
                    <label for="contract-status-filter" class="sr-only">Statut</label>
                    <select id="contract-status-filter" class="select-input">
                        <option value="">Tous les statuts</option>
                        <option value="draft">Brouillon / Draft</option>
                        <option value="signed">Signé / Signed</option>
                        <option value="archived">Archivé / Archived</option>
                    </select>
                </div>
            </div>

            <!-- List -->
            <div id="contracts-list" class="card-grid" role="list">
                <div class="loading-state">
                    <div class="spinner"></div>
                    <p>Chargement…</p>
                </div>
            </div>
        </section>

        <!-- ── WORKERS VIEW ── -->
        <section id="view-workers" class="view" aria-label="Workers">
            <header class="view-header">
                <div class="view-header-text">
                    <h1>Volontaires potentiels</h1>
                    <p class="view-subtitle">Base de données réutilisable</p>
                </div>
                <button class="btn btn-primary" id="btn-new-worker">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Nouveau volontaire
                </button>
            </header>

            <div class="toolbar">
                <div class="search-box">
                    <svg class="search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                    </svg>
                    <input type="search" id="worker-search" class="search-input" placeholder="Nom, prénom, e-mail…" aria-label="Rechercher un volontaire">
                </div>
            </div>

            <div id="workers-list" class="workers-table-wrap" role="list">
                <div class="loading-state">
                    <div class="spinner"></div>
                    <p>Chargement…</p>
                </div>
            </div>
        </section>

        <!-- ── CONTRACT FORM VIEW ── -->
        <section id="view-contract-form" class="view" aria-label="Contract form">
            <header class="view-header">
                <button class="btn btn-ghost btn-back" id="btn-back-from-contract">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    Retour
                </button>
                <h1 id="contract-form-title">Nouveau contrat</h1>
            </header>
            <div id="contract-form-container"></div>
        </section>

        <!-- ── WORKER FORM VIEW ── -->
        <section id="view-worker-form" class="view" aria-label="Worker form">
            <header class="view-header">
                <button class="btn btn-ghost btn-back" id="btn-back-from-worker">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    Retour
                </button>
                <h1 id="worker-form-title">Nouveau volontaire</h1>
            </header>
            <div id="worker-form-container"></div>
        </section>

        <!-- ── PREVIEW VIEW ── -->
        <section id="view-preview" class="view" aria-label="Contract preview">
            <header class="view-header">
                <button class="btn btn-ghost btn-back" id="btn-back-from-preview">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                    Retour
                </button>
                <h1>Aperçu du contrat / Contract preview</h1>
                <div class="header-actions">
                    <div class="lang-toggle" role="group" aria-label="Language">
                        <button class="lang-btn active" data-lang="both">NL + EN</button>
                        <button class="lang-btn" data-lang="nl">NL</button>
                        <button class="lang-btn" data-lang="en">EN</button>
                    </div>
                    <button class="btn btn-secondary" id="btn-print">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 6 2 18 2 18 9"/>
                            <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                            <rect x="6" y="14" width="12" height="8"/>
                        </svg>
                        Imprimer
                    </button>
                </div>
            </header>
            <div id="preview-content" class="preview-wrap"></div>
        </section>

    </main>
</div>

<!-- ── MODAL OVERLAY ── -->
<div id="modal-overlay" class="modal-overlay hidden" role="dialog" aria-modal="true">
    <div class="modal-box">
        <header class="modal-header">
            <h2 id="modal-title"></h2>
            <button class="modal-close" aria-label="Fermer">&times;</button>
        </header>
        <div id="modal-body" class="modal-body"></div>
        <footer class="modal-footer">
            <button class="btn btn-ghost" id="modal-cancel">Annuler</button>
            <button class="btn btn-danger" id="modal-confirm">Confirmer</button>
        </footer>
    </div>
</div>

<script src="/js/api.js?v=<?= $version ?>"></script>
<script src="/js/forms.js?v=<?= $version ?>"></script>
<script src="/js/workers.js?v=<?= $version ?>"></script>
<script src="/js/contracts.js?v=<?= $version ?>"></script>
<script src="/js/app.js?v=<?= $version ?>"></script>
</body>
</html>
