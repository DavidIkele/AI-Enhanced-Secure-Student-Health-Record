<?php
/** @var int $status */
/** @var string $title */
/** @var string $message */
/** @var string $details */
$status = $status ?? 500;
$title = $title ?? 'Something went wrong';
$message = $message ?? 'An unexpected error occurred. Please try again later.';
$details = $details ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="<?= e(base_url('assets/vendor/bootstrap/css/bootstrap.min.css')) ?>">
    <link rel="stylesheet" href="<?= e(base_url('assets/css/app.css')) ?>">
</head>
<body class="d-flex flex-column min-vh-100">
    <a class="skip-link" href="#main-content">Skip to main content</a>
    <main id="main-content" class="flex-fill d-flex align-items-center">
        <div class="container text-center my-5 py-5" role="alert">
            <p class="display-1 fw-bold text-muted"><?= e((string) $status) ?></p>
            <h1 class="h3"><?= e($title) ?></h1>
            <p class="lead mx-auto col-lg-7"><?= e($message) ?></p>
            <?= $details ?>
            <p class="mt-4">
                <a class="btn btn-primary" href="<?= e(base_url('/')) ?>">Return to home page</a>
            </p>
        </div>
    </main>
</body>
</html>