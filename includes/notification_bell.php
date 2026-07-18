<?php
/**
 * Shared Notification Bell UI Component for BDMS
 * Include this file inside the navbar (e.g., right before Logout)
 */

require_once __DIR__ . '/notification_service.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    return;
}

$userId = $_SESSION['user_id'];

// Automatically evaluate and trigger dynamic reminders
check_and_trigger_reminders($userId);

// Get initial unread count and latest notifications
$unreadNotifications = get_notifications($userId, 10, true);
$unreadCount = count($unreadNotifications);
$recentNotifications = get_notifications($userId, 5, false);
?>

<!-- Notification Bell Container -->
<div class="notification-container" id="notificationContainer">
    <button class="notification-bell-btn" id="notificationBellBtn" aria-label="Notifications" aria-haspopup="true">
        <svg viewBox="0 0 24 24" class="bell-icon">
            <path d="M12 22c1.1 0 2-.9 2-2h-4c0 1.1.89 2 2 2zm6-6v-5c0-3.07-1.64-5.64-4.5-6.32V4c0-.83-.67-1.5-1.5-1.5s-1.5.67-1.5 1.5v.68C7.63 5.36 6 7.92 6 11v5l-2 2v1h16v-1l-2-2z"/>
        </svg>
        <span class="bell-badge <?php echo $unreadCount > 0 ? 'active' : ''; ?>" id="notificationBadge">
            <?php echo $unreadCount; ?>
        </span>
    </button>

    <!-- Dropdown Menu -->
    <div class="notification-dropdown" id="notificationDropdown" role="menu" aria-label="Notifications Dropdown">
        <div class="dropdown-header">
            <h3>Notifications</h3>
            <?php if ($unreadCount > 0): ?>
                <button class="mark-all-btn" id="markAllReadBtn">Mark all as read</button>
            <?php endif; ?>
        </div>
        
        <div class="dropdown-body" id="notificationList">
            <?php if (empty($recentNotifications)): ?>
                <div class="empty-state">
                    <svg viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 15h-2v-6h2v6zm0-8h-2V7h2v2z"/>
                    </svg>
                    <p>No notifications yet</p>
                </div>
            <?php else: ?>
                <?php foreach ($recentNotifications as $notif): ?>
                    <div class="notification-item <?php echo $notif['is_read'] ? 'read' : 'unread'; ?> type-<?php echo $notif['type']; ?>" 
                         data-id="<?php echo $notif['id']; ?>" 
                         data-link="<?php echo htmlspecialchars($notif['link'] ?? ''); ?>"
                         role="menuitem">
                        <div class="notif-icon">
                            <?php if ($notif['type'] === 'success'): ?>
                                <span class="icon-dot success">✓</span>
                            <?php elseif ($notif['type'] === 'warning' || $notif['type'] === 'error'): ?>
                                <span class="icon-dot warning">⚠</span>
                            <?php elseif ($notif['type'] === 'blood_request'): ?>
                                <span class="icon-dot urgent">🩸</span>
                            <?php else: ?>
                                <span class="icon-dot info">ℹ</span>
                            <?php endif; ?>
                        </div>
                        <div class="notif-content">
                            <h4 class="notif-title"><?php echo htmlspecialchars($notif['title']); ?></h4>
                            <p class="notif-message"><?php echo htmlspecialchars($notif['message']); ?></p>
                            <span class="notif-time"><?php echo date('M d, h:i A', strtotime($notif['created_at'])); ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
/* Notification Bell Styles */
.notification-container {
    position: relative;
    display: inline-block;
}

.notification-bell-btn {
    background: none;
    border: none;
    cursor: pointer;
    position: relative;
    padding: 8px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--muted, #64748b);
    transition: all 0.2s ease;
    outline: none;
}

.notification-bell-btn:hover {
    background: rgba(24, 26, 27, 0.05);
    color: var(--ink, #0f172a);
}

.bell-icon {
    width: 22px;
    height: 22px;
    fill: currentColor;
    transition: transform 0.2s ease;
}

.notification-bell-btn:hover .bell-icon {
    transform: rotate(15deg);
}

.bell-badge {
    position: absolute;
    top: 4px;
    right: 4px;
    background-color: var(--crimson, #dc2626);
    color: #ffffff;
    font-size: 10px;
    font-weight: 700;
    min-width: 16px;
    height: 16px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 4px;
    border: 2px solid var(--card, #ffffff);
    opacity: 0;
    transform: scale(0);
    transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
}

.bell-badge.active {
    opacity: 1;
    transform: scale(1);
}

/* Dropdown styling */
.notification-dropdown {
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    width: 320px;
    background: var(--card, #ffffff);
    border: 1px solid var(--line, #e2e8f0);
    border-radius: 12px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    z-index: 1000;
    opacity: 0;
    transform: translateY(-10px) scale(0.95);
    pointer-events: none;
    transition: all 0.2s cubic-bezier(0.165, 0.84, 0.44, 1);
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.notification-dropdown.show {
    opacity: 1;
    transform: translateY(0) scale(1);
    pointer-events: auto;
}

.dropdown-header {
    padding: 12px 16px;
    border-bottom: 1px solid var(--line, #e2e8f0);
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: rgba(24, 26, 27, 0.01);
}

.dropdown-header h3 {
    margin: 0;
    font-size: 14px;
    font-weight: 600;
    color: var(--ink, #0f172a);
}

.mark-all-btn {
    background: none;
    border: none;
    color: var(--crimson, #dc2626);
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    padding: 2px 6px;
    border-radius: 4px;
    transition: background 0.15s;
}

.mark-all-btn:hover {
    background: var(--rose, #fef2f2);
}

.dropdown-body {
    max-height: 320px;
    overflow-y: auto;
}

/* Scrollbar styles */
.dropdown-body::-webkit-scrollbar {
    width: 6px;
}
.dropdown-body::-webkit-scrollbar-track {
    background: transparent;
}
.dropdown-body::-webkit-scrollbar-thumb {
    background: var(--line, #cbd5e1);
    border-radius: 3px;
}

.empty-state {
    padding: 32px 16px;
    text-align: center;
    color: var(--muted, #64748b);
}

.empty-state svg {
    width: 40px;
    height: 40px;
    fill: currentColor;
    margin-bottom: 8px;
    opacity: 0.5;
}

.empty-state p {
    margin: 0;
    font-size: 13px;
}

/* Notification Item styling */
.notification-item {
    display: flex;
    gap: 12px;
    padding: 12px 16px;
    border-bottom: 1px solid var(--line, #e2e8f0);
    cursor: pointer;
    transition: background 0.2s ease;
    position: relative;
}

.notification-item:last-child {
    border-bottom: none;
}

.notification-item:hover {
    background: rgba(24, 26, 27, 0.02);
}

.notification-item.unread {
    background: rgba(220, 38, 38, 0.02); /* Slight red tint for unread */
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    left: 4px;
    top: 50%;
    transform: translateY(-50%);
    width: 6px;
    height: 6px;
    background-color: var(--crimson, #dc2626);
    border-radius: 50%;
}

.notif-icon {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.icon-dot {
    font-size: 12px;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
}

.icon-dot.success {
    background-color: #dcfce7;
    color: #15803d;
}

.icon-dot.warning {
    background-color: #fef9c3;
    color: #a16207;
}

.icon-dot.urgent {
    background-color: #ffe4e6;
    color: #e11d48;
}

.icon-dot.info {
    background-color: #e0f2fe;
    color: #0369a1;
}

.notif-content {
    flex-grow: 1;
}

.notif-title {
    margin: 0 0 2px 0;
    font-size: 13px;
    font-weight: 600;
    color: var(--ink, #0f172a);
}

.notif-message {
    margin: 0 0 4px 0;
    font-size: 12px;
    color: var(--muted, #64748b);
    line-height: 1.4;
}

.notif-time {
    font-size: 10px;
    color: var(--muted, #94a3b8);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('notificationContainer');
    const bellBtn = document.getElementById('notificationBellBtn');
    const dropdown = document.getElementById('notificationDropdown');
    const badge = document.getElementById('notificationBadge');
    const markAllBtn = document.getElementById('markAllReadBtn');
    const notifList = document.getElementById('notificationList');

    if (!bellBtn || !dropdown) return;

    // Toggle dropdown
    bellBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('show');
    });

    // Close dropdown on click outside
    document.addEventListener('click', function(e) {
        if (!container.contains(e.target)) {
            dropdown.classList.remove('show');
        }
    });

    // Mark individual notification as read
    notifList.addEventListener('click', function(e) {
        const item = e.target.closest('.notification-item');
        if (!item) return;

        const id = item.dataset.id;
        const link = item.dataset.link;

        if (item.classList.contains('unread')) {
            fetch('api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_read',
                    id: id
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    item.classList.remove('unread');
                    item.classList.add('read');
                    
                    // Decrement badge count
                    let count = parseInt(badge.textContent) || 0;
                    count = Math.max(0, count - 1);
                    badge.textContent = count;
                    if (count === 0) {
                        badge.classList.remove('active');
                        if (markAllBtn) markAllBtn.style.display = 'none';
                    }
                    
                    if (link) {
                        window.location.href = link;
                    }
                }
            })
            .catch(err => {
                console.error("Error marking notification read", err);
                if (link) window.location.href = link;
            });
        } else {
            if (link) {
                window.location.href = link;
            }
        }
    });

    // Mark all as read
    if (markAllBtn) {
        markAllBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            fetch('api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'mark_all_read'
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // Update all UI items to read
                    document.querySelectorAll('.notification-item.unread').forEach(item => {
                        item.classList.remove('unread');
                        item.classList.add('read');
                    });
                    
                    badge.textContent = '0';
                    badge.classList.remove('active');
                    markAllBtn.style.display = 'none';
                }
            })
            .catch(err => console.error("Error marking all read", err));
        });
    }
});
</script>
