<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('globalLoadingOverlay', () => ({
            show: false,
            mode: 'loading',
            title: '',
            message: '',
            timer: null,
            pendingAction: false,
            lastClickedEl: null,
            loadingStartTime: 0,
            minLoadingMs: 650,
            isLoginSubmission: false,

            init() {
                const checkIsLogin = (el) => {
                    if (window.location.pathname.includes('/login')) return true;
                    if (!el) return false;
                    const btn = el.closest('button, input[type=submit], button[type=submit], a');
                    if (!btn) return false;
                    const text = (btn.innerText || btn.textContent || '').toLowerCase().trim();
                    return text.includes('sign in') || text.includes('se connecter') || text.includes('connexion') || text.includes('login');
                };

                const isActionButton = (el) => {
                    if (!el) return false;
                    if (el.closest('[data-no-overlay]')) return false;

                    // Unconditionally ignore checkboxes, radios, and their surrounding wrappers/labels
                    if (el.closest('input[type="checkbox"], input[type="radio"], .fi-checkbox-input, .fi-ta-checkbox-cell, [wire\\:model*="tableRecords"]')) {
                        return false;
                    }
                    if (el.tagName === 'INPUT') {
                        const type = (el.type || 'text').toLowerCase();
                        if (type === 'checkbox' || type === 'radio') {
                            return false;
                        }
                    }
                    if (el.closest('label') && el.closest('label').querySelector('input[type="checkbox"], input[type="radio"]')) {
                        return false;
                    }

                    const tag = (el.tagName || '').toUpperCase();
                    if (tag === 'TEXTAREA' || tag === 'SELECT' || tag === 'INPUT') return false;

                    const btn = el.closest('button, input[type=submit], button[type=submit], a.fi-btn, a.btn, [wire\\:click]');
                    if (!btn) return false;

                    const onclick = (btn.getAttribute('x-on:click') || btn.getAttribute('@click') || '').toLowerCase();
                    if (onclick.includes('close-modal') || (onclick.includes('$dispatch(') && onclick.includes('close'))) return false;

                    const wireClick = btn.getAttribute('wire:click') || '';

                    if (wireClick.includes('selectionnerModule')) return false;

                    const isInModal = !!btn.closest('.fi-modal, .fi-modal-window, [role="dialog"], [id*="modal"]');

                    // If button is OUTSIDE a modal and is opening a modal (mountTableAction, mountAction, mountFormAction, data-action), DO NOT trigger overlay!
                    if (!isInModal) {
                        if (wireClick.includes('mountTableAction') || wireClick.includes('mountAction') || wireClick.includes('mountFormAction') || btn.hasAttribute('data-action') || btn.classList.contains('fi-modal-trigger')) {
                            return false; // Opening modal window only!
                        }
                    }

                    // Confirmation modal submission (confirmerDesactivation, confirmerRetrait, callMountedTableAction, etc.)
                    if (wireClick.includes('confirmerDesactivation') || wireClick.includes('confirmerRetrait') || wireClick.includes('callMountedTableAction') || wireClick.includes('callMountedAction')) {
                        return true;
                    }

                    // Form submit button (inside modal or stand-alone form)
                    if (btn.type === 'submit' || btn.getAttribute('type') === 'submit') return true;

                    // Action button styles inside modal (modal submit button)
                    if (isInModal && (btn.classList.contains('fi-btn-color-primary') ||
                                      btn.classList.contains('fi-btn-color-success') ||
                                      btn.classList.contains('fi-btn-color-danger'))) {
                        return true;
                    }

                    // Action button styles for direct non-modal actions
                    if (!isInModal && (btn.classList.contains('fi-btn-color-primary') ||
                                       btn.classList.contains('fi-btn-color-success') ||
                                       btn.classList.contains('fi-btn-color-danger') ||
                                       btn.classList.contains('btn-primary'))) {
                        return true;
                    }

                    const text = (btn.innerText || btn.textContent || '').toLowerCase().trim();
                    const actionKeywords = [
                        'sign in', 'se connecter', 'connexion', 'login', 'authentifier',
                        'créer', 'creer', 'nouveau', 'nouvelle', 'ajouter',
                        'valider', 'validation', 'confirmer', 'confirmation',
                        'enregistrer', 'sauvegarder', 'garder',
                        'supprimer', 'effacer', 'retirer', 'retrait',
                        'modifier', 'modification', 'mise à jour',
                        'create', 'save', 'submit', 'confirm', 'validate', 'delete', 'update'
                    ];

                    return actionKeywords.some(keyword => text.includes(keyword));
                };

                const isNavLink = (el, e) => {
                    if (!el || !e) return false;
                    if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || e.button !== 0) return false;

                    const a = el.closest('a[href]');
                    if (!a) return false;
                    if (a.hasAttribute('data-no-overlay')) return false;

                    const target = a.getAttribute('target');
                    if (target && target !== '_self') return false;

                    const href = a.getAttribute('href');
                    if (!href || href === '#' || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) return false;

                    return true;
                };

                document.addEventListener('click', (e) => {
                    if (isActionButton(e.target)) {
                        this.pendingAction = true;
                        this.lastClickedEl = e.target;
                        this.isLoginSubmission = checkIsLogin(e.target);
                        this.showLoading(4000);
                        return;
                    }

                    if (isNavLink(e.target, e)) {
                        this.pendingAction = true;
                        this.lastClickedEl = e.target;
                        this.isLoginSubmission = true;
                        this.showLoading(4000);
                    }
                });

                document.addEventListener('submit', (e) => {
                    this.pendingAction = true;
                    this.lastClickedEl = e.target;
                    this.isLoginSubmission = checkIsLogin(e.target);
                    this.showLoading(4000);
                });

                window.addEventListener('beforeunload', () => {
                    this.showLoading(4000);
                });

                window.addEventListener('show-global-loading', () => this.showLoading());
                window.addEventListener('hide-global-loading', () => this.hide());
                window.addEventListener('show-global-success', (e) => this.showSuccess(e.detail?.title, e.detail?.message));
                window.addEventListener('show-global-error', (e) => this.showError(e.detail?.title, e.detail?.message));

                const detectActionContent = (calls = []) => {
                    let methodStr = (calls || []).map(c => (c.method || '').toLowerCase()).join(' ');
                    let btnText = '';
                    let wireClick = '';

                    if (this.lastClickedEl) {
                        const btn = this.lastClickedEl.closest('button, input, a, [wire\\:click]') || this.lastClickedEl;
                        btnText = (btn.innerText || btn.textContent || btn.value || '').toLowerCase();
                        wireClick = (btn.getAttribute('wire:click') || '').toLowerCase();
                    }

                    const combined = (methodStr + ' ' + btnText + ' ' + wireClick).toLowerCase();

                    if (combined.includes('create') || combined.includes('ajouter') || combined.includes('creer') || combined.includes('nouveau')) {
                        return {
                            title: 'Création réussie',
                            message: 'Le nouvel enregistrement a été créé avec succès.'
                        };
                    }
                    if (combined.includes('delete') || combined.includes('destroy') || combined.includes('supprimer') || combined.includes('effacer') || combined.includes('retirer') || combined.includes('retrait')) {
                        return {
                            title: 'Suppression effectuée',
                            message: 'L’élément a été supprimé avec succès.'
                        };
                    }
                    if (combined.includes('update') || combined.includes('save') || combined.includes('modifier') || combined.includes('sauvegarder') || combined.includes('editer')) {
                        return {
                            title: 'Modifications enregistrées',
                            message: 'Les modifications ont été enregistrées avec succès.'
                        };
                    }
                    if (combined.includes('valider') || combined.includes('soumettre') || combined.includes('confirmer') || combined.includes('validate') || combined.includes('confirm')) {
                        return {
                            title: 'Opération validée',
                            message: 'La validation de l’opération a été effectuée avec succès.'
                        };
                    }
                    if (combined.includes('desactiver') || combined.includes('activer')) {
                        return {
                            title: 'Statut mis à jour',
                            message: 'Le statut a été mis à jour avec succès.'
                        };
                    }

                    return {
                        title: 'Opération réussie',
                        message: 'L’opération a été exécutée et enregistrée avec succès.'
                    };
                };

                const bindLivewire = () => {
                    if (typeof Livewire !== 'undefined' && Livewire.hook) {
                        Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
                            const calls = commit?.calls || [];
                            const isActionMethodCall = calls.some(c => {
                                const method = (c.method || '').toLowerCase();
                                if (method.includes('table') && (method.includes('search') || method.includes('sort') || method.includes('filter') || method.includes('column') || method.includes('mount'))) {
                                    return false;
                                }
                                if (method.includes('gotopage') || method.includes('nextpage') || method.includes('previouspage')) {
                                    return false;
                                }
                                const keywords = [
                                    'callmountedtableaction', 'callmountedaction', 'callmountedformaction',
                                    'save', 'create', 'update', 'submit', 'store', 'delete', 'destroy',
                                    'soumettre', 'valider', 'confirmer', 'supprimer', 'retirer', 'rejeter',
                                    'demander', 'changer', 'modifier'
                                ];
                                return keywords.some(kw => method.includes(kw));
                            });

                            succeed(({ snapshot }) => {
                                const mountedTableAction = snapshot?.memo?.mountedTableAction || snapshot?.memo?.data?.mountedTableAction;
                                const mountedAction = snapshot?.memo?.mountedAction || snapshot?.memo?.data?.mountedAction;

                                let errors = snapshot?.memo?.errors || {};
                                let errorKeys = Object.keys(errors);

                                if (errorKeys.length > 0) {
                                    // Validation errors occurred! Show Error card!
                                    this.pendingAction = false;
                                    let firstMsg = errors[errorKeys[0]][0] || 'Veuillez vérifier les champs obligatoires.';
                                    this.showError('Erreur de validation', firstMsg);
                                } else if (mountedTableAction || mountedAction) {
                                    // Modal just opened on screen! Reset pendingAction and hide loading!
                                    this.pendingAction = false;
                                    this.hide();
                                } else if (this.isLoginSubmission) {
                                    this.showLoading();
                                } else if (this.pendingAction && isActionMethodCall) {
                                    // REAL action/validation method was submitted and succeeded!
                                    const actionMeta = detectActionContent(calls);
                                    this.pendingAction = false;
                                    this.showSuccess(actionMeta.title, actionMeta.message);
                                } else {
                                    // Non-action update (sorting, filtering, opening menus). Silently hide!
                                    this.pendingAction = false;
                                    this.hide();
                                }
                            });

                            fail(() => {
                                this.pendingAction = false;
                                this.showError('Échec de l’opération', 'Une erreur est survenue lors du traitement.');
                            });
                        });
                    }
                };

                if (typeof Livewire !== 'undefined') {
                    bindLivewire();
                } else {
                    document.addEventListener('livewire:init', bindLivewire);
                }
            },

            showLoading(maxMs = 4000) {
                clearTimeout(this.timer);
                this.loadingStartTime = Date.now();
                this.mode = 'loading';
                this.show = true;

                this.timer = setTimeout(() => {
                    if (this.mode === 'loading') {
                        this.show = false;
                    }
                }, maxMs);
            },

            showSuccess(title, message) {
                clearTimeout(this.timer);

                const elapsed = Date.now() - this.loadingStartTime;
                const delay = Math.max(0, this.minLoadingMs - elapsed);

                this.timer = setTimeout(() => {
                    this.mode = 'success';
                    this.title = title || 'Opération réussie';
                    this.message = message || 'L’opération a été effectuée et enregistrée avec succès.';
                    this.show = true;

                    this.timer = setTimeout(() => {
                        this.show = false;
                    }, 3500);
                }, delay);
            },

            showError(title, message) {
                clearTimeout(this.timer);

                const elapsed = Date.now() - this.loadingStartTime;
                const delay = Math.max(0, this.minLoadingMs - elapsed);

                this.timer = setTimeout(() => {
                    this.mode = 'error';
                    this.title = title || 'Erreur de validation';
                    this.message = message || 'Veuillez vérifier les informations renseignées et réessayer.';
                    this.show = true;

                    this.timer = setTimeout(() => {
                        this.show = false;
                    }, 5000);
                }, delay);
            },

            hide() {
                clearTimeout(this.timer);
                this.show = false;
            }
        }));
    });
</script>

<div
    id="amb-global-loading-overlay"
    x-data="globalLoadingOverlay"
    x-show="show"
    x-cloak
    x-transition:enter="transition ease-out duration-200"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-100"
    x-transition:leave="transition ease-in duration-250"
    x-transition:leave-start="opacity-100"
    x-transition:leave-end="opacity-0"
    :style="mode === 'loading'
        ? 'position: fixed; inset: 0; width: 100vw; height: 100vh; z-index: 999999; background-color: rgba(3, 10, 36, 0.94); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px); margin: 0; padding: 0; pointer-events: auto;'
        : 'position: fixed; inset: 0; width: 100vw; height: 100vh; z-index: 999999; background: transparent; pointer-events: none; margin: 0; padding: 0;'"
>
    <!-- MODE LOADING: 3 DOTS CENTERED IN EXACT VERTICAL & HORIZONTAL CENTER OF SCREEN -->
    <div
        x-show="mode === 'loading'"
        style="position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); display: flex; align-items: center; justify-content: center; z-index: 9999999; margin: 0; padding: 0; pointer-events: auto;"
    >
        <svg
            width="100"
            height="100"
            viewBox="0 0 24 24"
            xmlns="http://www.w3.org/2000/svg"
            style="fill: #ffffff; color: #ffffff; display: block; margin: auto;"
        >
            <circle cx="4" cy="12" r="2.2" fill="#ffffff">
                <animate
                    id="spinner_qFRN"
                    begin="0;spinner_OcgL.end+0.2s"
                    attributeName="cy"
                    calcMode="spline"
                    dur="0.6s"
                    values="12;6;12"
                    keySplines=".33,.66,.66,1;.33,0,.66,.33"
                    repeatCount="indefinite"
                />
            </circle>
            <circle cx="12" cy="12" r="2.2" fill="#ffffff">
                <animate
                    begin="spinner_qFRN.begin+0.1s"
                    attributeName="cy"
                    calcMode="spline"
                    dur="0.6s"
                    values="12;6;12"
                    keySplines=".33,.66,.66,1;.33,0,.66,.33"
                    repeatCount="indefinite"
                />
            </circle>
            <circle cx="20" cy="12" r="2.2" fill="#ffffff">
                <animate
                    id="spinner_OcgL"
                    begin="spinner_qFRN.begin+0.2s"
                    attributeName="cy"
                    calcMode="spline"
                    dur="0.6s"
                    values="12;6;12"
                    keySplines=".33,.66,.66,1;.33,0,.66,.33"
                    repeatCount="indefinite"
                />
            </circle>
        </svg>
    </div>

    <!-- MODE SUCCESS: WHITE ELEGANT CARD MATCHING USER IMAGE REFERENCE -->
    <div
        x-show="mode === 'success'"
        style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 92%; max-width: 28rem; background: #ffffff; border-radius: 1.5rem; padding: 2.25rem 2rem 1.85rem; box-shadow: 0 25px 55px -10px rgba(0, 0, 0, 0.18), 0 0 0 1px rgba(0, 0, 0, 0.06); text-align: center; pointer-events: auto; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); overflow: hidden;"
    >
        <!-- TOP GRADIENT ACCENT RIM MATCHING IMAGE -->
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, #ec4899, #f59e0b, #10b981, #3b82f6);"></div>

        <!-- ICON BADGE -->
        <div style="width: 4rem; height: 4rem; border-radius: 9999px; background: rgba(16, 185, 129, 0.1); border: 1.5px solid rgba(16, 185, 129, 0.35); display: flex; align-items: center; justify-content: center; margin: 0.4rem auto 1.25rem auto; color: #059669; box-shadow: 0 0 20px rgba(16, 185, 129, 0.2);">
            <svg style="width: 2.2rem; height: 2.2rem; display: block; margin: auto;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.75" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <h3 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0 auto 0.4rem auto; letter-spacing: -0.015em; text-align: center;" x-text="title || 'Opération réussie'"></h3>
        <p style="font-size: 0.95rem; color: #475569; line-height: 1.55; margin: 0 auto; max-width: 92%; text-align: center; word-break: break-word;" x-text="message || 'L’opération a été effectuée et enregistrée avec succès.'"></p>
        <button @click="show = false" style="margin-top: 1.5rem; padding: 0.65rem 2.6rem; background: #10b981; color: #ffffff; font-weight: 700; font-size: 0.95rem; border-radius: 9999px; border: 0; cursor: pointer; box-shadow: 0 8px 22px -4px rgba(16, 185, 129, 0.45); transition: transform 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            Fermer
        </button>
    </div>

    <!-- MODE ERROR: WHITE ELEGANT ERROR CARD WITH ROSY ACCENT RIM -->
    <div
        x-show="mode === 'error'"
        style="display: flex; flex-direction: column; align-items: center; justify-content: center; width: 92%; max-width: 28rem; background: #ffffff; border-radius: 1.5rem; padding: 2.25rem 2rem 1.85rem; box-shadow: 0 25px 55px -10px rgba(0, 0, 0, 0.18), 0 0 0 1px rgba(244, 63, 94, 0.15); text-align: center; pointer-events: auto; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); overflow: hidden;"
    >
        <!-- TOP GRADIENT ACCENT RIM FOR ERROR -->
        <div style="position: absolute; top: 0; left: 0; right: 0; height: 5px; background: linear-gradient(90deg, #f43f5e, #f97316, #e11d48);"></div>

        <!-- ICON BADGE -->
        <div style="width: 4rem; height: 4rem; border-radius: 9999px; background: rgba(244, 63, 94, 0.1); border: 1.5px solid rgba(244, 63, 94, 0.35); display: flex; align-items: center; justify-content: center; margin: 0.4rem auto 1.25rem auto; color: #e11d48; box-shadow: 0 0 20px rgba(244, 63, 94, 0.2);">
            <svg style="width: 2.2rem; height: 2.2rem; display: block; margin: auto;" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.75" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </div>
        <h3 style="font-size: 1.35rem; font-weight: 800; color: #0f172a; margin: 0 auto 0.4rem auto; letter-spacing: -0.015em; text-align: center;" x-text="title || 'Échec de l\'opération'"></h3>
        <p style="font-size: 0.95rem; color: #64748b; line-height: 1.55; margin: 0 auto; max-width: 92%; text-align: center; word-break: break-word;" x-text="message || 'Une erreur est survenue lors de l\'opération.'"></p>
        <button @click="show = false" style="margin-top: 1.5rem; padding: 0.65rem 2.6rem; background: #f43f5e; color: #ffffff; font-weight: 700; font-size: 0.95rem; border-radius: 9999px; border: 0; cursor: pointer; box-shadow: 0 8px 22px -4px rgba(244, 63, 94, 0.45); transition: transform 0.15s ease;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
            Fermer
        </button>
    </div>
</div>
