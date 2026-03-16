// user-push.js
// Handles Browser Push Notifications for Users (Staking Maturity)

(function () {
    // Sound Effect
    const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');

    // UI: Toast for Permission Request
    // We only show this once if permission is 'default'
    function showPermissionToast() {
        if (Notification.permission === 'granted' || Notification.permission === 'denied') return;
        // Check local storage to avoid pestering if they closed it before? 
        // For now, simple logic.

        const toast = document.createElement('div');
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast show align-items-center text-white bg-glass border border-secondary" role="alert" aria-live="assertive" aria-atomic="true" style="backdrop-filter: blur(10px); background: rgba(11,11,15,0.9);">
                <div class="d-flex">
                    <div class="toast-body">
                        <i class="fas fa-bell text-warning me-2"></i>
                        Get notified when your stakes mature?
                    </div>
                    <button type="button" class="btn btn-sm btn-primary me-2" id="btn-enable-push">Yes</button>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        document.body.appendChild(toast);

        document.getElementById('btn-enable-push').addEventListener('click', () => {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    new Notification('Notifications Enabled', {
                        body: 'You will be alerted when your investments mature.',
                        icon: 'assets/images/logo-icon.png'
                    });
                    toast.remove();
                }
            });
        });
    }

    // Polling Function
    async function checkNotifications() {
        try {
            // Path adjustment: This script is in assets/js, so relative to API is ... wait.
            // When included in index.php (root), API is 'api/check...'.
            // Let's use absolute path or root-relative if possible. 
            // We'll rely on relative from Root since this JS is loaded in root pages.

            const res = await fetch('api/check_user_notifications.php');
            const data = await res.json();

            if (data.notifications && data.notifications.length > 0) {
                data.notifications.forEach(notif => {
                    if (Notification.permission === 'granted') {
                        audio.play().catch(e => { }); // Ignore user gesture errors

                        const n = new Notification(notif.title, {
                            body: notif.message,
                            icon: 'https://cdn-icons-png.flaticon.com/512/10332/10332306.png', // Money/Stake Icon
                            tag: 'user-stake-' + notif.id
                        });

                        n.onclick = function () {
                            window.window.focus();
                            window.location.href = notif.link;
                            n.close();
                        };
                    }
                });
            }

        } catch (e) {
            console.error("User Push Error", e);
        }
    }

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        if ("Notification" in window) {
            setTimeout(showPermissionToast, 3000); // Delay a bit so it's not aggressive

            // Poll every 30 seconds (Staking isn't that fast)
            setInterval(checkNotifications, 30000);

            checkNotifications();
        }
    });

})();
