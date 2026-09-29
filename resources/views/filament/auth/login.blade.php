<div class="amb-login-card">
<style>
/* Guaranteed High-Contrast Login Input Fields & Labels (Dark text on Light background) */
.amb-auth-body,
.amb-auth-body *,
.amb-login-card,
.amb-login-card *,
.amb-login-form,
.amb-login-form * {
    color-scheme: light !important;
}

/* Force dark navy text for all labels, headings, spans, and text in the form section */
.amb-login-form label,
.amb-login-form label span,
.amb-login-form label *,
.amb-login-form .fi-fo-field-wrp-label,
.amb-login-form .fi-fo-field-wrp-label *,
.amb-login-form .fi-field-wrp-label,
.amb-login-form .fi-field-wrp-label *,
.amb-login-form .fi-header-heading,
.amb-login-form .fi-header-subheading,
.amb-login-form p,
.amb-login-form span,
.dark .amb-login-form label,
.dark .amb-login-form label span,
.dark .amb-login-form label *,
.dark .amb-login-form .fi-fo-field-wrp-label,
.dark .amb-login-form .fi-fo-field-wrp-label *,
.dark .amb-login-form .fi-field-wrp-label,
.dark .amb-login-form .fi-field-wrp-label *,
.dark .amb-login-form .fi-header-heading,
.dark .amb-login-form .fi-header-subheading,
.dark .amb-login-form p,
.dark .amb-login-form span {
    color: #07163f !important;
    -webkit-text-fill-color: #07163f !important;
    opacity: 1 !important;
}

/* Keep required asterisk red */
.amb-login-form sup,
.amb-login-form .text-danger-600,
.amb-login-form .text-custom-600,
.dark .amb-login-form sup,
.dark .amb-login-form .text-danger-600 {
    color: #dc2626 !important;
    -webkit-text-fill-color: #dc2626 !important;
}

/* Keep links blue */
.amb-login-form a,
.dark .amb-login-form a {
    color: #1948bd !important;
    -webkit-text-fill-color: #1948bd !important;
    font-weight: 600 !important;
}

/* Inputs styling */
.amb-login-form input[type="text"],
.amb-login-form input[type="email"],
.amb-login-form input[type="password"],
.amb-login-form input,
.dark .amb-login-form input {
    color: #07163f !important;
    -webkit-text-fill-color: #07163f !important;
    background-color: #ffffff !important;
    caret-color: #1948bd !important;
    font-size: 16px !important;
    font-weight: 600 !important;
    opacity: 1 !important;
}

.amb-login-form input::placeholder,
.dark .amb-login-form input::placeholder {
    color: #64748b !important;
    -webkit-text-fill-color: #64748b !important;
    opacity: 1 !important;
}

.amb-login-form .fi-input-wrp,
.dark .amb-login-form .fi-input-wrp {
    background-color: #ffffff !important;
    border: 1.5px solid #cbd5e1 !important;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08) !important;
}

.amb-login-form .fi-input-wrp:focus-within,
.dark .amb-login-form .fi-input-wrp:focus-within {
    border-color: #1948bd !important;
    box-shadow: 0 0 0 3px rgba(25, 72, 189, 0.2) !important;
}

.amb-login-form input:-webkit-autofill,
.amb-login-form input:-webkit-autofill:hover,
.amb-login-form input:-webkit-autofill:focus,
.amb-login-form input:-webkit-autofill:active,
.dark .amb-login-form input:-webkit-autofill,
.dark .amb-login-form input:-webkit-autofill:hover,
.dark .amb-login-form input:-webkit-autofill:focus,
.dark .amb-login-form input:-webkit-autofill:active {
    -webkit-text-fill-color: #07163f !important;
    -webkit-box-shadow: 0 0 0px 1000px #ffffff inset !important;
    box-shadow: 0 0 0px 1000px #ffffff inset !important;
    transition: background-color 50000s ease-in-out 0s !important;
}

/* Submit button text must stay white */
.amb-login-form button[type="submit"],
.amb-login-form .fi-btn-color-primary,
.dark .amb-login-form button[type="submit"],
.dark .amb-login-form .fi-btn-color-primary {
    background: linear-gradient(135deg, #102f78 0%, #1948bd 100%) !important;
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}

.amb-login-form button[type="submit"] *,
.dark .amb-login-form button[type="submit"] * {
    color: #ffffff !important;
    -webkit-text-fill-color: #ffffff !important;
}
</style>

    <aside class="amb-login-brand" aria-label="Présentation de l’établissement">
        <span class="amb-orb amb-orb-one" aria-hidden="true"></span>
        <span class="amb-orb amb-orb-two" aria-hidden="true"></span>

        <img src="{{ asset('images/logo.png') }}" alt="Ambassadors Educational Complex" class="amb-login-logo">
        <h1>Bienvenue à nouveau !</h1>
        <p class="amb-login-school">Ambassadors Educational Complex</p>
        <span class="amb-gold-rule" aria-hidden="true"></span>
        <p class="amb-login-motto">Développer les esprits. Bâtir l’avenir.<br>Ensemble dans la foi, la vision et la discipline.</p>
    </aside>

    <section class="amb-login-form">
        <div class="fi-simple-page-content w-full">
            <x-filament-panels::header.simple
                :heading="$this->getHeading()"
                :logo="$this->hasLogo()"
                :subheading="$this->getSubheading()"
            />

            {{ $this->content }}

            <x-filament-actions::modals />
        </div>
    </section>

    <footer class="amb-login-security">
        <x-filament::icon icon="heroicon-o-shield-check" />
        <span><strong>Accès sécurisé et fiable</strong><small>Vos données sont protégées selon les normes de sécurité les plus élevées.</small></span>
    </footer>

    <x-global-loading-overlay />
</div>


