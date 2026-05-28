<?php

define('API_ACCESS', true);
require_once '../api/v1/config.php';
require_once '../includes/import.php';
require_once '../includes/AuthMiddleware.php';
require_once '../includes/NotificationManager.php';

$auth = initAuth();
$currentUser = $auth->getCurrentUser();

if (!$currentUser) {
    header('Location: /auth/login?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit();
}

try {
    $notificationManager = new NotificationManager();

        $notifications = $notificationManager->getUserNotifications($currentUser['id'], 100);
    $unreadCount = $notificationManager->getUnreadCount($currentUser['id']);

        $stats = [
        'total' => count($notifications),
        'unread' => $unreadCount,
        'read' => count($notifications) - $unreadCount
    ];

        $groupedNotifications = [];
    foreach ($notifications as $notif) {
        $date = date('Y-m-d', strtotime($notif['created_at']));
        $dateLabel = formatNotificationDate($date);

        if (!isset($groupedNotifications[$dateLabel])) {
            $groupedNotifications[$dateLabel] = [];
        }

        $groupedNotifications[$dateLabel][] = $notif;
    }

} catch (Exception $e) {
    error_log("Dashboard notifications error: " . $e->getMessage());
    $notifications = [];
    $stats = ['total' => 0, 'unread' => 0, 'read' => 0];
    $groupedNotifications = [];
}

function formatNotificationDate($date) {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    if ($date === $today) {
        return "Aujourd'hui";
    } elseif ($date === $yesterday) {
        return "Hier";
    } else {
        $timestamp = strtotime($date);
        return ucfirst(strftime('%A %d %B %Y', $timestamp));
    }
}

$seo = [
    'title' => 'Mes Notifications - Pilote Shopping',
    'description' => 'Consultez toutes vos notifications',
    'robots' => 'noindex, nofollow'
];

startHTMLPage($seo);
?>


<?php require_once '../includes/menu.php'; ?>


<main id="main-content" class="min-h-screen bg-dark-900 py-8">
    <div class="container mx-auto px-4 lg:px-6">

        <nav class="mb-8">
            <div class="flex items-center space-x-2 text-sm">
                <a href="/" class="text-gray-400 hover:text-white transition-colors">Accueil</a>
                <i class="fas fa-chevron-right text-gray-600"></i>
                <a href="/dashboard" class="text-gray-400 hover:text-white transition-colors">Dashboard</a>
                <i class="fas fa-chevron-right text-gray-600"></i>
                <span class="text-white font-medium">Notifications</span>
            </div>
        </nav>

        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between mb-8">
            <div>
                <h1 class="text-3xl md:text-4xl font-bold text-white mb-4">
                    <i class="fas fa-bell text-blue-primary mr-3"></i>
                    Mes Notifications
<?php if ($unreadCount > 0): ?>
                    <span class="inline-block ml-3 bg-red-500 text-white text-sm font-bold px-3 py-1 rounded-full">
<?= $unreadCount ?>
                    </span>
<?php endif; ?>
                </h1>
                <p class="text-gray-400">Toutes vos notifications en un seul endroit</p>
            </div>

<?php if ($stats['unread'] > 0): ?>
            <div class="mt-6 lg:mt-0">
                <button onclick="markAllAsRead()"
                        class="bg-blue-primary hover:bg-blue-secondary text-white font-bold py-3 px-6 rounded-xl transition-colors">
                    <i class="fas fa-check-double mr-2"></i>Tout marquer comme lu
                </button>
            </div>
<?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Total</p>
                        <p class="text-2xl font-bold text-white"><?= $stats['total'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-blue-primary/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-bell text-blue-primary text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Non lues</p>
                        <p class="text-2xl font-bold text-orange-400"><?= $stats['unread'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-orange-500/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-envelope text-orange-400 text-xl"></i>
                    </div>
                </div>
            </div>

            <div class="bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-6">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-400 text-sm">Lues</p>
                        <p class="text-2xl font-bold text-green-400"><?= $stats['read'] ?></p>
                    </div>
                    <div class="w-12 h-12 bg-green-500/20 rounded-xl flex items-center justify-center">
                        <i class="fas fa-envelope-open text-green-400 text-xl"></i>
                    </div>
                </div>
            </div>
        </div>

<?php if (empty($notifications)): ?>
        <div class="text-center py-16">
            <div class="w-24 h-24 bg-blue-500/20 rounded-full flex items-center justify-center mx-auto mb-6">
                <i class="fas fa-bell-slash text-blue-400 text-4xl"></i>
            </div>
            <h3 class="text-2xl font-bold text-white mb-4">Aucune notification</h3>
            <p class="text-gray-400">Vous n'avez pas encore reçu de notifications</p>
        </div>
<?php else: ?>
        <div class="space-y-8">
<?php foreach ($groupedNotifications as $dateLabel => $notifs): ?>
            <div class="notification-group">

                <div class="flex items-center mb-4">
                    <h2 class="text-lg font-bold text-white mr-4"><?= $dateLabel ?></h2>
                    <div class="flex-1 h-px bg-dark-600"></div>
                </div>

                <div class="space-y-3">
<?php foreach ($notifs as $notif): ?>
<?php
                        $colorClasses = [
                            'blue' => 'bg-blue-500/20 border-blue-500/30 text-blue-400',
                            'green' => 'bg-green-500/20 border-green-500/30 text-green-400',
                            'red' => 'bg-red-500/20 border-red-500/30 text-red-400',
                            'orange' => 'bg-orange-500/20 border-orange-500/30 text-orange-400',
                            'purple' => 'bg-purple-500/20 border-purple-500/30 text-purple-400',
                            'yellow' => 'bg-yellow-500/20 border-yellow-500/30 text-yellow-400'
                        ];
                        $colorClass = $colorClasses[$notif['color']] ?? $colorClasses['blue'];
                    ?>
                    <div class="notification-item bg-dark-800/50 backdrop-blur-sm rounded-2xl border border-dark-600 p-5 hover:border-dark-500 transition-all <?= !$notif['is_read'] ? 'unread' : '' ?>"
                         data-notification-id="<?= $notif['id'] ?>"
                         data-is-read="<?= $notif['is_read'] ?>"
                         data-link="<?= htmlspecialchars($notif['link'] ?? '') ?>">

                        <div class="flex items-start space-x-4">

                            <div class="w-12 h-12 rounded-xl flex items-center justify-center flex-shrink-0 <?= $colorClass ?>">
                                <i class="fas fa-<?= htmlspecialchars($notif['icon']) ?> text-lg"></i>
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between mb-2">
                                    <h3 class="font-semibold text-white <?= !$notif['is_read'] ? 'font-bold' : '' ?>">
<?= htmlspecialchars($notif['title']) ?>
<?php if (!$notif['is_read']): ?>
                                        <span class="inline-block w-2 h-2 bg-blue-primary rounded-full ml-2"></span>
<?php endif; ?>
                                    </h3>

                                    <span class="text-gray-500 text-xs ml-4 flex-shrink-0">
<?= date('H:i', strtotime($notif['created_at'])) ?>
                                    </span>
                                </div>

                                <p class="text-gray-400 text-sm mb-3">
<?= htmlspecialchars($notif['message']) ?>
                                </p>

                                <div class="flex items-center space-x-3">
<?php if ($notif['link']): ?>
                                    <a href="<?= htmlspecialchars($notif['link']) ?>"
                                       onclick="markNotificationAsRead(<?= $notif['id'] ?>)"
                                       class="text-blue-400 hover:text-blue-300 text-sm font-medium transition-colors">
                                        <i class="fas fa-external-link-alt mr-1"></i>Voir
                                    </a>
<?php endif; ?>

<?php if (!$notif['is_read']): ?>
                                    <button onclick="markNotificationAsRead(<?= $notif['id'] ?>)"
                                            class="text-green-400 hover:text-green-300 text-sm font-medium transition-colors">
                                        <i class="fas fa-check mr-1"></i>Marquer comme lu
                                    </button>
<?php endif; ?>

                                    <button onclick="deleteNotification(<?= $notif['id'] ?>)"
                                            class="text-red-400 hover:text-red-300 text-sm font-medium transition-colors">
                                        <i class="fas fa-trash mr-1"></i>Supprimer
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
<?php endforeach; ?>
                </div>
            </div>
<?php endforeach; ?>
        </div>
<?php endif; ?>
    </div>
</main>

<?php require_once '../includes/footer.php'; ?>


<script>

async function markNotificationAsRead(notificationId) {
    try {
        const response = await fetch('/api/v1/notifications/actions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'mark_as_read',
                notification_id: notificationId
            })
        });

        const result = await response.json();

        if (result.success) {

            const notifElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notifElement) {
                notifElement.classList.remove('unread');
                notifElement.dataset.isRead = '1';

                const badge = notifElement.querySelector('.w-2.h-2.bg-blue-primary');
                if (badge) {
                    badge.remove();
                }
            }

            updateNotificationBadge();
        }

    } catch (error) {
        console.error('Erreur:', error);
    }
}

async function markAllAsRead() {
    if (!confirm('Marquer toutes les notifications comme lues ?')) {
        return;
    }

    try {
        showLoading(true);

        const response = await fetch('/api/v1/notifications/actions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'mark_all_read'
            })
        });

        const result = await response.json();

        if (result.success) {
            showNotification(result.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            throw new Error(result.error);
        }

    } catch (error) {
        console.error('Erreur:', error);
        showNotification('Erreur lors du marquage', 'error');
    } finally {
        showLoading(false);
    }
}

async function deleteNotification(notificationId) {
    if (!confirm('Supprimer cette notification ?')) {
        return;
    }

    try {
        const response = await fetch('/api/v1/notifications/actions', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'delete',
                notification_id: notificationId
            })
        });

        const result = await response.json();

        if (result.success) {

            const notifElement = document.querySelector(`[data-notification-id="${notificationId}"]`);
            if (notifElement) {
                notifElement.style.opacity = '0';
                notifElement.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    notifElement.remove();
                    checkEmptyGroups();
                }, 300);
            }

            showNotification('Notification supprimée', 'success');
            updateNotificationBadge();
        } else {
            throw new Error(result.error);
        }

    } catch (error) {
        console.error('Erreur:', error);
        showNotification('Erreur lors de la suppression', 'error');
    }
}

function checkEmptyGroups() {
    const groups = document.querySelectorAll('.notification-group');
    groups.forEach(group => {
        const items = group.querySelectorAll('.notification-item');
        if (items.length === 0) {
            group.remove();
        }
    });

    const remaining = document.querySelectorAll('.notification-item');
    if (remaining.length === 0) {
        location.reload();
    }
}

function updateNotificationBadge() {
    fetch('/api/v1/notifications/list?unread=true')
        .then(res => res.json())
        .then(data => {
            const badge = document.getElementById('notification-badge');
            if (badge) {
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count;
                    badge.style.display = 'flex';
                } else {
                    badge.style.display = 'none';
                }
            }
        })
        .catch(err => console.error('Erreur mise à jour badge:', err));
}

document.addEventListener('click', function(e) {
    const notifItem = e.target.closest('.notification-item');
    if (notifItem && notifItem.dataset.link && !e.target.closest('button') && !e.target.closest('a')) {
        const notifId = notifItem.dataset.notificationId;
        const link = notifItem.dataset.link;

        if (link) {
            markNotificationAsRead(notifId);
            setTimeout(() => {
                window.location.href = link;
            }, 200);
        }
    }
});

function showLoading(show) {
    let loader = document.getElementById('page-loader');

    if (show && !loader) {
        loader = document.createElement('div');
        loader.id = 'page-loader';
        loader.className = 'fixed inset-0 z-50 bg-black/50 backdrop-blur-sm flex items-center justify-center';
        loader.innerHTML = `
            <div class="bg-dark-800 rounded-2xl border border-dark-600 p-8 text-center">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-primary mx-auto mb-4"></div>
                <p class="text-white">Traitement en cours...</p>
            </div>
        `;
        document.body.appendChild(loader);
    } else if (!show && loader) {
        document.body.removeChild(loader);
    }
}

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-xl text-white font-medium transition-all duration-300 transform translate-x-full`;

    switch (type) {
        case 'success':
            notification.className += ' bg-green-500';
            break;
        case 'error':
            notification.className += ' bg-red-500';
            break;
        default:
            notification.className += ' bg-blue-primary';
    }

    notification.innerHTML = `
        <div class="flex items-center space-x-2">
            <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'times' : 'info'}-circle"></i>
            <span>${message}</span>
        </div>
    `;

    document.body.appendChild(notification);

    setTimeout(() => {
        notification.classList.remove('translate-x-full');
    }, 100);

    setTimeout(() => {
        notification.classList.add('translate-x-full');
        setTimeout(() => {
            document.body.removeChild(notification);
        }, 300);
    }, 5000);
}
</script>

<style>

.notification-item.unread {
    background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(59, 130, 246, 0.02) 100%);
    border-color: rgba(59, 130, 246, 0.3);
}

.notification-item[data-link]:not([data-link=""]) {
    cursor: pointer;
}

.notification-item[data-link]:not([data-link=""]):hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(59, 130, 246, 0.15);
}

.notification-item {
    transition: all 0.3s ease;
}

@media (max-width: 768px) {
    .notification-item .flex-1 {
        min-width: 0;
    }
}
</style>

<?php
endHTMLPage();
?>
