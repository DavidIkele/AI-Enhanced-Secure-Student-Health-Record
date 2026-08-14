<?php /** @var string $title */ ?>
<?php /** @var string $content */ ?>
<?php /** @var string $page */ ?>
<?php $flashMessages = \App\Core\Session::flushFlash(); ?>
<?php $siteAuth = new \App\Services\AuthService(); ?>
<?php $siteSignedIn = $siteAuth->check(); ?>
<?php if ($siteSignedIn) { $siteUserId = $siteAuth->id(); $siteDashboard = $siteUserId !== null && (\App\Security\AccessControl::can((int) $siteUserId, 'records.manage') || \App\Security\AccessControl::can((int) $siteUserId, 'analytics.view')); } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="AI-enhanced secure web-based student health record management system for UNIZIK students.">
    <meta name="csrf-token" content="<?= e(\App\Security\Security::csrfToken()) ?>">
    <meta name="base-url" content="<?= e(base_url('')) ?>">
    <title><?= e($title ?? app_name()) ?></title>
    <link rel="icon" href="<?= e(base_url('assets/img/Students%20health.png')) ?>" type="image/png">
    <link rel="stylesheet" href="<?= e(base_url('assets/vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
</head>
<body class="d-flex flex-column min-vh-100">
    <a class="skip-link" href="#main-content">Skip to main content</a>

    <header class="site-header sticky-top" id="site-header">
        <div class="site-accent" aria-hidden="true"></div>
        <div class="container">
            <div class="d-flex flex-wrap align-items-center justify-content-between py-2 gap-2">
                <a class="site-brand d-flex align-items-center gap-2 text-decoration-none" href="<?= e(base_url('/')) ?>" aria-label="<?= e(app_name()) ?>">
                    <img class="site-brand-mark" src="<?= e(base_url('assets/img/Students%20health.png')) ?>" alt="" width="36" height="36">
                    <span class="d-flex flex-column lh-sm">
                        <span class="site-brand-text">Student Health Record System</span>
                        <span class="site-brand-sub">UNIZIK Health Centre</span>
                    </span>
                </a>
                <button class="site-nav-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#primary-nav" aria-controls="primary-nav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="hamburger" aria-hidden="true"></span>
                </button>
            </div>
            <div class="collapse site-nav-collapse" id="primary-nav">
                <?php include APP_PATH . '/Views/partials/nav.php'; ?>
            </div>
        </div>
    </header>

    <main id="main-content" class="container flex-fill py-4">

        <?php if (!empty($flashMessages)): ?>
            <?php foreach ($flashMessages as $type => $text): ?>
                <div class="alert alert-<?= e(in_array($type, ['success', 'danger', 'warning', 'info'], true) ? $type : 'info') ?> alert-dismissible fade show" role="alert">
                    <?= e($text) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <footer class="site-footer mt-auto">
        <div class="container py-4">
            <div class="row g-4 pb-3">
                <div class="col-md-6 col-lg-4">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img class="site-brand-mark" src="<?= e(base_url('assets/img/Students%20health.png')) ?>" alt="" width="36" height="36">
                        <span class="fw-semibold">Student Health Record System</span>
                    </div>
                    <p class="small text-muted mb-0">
                        AI-enhanced, secure web-based student health record management for
                        Nnamdi Azikiwe University health centre.
                    </p>
                </div>
                <div class="col-md-3 col-lg-2 ms-lg-auto">
                    <h6 class="small fw-semibold text-uppercase mb-2">Platform</h6>
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-1"><a href="<?= e(base_url('/')) ?>">Home</a></li>
                        <li class="mb-1"><?php if ($siteSignedIn): ?><a href="<?= e(base_url($siteDashboard ? '/dashboard' : '/profile')) ?>"><?= e($siteDashboard ? 'Dashboard' : 'My profile') ?></a><?php else: ?><a href="<?= e(base_url('/auth/login')) ?>">Sign in</a><?php endif; ?></li>
                        <li class="mb-1"><a href="<?= e(base_url('/system/health')) ?>">System health</a></li>
                    </ul>
                </div>
                <div class="col-md-3 col-lg-3">
                    <h6 class="small fw-semibold text-uppercase mb-2">Information</h6>
                    <ul class="list-unstyled mb-0 small">
                        <li class="mb-1"><a href="<?= e(base_url('/#features')) ?>">Features</a></li>
                        <li class="mb-1"><a href="<?= e(base_url('/#audiences')) ?>">Who it is for</a></li>
                        <li class="mb-1"><a href="<?= e(base_url('/#security')) ?>">Security &amp; privacy</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-top pt-3">
                <p class="small text-muted mb-2">
                    Decision-support system. AI outputs are informational and never a medical diagnosis.
                </p>
                <p class="small text-muted mb-0">
                    &copy; <?= date('Y') ?> Nnamdi Azikiwe University Health Centre. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <?php if (!empty($extra_scripts) && is_array($extra_scripts)): ?>
        <?php foreach ($extra_scripts as $src): ?>
            <script src="<?= e($src) ?>" defer></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <script src="<?= e(base_url('assets/vendor/bootstrap/js/bootstrap.bundle.min.js')) ?>" defer></script>
    <script src="<?= e(base_url('assets/js/app.js')) ?>" defer></script>
</body>
</html>