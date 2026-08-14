<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php $csrf = \App\Security\Security::csrfToken(); ?>
<section class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <h1 class="h3 mb-3">Sign in</h1>
        <p class="text-muted">Use your UNIZIK student/health-centre account to access your health records.</p>
        <p class="small text-muted"><span aria-hidden="true">*</span> Required field</p>

        <form method="post" action="<?= e(base_url('/auth/login')) ?>" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

            <div class="mb-3">
                <label for="identifier" class="form-label">Username or email <span aria-hidden="true">*</span></label>
                <input type="text" class="form-control" id="identifier" name="identifier"
                       autocomplete="username" required maxlength="190">
                <div class="form-text">Enter your username or institutional email.</div>
            </div>

            <div class="mb-3">
                <label for="password" class="form-label">Password <span aria-hidden="true">*</span></label>
                <input type="password" class="form-control" id="password" name="password"
                       autocomplete="current-password" required>
                <div class="form-text">Your password is stored as a strong one-way hash.</div>
            </div>

            <button type="submit" class="btn btn-primary w-100">Sign in</button>
        </form>

        <p class="mt-3 text-muted small">
            This is a decision-support system. AI outputs are informational and never a medical diagnosis.
        </p>

        <hr class="my-4">

        <p class="mb-0">
            New student? <a href="<?= e(base_url('/auth/register')) ?>">Create a student account</a> to get started.
        </p>
    </div>
</section>
