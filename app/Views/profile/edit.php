<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var array $student */ ?>
<?php /** @var string $email */ ?>
<?php /** @var array<string, array<int, string>> $errors */ ?>
<?php /** @var array<string, mixed> $old */ ?>
<?php $csrf = \App\Security\Security::csrfToken(); ?>
<section aria-labelledby="edit-profile-heading">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= e(base_url('/profile')) ?>">My profile</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit profile</li>
        </ol>
    </nav>

    <h1 id="edit-profile-heading" class="h3 mb-1">Edit your profile</h1>
    <p class="lead">Keep your details up to date so the health centre can reach you when it matters.</p>

    <p class="small text-muted"><span aria-hidden="true">*</span> Required field</p>

    <form method="post" action="<?= e(base_url('/profile')) ?>" class="row g-3 mb-4" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <div class="col-md-6">
            <label for="reg_number" class="form-label">Registration number</label>
            <input type="text" class="form-control" id="reg_number" value="<?= e($student['reg_number'] ?? '') ?>" readonly disabled>
            <div class="form-text">Your registration number cannot be changed.</div>
        </div>

        <div class="col-md-6">
            <label for="username" class="form-label">Username <span aria-hidden="true">*</span></label>
            <input type="text" class="form-control<?= isset($errors['username']) ? ' is-invalid' : '' ?>" id="username" name="username" value="<?= e($username ?? $old['username'] ?? '') ?>" autocomplete="username" required maxlength="50" pattern="[a-zA-Z0-9_.-]{3,50}" aria-describedby="username-error">
            <?php if (isset($errors['username'])): ?>
                <div class="invalid-feedback" id="username-error"><?= e($errors['username'][0]) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label for="email" class="form-label">Institutional email <span aria-hidden="true">*</span></label>
            <input type="email" class="form-control<?= isset($errors['email']) ? ' is-invalid' : '' ?>" id="email" name="email" value="<?= e($email ?? $old['email'] ?? '') ?>" autocomplete="email" required maxlength="190" aria-describedby="email-error">
            <?php if (isset($errors['email'])): ?>
                <div class="invalid-feedback" id="email-error"><?= e($errors['email'][0]) ?></div>
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

        <div class="col-md-6">
            <label for="emergency_contact_name" class="form-label">Emergency contact name</label>
            <input type="text" class="form-control" id="emergency_contact_name" name="emergency_contact_name" value="<?= e($old['emergency_contact_name'] ?? '') ?>" maxlength="120">
        </div>

        <div class="col-md-6">
            <label for="emergency_contact_phone" class="form-label">Emergency contact phone</label>
            <input type="tel" class="form-control" id="emergency_contact_phone" name="emergency_contact_phone" value="<?= e($old['emergency_contact_phone'] ?? '') ?>" maxlength="30">
        </div>

        <div class="col-12 d-flex flex-wrap gap-2 pt-2">
            <button type="submit" class="btn btn-primary px-4">Save changes</button>
            <a href="<?= e(base_url('/profile')) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>

    <h2 class="h5 mt-5 border-bottom pb-1">Change password</h2>
    <form method="post" action="<?= e(base_url('/profile/password')) ?>" class="row g-3 mt-0 mb-4" novalidate>
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="col-md-4">
            <label for="current_password" class="form-label">Current password <span aria-hidden="true">*</span></label>
            <input type="password" class="form-control<?= isset($errors['current_password']) ? ' is-invalid' : '' ?>" id="current_password" name="current_password" autocomplete="current-password" required aria-describedby="current_password-help current_password-error">
            <div class="form-text" id="current_password-help">We need this to confirm it's really you.</div>
            <?php if (isset($errors['current_password'])): ?>
                <div class="invalid-feedback" id="current_password-error"><?= e($errors['current_password'][0]) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-4">
            <label for="new_password" class="form-label">New password <span aria-hidden="true">*</span></label>
            <input type="password" class="form-control<?= isset($errors['new_password']) ? ' is-invalid' : '' ?>" id="new_password" name="new_password" autocomplete="new-password" required aria-describedby="new_password-help new_password-error">
            <div class="form-text" id="new_password-help">At least 12 characters with at least one letter and one number.</div>
            <?php if (isset($errors['new_password'])): ?>
                <div class="invalid-feedback" id="new_password-error"><?= e($errors['new_password'][0]) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-md-4">
            <label for="new_password_confirmation" class="form-label">Confirm new password <span aria-hidden="true">*</span></label>
            <input type="password" class="form-control<?= isset($errors['new_password_confirmation']) ? ' is-invalid' : '' ?>" id="new_password_confirmation" name="new_password_confirmation" autocomplete="new-password" required aria-describedby="new_password_confirmation-error">
            <?php if (isset($errors['new_password_confirmation'])): ?>
                <div class="invalid-feedback" id="new_password_confirmation-error"><?= e($errors['new_password_confirmation'][0]) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-12">
            <button type="submit" class="btn btn-outline-primary">Update password</button>
        </div>
    </form>
</section>