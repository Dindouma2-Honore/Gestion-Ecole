<script>
    (() => {
        const notificationSelector = '.fi-no-notification:not(.fi-inline)';
        const processed = new WeakSet();

        const getNotificationDuration = (notification) => {
            // Pas de fermeture automatique pour les erreurs critiques ou les notifications persistantes
            const isPersistent = notification.matches('.fi-critical, [data-critical], .fi-no-notification-persistent, [data-persistent="true"]')
                || notification.getAttribute('data-duration') === 'persistent'
                || notification._x_dataStack?.[0]?.notification?.duration === 'persistent';

            if (isPersistent) {
                return null;
            }

            // ✅ Succès : 4 secondes
            if (notification.matches('.fi-color-success, .fi-status-success, [data-status="success"]')) {
                return 4000;
            }

            // ⚠️ Avertissement : 6 secondes
            if (notification.matches('.fi-color-warning, .fi-status-warning, [data-status="warning"]')) {
                return 6000;
            }

            // ❌ Erreur : 8 secondes
            if (notification.matches('.fi-color-danger, .fi-status-danger, [data-status="danger"]')) {
                return 8000;
            }

            // ℹ️ Information : 5 secondes
            if (notification.matches('.fi-color-info, .fi-status-info, [data-status="info"]')) {
                return 5000;
            }

            // Par défaut : 5 secondes
            return 5000;
        };

        const closeNotification = (notification) => {
            const closeBtn = notification.querySelector('.fi-no-notification-close-btn');
            if (closeBtn) {
                closeBtn.click();
                return;
            }

            const alpineComponent = notification._x_dataStack?.[0];
            if (typeof alpineComponent?.close === 'function') {
                alpineComponent.close();
                return;
            }

            notification.remove();
        };

        const handleNotification = (notification) => {
            if (processed.has(notification)) {
                return;
            }

            processed.add(notification);

            const duration = getNotificationDuration(notification);
            if (!duration) {
                return; // Notification critique / persistante : pas de fermeture auto
            }

            window.setTimeout(() => {
                if (notification.matches(':hover')) {
                    notification.addEventListener('mouseleave', () => closeNotification(notification), { once: true });
                    return;
                }

                closeNotification(notification);
            }, duration);
        };

        const scan = (root = document) => {
            root.querySelectorAll?.(notificationSelector).forEach(handleNotification);
        };

        scan();

        new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof Element)) {
                        return;
                    }

                    if (node.matches(notificationSelector)) {
                        handleNotification(node);
                    }

                    scan(node);
                });
            });
        }).observe(document.body, { childList: true, subtree: true });
    })();
</script>
