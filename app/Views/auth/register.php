<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var array<string, array<int, string>> $errors */ ?>
<?php /** @var array<string, mixed> $old */ ?>
<?php $csrf = \App\Security\Security::csrfToken(); ?>
<section class="row justify-content-center">
    <div class="col-lg-9">
        <h1 class="h3 mb-1">Create a student account</h1>
        <p class="text-muted mb-3">
            Register with your UNIZIK details to request appointments, receive health alerts
            and view your own insights.
        </p>

        <?php if (!empty($errors['_form'])): ?>
            <div class="alert alert-danger" role="alert"><?= e($errors['_form'][0]) ?></div>
        <?php endif; ?>

        <p class="small text-muted"><span aria-hidden="true">*</span> Required field</p>

        <form method="post" action="<?= e(base_url('/auth/register')) ?>" class="row g-3 mb-4" novalidate>
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

            <h2 class="h5 mt-2 col-12 border-bottom pb-1">Account details</h2>

            <div class="col-md-6">
                <label for="username" class="form-label">Username <span aria-hidden="true">*</span></label>
                <input type="text" class="form-control<?= isset($errors['username']) ? ' is-invalid' : '' ?>" id="username" name="username" value="<?= e($old['username'] ?? '') ?>" autocomplete="username" required maxlength="50" aria-describedby="username-help username-error">
                <div class="form-text" id="username-help">Used to sign in. Letters, numbers, dots, dashes and underscores only.</div>
                <?php if (isset($errors['username'])): ?>
                    <div class="invalid-feedback" id="username-error"><?= e($errors['username'][0]) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <label for="email" class="form-label">Institutional email <span aria-hidden="true">*</span></label>
                <input type="email" class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" value="<?= e($old['email'] ?? '') ?>" autocomplete="email" required maxlength="190" aria-describedby="email-error">
                <?php if (isset($errors['email'])): ?>
                    <div class="invalid-feedback" id="email-error"><?= e($errors['email'][0]) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <label for="password" class="form-label">Password <span aria-hidden="true">*</span></label>
                <input type="password" class="form-control<?= isset($errors['password']) ? ' is-invalid' : '' ?>" id="password" name="password" autocomplete="new-password" required aria-describedby="password-help password-error">
                <div class="form-text" id="password-help">At least 12 characters with at least one letter and one number. Stored as a strong one-way hash.</div>
                <?php if (isset($errors['password'])): ?>
                    <div class="invalid-feedback" id="password-error"><?= e($errors['password'][0]) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-md-6">
                <label for="password_confirmation" class="form-label">Confirm password <span aria-hidden="true">*</span></label>
                <input type="password" class="form-control<?= isset($errors['password_confirmation']) ? ' is-invalid' : '' ?>" id="password_confirmation" name="password_confirmation" autocomplete="new-password" required aria-describedby="password_confirmation-error">
                <?php if (isset($errors['password_confirmation'])): ?>
                    <div class="invalid-feedback" id="password_confirmation-error"><?= e($errors['password_confirmation'][0]) ?></div>
                <?php endif; ?>
            </div>

            <h2 class="h5 mt-3 col-12 border-bottom pb-1">Student details</h2>

            <div class="col-md-4">
                <label for="reg_number" class="form-label">Registration number <span aria-hidden="true">*</span></label>
                <input type="text" class="form-control<?= isset($errors['reg_number']) ? ' is-invalid' : '' ?>" id="reg_number" name="reg_number" value="<?= e($old['reg_number'] ?? '') ?>" required maxlength="30" aria-describedby="reg_number-error">
                <?php if (isset($errors['reg_number'])): ?>
                    <div class="invalid-feedback" id="reg_number-error"><?= e($errors['reg_number'][0]) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <label for="first_name" class="form-label">First name <span aria-hidden="true">*</span></label>
                <input type="text" class="form-control<?= isset($errors['first_name']) ? ' is-invalid' : '' ?>" id="first_name" name="first_name" value="<?= e($old['first_name'] ?? '') ?>" autocomplete="given-name" required maxlength="80" aria-describedby="first_name-error">
                <?php if (isset($errors['first_name'])): ?>
                    <div class="invalid-feedback" id="first_name-error"><?= e($errors['first_name'][0]) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <label for="last_name" class="form-label">Last name <span aria-hidden="true">*</span></label>
                <input type="text" class="form-control<?= isset($errors['last_name']) ? ' is-invalid' : '' ?>" id="last_name" name="last_name" value="<?= e($old['last_name'] ?? '') ?>" autocomplete="family-name" required maxlength="80" aria-describedby="last_name-error">
                <?php if (isset($errors['last_name'])): ?>
                    <div class="invalid-feedback" id="last_name-error"><?= e($errors['last_name'][0]) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <label for="other_names" class="form-label">Other names</label>
                <input type="text" class="form-control" id="other_names" name="other_names" value="<?= e($old['other_names'] ?? '') ?>" autocomplete="additional-name" maxlength="120">
            </div>

            <div class="col-md-4">
                <label for="gender" class="form-label">Gender</label>
                <select class="form-select" id="gender" name="gender">
                    <option value="">Prefer not to say</option>
                    <option value="male"<?= ($old['gender'] ?? '') === 'male' ? ' selected' : '' ?>>Male</option>
                    <option value="female"<?= ($old['gender'] ?? '') === 'female' ? ' selected' : '' ?>>Female</option>
                    <option value="other"<?= ($old['gender'] ?? '') === 'other' ? ' selected' : '' ?>>Other</option>
                </select>
            </div>

            <div class="col-md-4">
                <label for="date_of_birth" class="form-label">Date of birth</label>
                <input type="date" class="form-control<?= isset($errors['date_of_birth']) ? ' is-invalid' : '' ?>" id="date_of_birth" name="date_of_birth" value="<?= e($old['date_of_birth'] ?? '') ?>" aria-describedby="date_of_birth-error">
                <?php if (isset($errors['date_of_birth'])): ?>
                    <div class="invalid-feedback" id="date_of_birth-error"><?= e($errors['date_of_birth'][0]) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <label for="department" class="form-label">Department</label>
                <input type="text" class="form-control" id="department" name="department" value="<?= e($old['department'] ?? '') ?>" maxlength="120">
            </div>

            <div class="col-md-4">
                <label for="faculty" class="form-label">Faculty</label>
                <input type="text" class="form-control" id="faculty" name="faculty" value="<?= e($old['faculty'] ?? '') ?>" maxlength="120">
            </div>

            <div class="col-md-4">
                <label for="level_of_study" class="form-label">Level of study</label>
                <input type="text" class="form-control" id="level_of_study" name="level_of_study" value="<?= e($old['level_of_study'] ?? '') ?>" maxlength="30" placeholder="e.g. 300">
            </div>

            <div class="col-md-4">
                <label for="phone" class="form-label">Phone number</label>
                <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($old['phone'] ?? '') ?>" autocomplete="tel" maxlength="30">
            </div>

            <div class="col-12">
                <label for="address" class="form-label">Residential address</label>
                <input type="text" class="form-control" id="address" name="address" value="<?= e($old['address'] ?? '') ?>" autocomplete="street-address" maxlength="255">
            </div>

            <h2 class="h5 mt-3 col-12 border-bottom pb-1">Emergency contact</h2>

            <div class="col-md-6">
                <label for="emergency_contact_name" class="form-label">Contact name</label>
                <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?= e($old['emergency_contact_name'] ?? '') ?>" maxlength="120">
            </div>

            <div class="col-md-6">
                <label for="emergency_contact_phone" class="form-label">Contact phone</label>
                <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= e($old['emergency_contact_phone'] ?? '') ?>" maxlength="30">
            </div>

            <div class="col-12 d-flex flex-wrap gap-2 pt-2">
                <button type="submit" class="btn btn-primary btn-lg px-4">Create account</button>
                <a href="<?= e(base_url('/auth/login')) ?>" class="btn btn-link align-self-center">Already have an account? Sign in</a>
            </div>
        </form>
    </div>
</section>