/**
 * phpKanMaster PWA Registration
 * Handles service worker registration and install prompt
 */

window.App = window.App || {};
App.PWA = {
    init() {
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                this.registerServiceWorker();
            });
        }

        // Handle install prompt
        this.setupInstallPrompt();
    },

    async registerServiceWorker() {
        try {
            const registration = await navigator.serviceWorker.register('/sw.js', {
                scope: '/',
            });

            // Check for updates periodically
            setInterval(() => {
                registration.update();
            }, 60 * 1000);

            registration.addEventListener('updatefound', () => {
                const newWorker = registration.installing;
                if (newWorker) {
                    newWorker.addEventListener('statechange', () => {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            this.showUpdateToast();
                        }
                    });
                }
            });

            console.log('Service Worker registered successfully');
        } catch (error) {
            console.error('Service Worker registration failed:', error);
        }
    },

    setupInstallPrompt() {
        // Already running as installed PWA — don't show install prompt
        if (window.matchMedia('(display-mode: standalone)').matches) return;

        // iOS: no beforeinstallprompt support — show manual instructions once
        const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
        if (isIOS) {
            const dismissed = localStorage.getItem('pwa-ios-dismissed');
            if (dismissed) return;
            this.showIOSInstallHint();
            return;
        }

        let deferredPrompt = null;

        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
            // Don't show on first load — wait for engagement signal
            this._deferredPrompt = deferredPrompt;
            this._engageListeners();
        });

        window.addEventListener('appinstalled', () => {
            deferredPrompt = null;
            this._deferredPrompt = null;
            const installToast = document.getElementById('pwa-install-toast');
            if (installToast) installToast.remove();
            if (window.App?.Alerts?.Toast) {
                window.App.Alerts.Toast.fire({
                    icon: 'success',
                    title: 'App installed successfully!',
                });
            }
        });
    },

    _engageListeners() {
        // Wait for meaningful user interaction before showing the prompt
        const engageEvents = ['click', 'scroll', 'touchstart'];
        const showOnce = () => {
            if (this._deferredPrompt) {
                this.showInstallToast(this._deferredPrompt);
                this._deferredPrompt = null;
                engageEvents.forEach(ev => document.removeEventListener(ev, showOnce));
            }
        };
        engageEvents.forEach(ev => document.addEventListener(ev, showOnce, { once: true }));
    },

    showIOSInstallHint() {
        const toast = document.createElement('div');
        toast.id = 'pwa-install-toast';
        toast.className = 'toast show';
        toast.setAttribute('role', 'alert');

        const toastBody = document.createElement('div');
        toastBody.style.cssText = 'display:flex;align-items:flex-start;justify-content:space-between;gap:12px;padding:12px 16px;background-color:#2d333b;color:#fff;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);';

        const content = document.createElement('div');
        content.style.cssText = 'flex:1;';

        const title = document.createElement('div');
        title.style.cssText = 'font-weight:600;margin-bottom:4px;';
        title.textContent = 'Install phpKanMaster';
        content.appendChild(title);

        const steps = document.createElement('div');
        steps.style.cssText = 'font-size:0.8rem;line-height:1.4;';

        const step1 = document.createElement('div');
        step1.innerHTML = '1. Tap the share button <strong>↑</strong>';
        const step2 = document.createElement('div');
        step2.innerHTML = '2. Scroll down and tap <strong>"Add to Home Screen"</strong>';
        const step3 = document.createElement('div');
        step3.innerHTML = '3. Tap <strong>"Add"</strong> in the top right';

        steps.appendChild(step1);
        steps.appendChild(step2);
        steps.appendChild(step3);
        content.appendChild(steps);
        toastBody.appendChild(content);

        const dismissBtn = document.createElement('button');
        dismissBtn.id = 'pwa-dismiss-btn';
        dismissBtn.className = 'btn btn-sm btn-outline-secondary';
        dismissBtn.textContent = '✕';
        dismissBtn.type = 'button';
        dismissBtn.style.cssText = 'align-self:flex-start;';
        dismissBtn.addEventListener('click', () => {
            localStorage.setItem('pwa-ios-dismissed', '1');
            toast.remove();
        });
        toastBody.appendChild(dismissBtn);
        toast.appendChild(toastBody);

        let container = document.getElementById('pwa-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'pwa-toast-container';
            container.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;';
            document.body.appendChild(container);
        }
        container.appendChild(toast);
    },

    showInstallToast(deferredPrompt) {
        const existing = document.getElementById('pwa-install-toast');
        if (existing) existing.remove();

        let container = document.getElementById('pwa-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'pwa-toast-container';
            container.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:9999;';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.id = 'pwa-install-toast';
        toast.className = 'toast show';
        toast.setAttribute('role', 'alert');
        
        const toastBody = document.createElement('div');
        toastBody.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:12px;padding:12px 16px;background-color:#2d333b;color:#fff;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.3);';
        
        const message = document.createElement('span');
        message.textContent = 'Install phpKanMaster for offline access';
        toastBody.appendChild(message);
        
        const btnGroup = document.createElement('div');
        btnGroup.style.cssText = 'display:flex;gap:8px;';
        
        const installBtn = document.createElement('button');
        installBtn.id = 'pwa-install-btn';
        installBtn.className = 'btn btn-sm btn-primary';
        installBtn.textContent = 'Install';
        installBtn.type = 'button';
        
        const dismissBtn = document.createElement('button');
        dismissBtn.id = 'pwa-dismiss-btn';
        dismissBtn.className = 'btn btn-sm btn-outline-secondary';
        dismissBtn.textContent = 'Later';
        dismissBtn.type = 'button';
        
        btnGroup.appendChild(installBtn);
        btnGroup.appendChild(dismissBtn);
        toastBody.appendChild(btnGroup);
        toast.appendChild(toastBody);
        container.appendChild(toast);

        installBtn.addEventListener('click', async () => {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;
                console.log('Install prompt outcome: ' + outcome);
            }
            toast.remove();
        });

        dismissBtn.addEventListener('click', () => {
            sessionStorage.setItem('pwa-install-dismissed', '1');
            toast.remove();
        });
    },

    showUpdateToast() {
        if (window.App?.Alerts?.Toast) {
            window.App.Alerts.Toast.fire({
                icon: 'info',
                title: 'New version available! Refresh to update.',
                timer: null,
            });
        }
    },

    setupConnectivityMonitoring() {
        window.addEventListener('online', () => {
            if (window.App?.Alerts?.Toast) {
                window.App.Alerts.Toast.fire({
                    icon: 'success',
                    title: 'Back online! Syncing...',
                    timer: 2000,
                });
            }
        });

        window.addEventListener('offline', () => {
            if (window.App?.Alerts?.Toast) {
                window.App.Alerts.Toast.fire({
                    icon: 'warning',
                    title: 'You are offline. Changes will sync when reconnected.',
                    timer: 3000,
                });
            }
        });
    },
};

document.addEventListener('DOMContentLoaded', () => {
    if (window.App && window.App.PWA) {
        App.PWA.init();
        App.PWA.setupConnectivityMonitoring();
    }
});
