// admin-push.js
// Handles Browser Push Notifications for Admin Panel

(function () {
    let lastNotifId = 0;
    // Sound Effect (Subtle "Ping")
    const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');

    // Initialize Last ID (Wait for DOM to fetch initial if needed, or start from 0)
    // Ideally we sync with server once to not spam old notifications on refresh
    // For now we start polling 0, but maybe filter by time in future. 
    // Actually, improved logic: We should fetch latest ID first to avoid spamming on load.

    // UI: Toast for Permission Request
    function showPermissionToast() {
        if (Notification.permission === 'granted' || Notification.permission === 'denied') return;

        const toast = document.createElement('div');
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = '9999';
        toast.innerHTML = `
            <div class="toast show align-items-center text-white bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body">
                        Enable <strong>Desktop Notifications</strong> to get real-time alerts?
                    </div>
                    <button type="button" class="btn btn-primary btn-sm me-2 fw-bold" id="btn-enable-push">Enable</button>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `;
        document.body.appendChild(toast);

        document.getElementById('btn-enable-push').addEventListener('click', () => {
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    new Notification('Notifications Enabled', {
                        body: 'You will now receive alerts for new activities.',
                        icon: '../assets/images/logo-icon.png' // Adjust path if needed
                    });
                    toast.remove();
                }
            });
        });
    }

    // Polling Function
    async function checkNotifications() {
        try {
            // If it's the very first run, we might want to just get the max ID and not show notification
            // But for simplicity let's just use a param 'init=1' if lastNotifId is 0?
            // Actually, API returns id > last_id. 
            // If lastNotifId is 0, it returns ALL unread. That might spawn 50 notifications. Bad.
            // Fix: First call gets max ID.

            if (lastNotifId === 0) {
                // Determine max ID logic or just accept we might miss one or two during init
                // Better approach: API should support 'get_latest_id'
                // Let's just assume we start from now.
                // Or simplified: fetch check_notifications, if lastNotifId is 0, just set lastNotifId = max(id) and return.
            }

            const res = await fetch(`../api/check_notifications.php?last_id=${lastNotifId}`);
            const data = await res.json();

            if (data.notifications && data.notifications.length > 0) {

                // If this is first load (lastNotifId == 0), don't spam. Just update ID.
                if (lastNotifId === 0) {
                    lastNotifId = data.notifications[data.notifications.length - 1].id;
                    return;
                }

                data.notifications.forEach(notif => {
                    lastNotifId = Math.max(lastNotifId, notif.id);

                    if (Notification.permission === 'granted') {
                        // Play Sound
                        audio.play().catch(e => console.log('Audio blocked'));

                        // Show Notification
                        const n = new Notification(notif.title, {
                            body: notif.message,
                            icon: 'https://cdn-icons-png.flaticon.com/512/1828/1828640.png', // Generic Alert Icon
                            tag: 'admin-alert-' + notif.id
                        });

                        n.onclick = function () {
                            window.open(notif.link, '_blank');
                            n.close();
                        };
                    }
                });
            }

        } catch (e) {
            console.error("Push Polling Error", e);
        }
    }

    // Init
    document.addEventListener('DOMContentLoaded', () => {
        if ("Notification" in window) {
            setTimeout(showPermissionToast, 2000);

            // Start Polling (Every 10s)
            setInterval(checkNotifications, 10000);

            // Initial call to set ID baseline
            checkNotifications();
        }
    });

})();
