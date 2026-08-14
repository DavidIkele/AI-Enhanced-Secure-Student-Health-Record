<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var array $student */ ?>
<?php /** @var array|null $record */ ?>
<?php /** @var array $errors */ ?>
<?php /** @var array $old */ ?>
<?php $errors = $errors ?? []; ?>
<?php $old = $old ?? ($record ?? []); ?>
<section aria-labelledby="edit-heading">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= e(base_url('/records')) ?>">Student Health Records</a></li>
            <li class="breadcrumb-item"><a href="<?= e(base_url('/records/' . (int) $student['id'])) ?>"><?= e($student['last_name'] . ', ' . $student['first_name']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit health profile</li>
        </ol>
    </nav>

    <h1 id="edit-heading" class="h3">Edit health profile</h1>
    <p class="lead">
        <?= e($student['last_name'] . ', ' . $student['first_name']) ?> &middot; <?= e($student['reg_number']) ?>
    </p>

    <?php if (isset($errors['form'])): ?>
        <div class="alert alert-danger" role="alert"><?= e($errors['form'][0]) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('/records/' . (int) $student['id'] . '/profile')) ?>" class="row g-3" novalidate>
        <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">

        <div class="col-md-6">
            <label for="blood_group" class="form-label">Blood group</label>
            <select class="form-select<?= isset($errors['blood_group']) ? ' is-invalid' : '' ?>" id="blood_group" name="blood_group" aria-describedby="blood_group-error">
                <?php foreach (['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-', 'Unknown'] as $bg): ?>
                    <option value="<?= e($bg) ?>"<?= ($old['blood_group'] ?? 'Unknown') === $bg ? ' selected' : '' ?>><?= e($bg) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['blood_group'])): ?>
                <div class="invalid-feedback" id="blood_group-error"><?= e($errors['blood_group'][0]) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label for="genotype" class="form-label">Genotype</label>
            <select class="form-select<?= isset($errors['genotype']) ? ' is-invalid' : '' ?>" id="genotype" name="genotype" aria-describedby="genotype-error">
                <?php foreach (['AA', 'AS', 'SS', 'AC', 'SC', 'Unknown'] as $gt): ?>
                    <option value="<?= e($gt) ?>"<?= ($old['genotype'] ?? 'Unknown') === $gt ? ' selected' : '' ?>><?= e($gt) ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['genotype'])): ?>
                <div class="invalid-feedback" id="genotype-error"><?= e($errors['genotype'][0]) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label for="height_cm" class="form-label">Height (cm)</label>
            <input type="number" step="0.01" min="50" max="250" class="form-control<?= isset($errors['height_cm']) ? ' is-invalid' : '' ?>" id="height_cm" name="height_cm" value="<?= e((string) ($old['height_cm'] ?? '')) ?>" aria-describedby="height_cm-error">
            <?php if (isset($errors['height_cm'])): ?>
                <div class="invalid-feedback" id="height_cm-error"><?= e($errors['height_cm'][0]) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <label for="weight_kg" class="form-label">Weight (kg)</label>
            <input type="number" step="0.01" min="10" max="300" class="form-control<?= isset($errors['weight_kg']) ? ' is-invalid' : '' ?>" id="weight_kg" name="weight_kg" value="<?= e((string) ($old['weight_kg'] ?? '')) ?>" aria-describedby="weight_kg-error">
            <?php if (isset($errors['weight_kg'])): ?>
                <div class="invalid-feedback" id="weight_kg-error"><?= e($errors['weight_kg'][0]) ?></div>
            <?php endif; ?>
        </div>

        <div class="col-12">
            <label for="allergies" class="form-label">Allergies</label>
            <textarea class="form-control" id="allergies" name="allergies" rows="2" maxlength="2000" aria-describedby="allergies-help"><?= e($old['allergies'] ?? '') ?></textarea>
            <div class="form-text" id="allergies-help">Comma-separated list, e.g. Penicillin, Aspirin.</div>
        </div>

        <div class="col-md-6">
            <label for="chronic_conditions" class="form-label">Chronic conditions</label>
            <textarea class="form-control" id="chronic_conditions" name="chronic_conditions" rows="2" maxlength="2000"><?= e($old['chronic_conditions'] ?? '') ?></textarea>
        </div>

        <div class="col-md-6">
            <label for="disabilities" class="form-label">Disabilities</label>
            <textarea class="form-control" id="disabilities" name="disabilities" rows="2" maxlength="2000"><?= e($old['disabilities'] ?? '') ?></textarea>
        </div>

        <div class="col-12">
            <label for="family_history" class="form-label">Family history</label>
            <textarea class="form-control" id="family_history" name="family_history" rows="2" maxlength="2000"><?= e($old['family_history'] ?? '') ?></textarea>
        </div>

        <div class="col-12">
            <label for="notes" class="form-label">Clinical notes</label>
            <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="4000"><?= e($old['notes'] ?? '') ?></textarea>
        </div>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">Save health profile</button>
            <a href="<?= e(base_url('/records/' . (int) $student['id'])) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</section>
