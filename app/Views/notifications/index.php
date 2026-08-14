<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var array<int, array<string, mixed>> $notifications */ ?>
<?php /** @var int $unreadCount */ ?>
<?php /** @var bool $canBroadcast */ ?>
<?php $typeBadge = ['appointment' => 'info', 'alert' => 'warning', 'outbreak' => 'danger', 'system' => 'secondary']; ?>
<section aria-labelledby="notifications-heading">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item active" aria-current="page">Notifications</li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h1 id="notifications-heading" class="h3 mb-0">Notifications</h1>
        <?php if ($unreadCount > 0): ?>
            <form method="post" action="<?= e(base_url('/notifications/read-all')) ?>">
                <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
                <button type="submit" class="btn btn-sm btn-outline-primary" data-confirm="Mark all notifications as read?">Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>
    <p class="lead">
        <?= $unreadCount > 0 ? $unreadCount . ' unread notification' . ($unreadCount === 1 ? '' : 's') . '.' : 'You have no unread notifications.' ?>
    </p>

    <?php if ($notifications === []): ?>
        <div class="alert alert-secondary" role="status">No notifications.</div>
    <?php else: ?>
        <ul class="list-unstyled" aria-label="Notification list">
            <?php foreach ($notifications as $notification): ?>
                <?php $badge = $typeBadge[$notification['type']] ?? 'light'; ?>
                <li class="border rounded p-3 mb-2<?= $notification['is_read'] ? ' bg-body-tertiary' : '' ?>">
                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                        <div>
                            <div class="d-flex flex-wrap align-items-center gap-2">
                                <span class="badge text-bg-<?= $badge ?>"><?= e($notification['type']) ?></span>
                                <?php if (!$notification['is_read']): ?>
                                    <span class="badge text-bg-primary">New</span>
                                <?php endif; ?>
                                <span class="small text-muted"><?= e(date('D, j M Y H:i', strtotime((string) $notification['created_at']))) ?></span>
                            </div>
                            <h2 class="h6 mb-1 mt-1"><?= e($notification['title']) ?></h2>
                            <?php if ($notification['body'] !== null && $notification['body'] !== ''): ?>
                                <p class="mb-1"><?= e($notification['body']) ?></p>
                            <?php endif; ?>
                        </div>
                        <?php if (!$notification['is_read']): ?>
                            <form method="post" action="<?= e(base_url('/notifications/' . (int) $notification['id'] . '/read')) ?>" class="flex-shrink-0">
                                <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
                                <span class="visually-hidden">Mark notification &ldquo;<?= e($notification['title']) ?>&rdquo; as read</span>
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Mark as read</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($canBroadcast): ?>
        <hr>
        <h2 class="h5">Send a system announcement</h2>
        <p class="text-muted small">Delivered to every active user. Keep the message generic; never include clinical detail. <span aria-hidden="true">*</span> required.</p>
        <form method="post" action="<?= e(base_url('/notifications/system')) ?>" class="row g-3" novalidate>
            <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
            <div class="col-md-4">
                <label for="system_title" class="form-label">Title <span aria-hidden="true">*</span></label>
                <input type="text" class="form-control" id="system_title" name="title" required maxlength="80">
            </div>
            <div class="col-md-8">
                <label for="system_body" class="form-label">Message <span aria-hidden="true">*</span></label>
                <input type="text" class="form-control" id="system_body" name="body" required maxlength="150">
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary" data-confirm="Send this announcement to all users?">Send announcement</button>
            </div>
        </form>
    <?php endif; ?>
</section>
