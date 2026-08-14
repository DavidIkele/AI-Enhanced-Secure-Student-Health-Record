<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var array $student */ ?>
<?php $errors = $errors ?? \App\Core\Session::get('danger_fields') ?? []; ?>
<?php $old = $old ?? \App\Core\Session::get('old_visit') ?? []; ?>
<?php \App\Core\Session::remove('danger_fields'); ?>
<?php \App\Core\Session::remove('old_visit'); ?>
<section aria-labelledby="visit-heading">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= e(base_url('/records')) ?>">Student Health Records</a></li>
            <li class="breadcrumb-item"><a href="<?= e(base_url('/records/' . (int) $student['id'])) ?>"><?= e($student['last_name'] . ', ' . $student['first_name']) ?></a></li>
            <li class="breadcrumb-item active" aria-current="page">New clinic visit</li>
        </ol>
    </nav>

    <h1 id="visit-heading" class="h3">Record clinic visit</h1>
    <p class="lead">
        <?= e($student['last_name'] . ', ' . $student['first_name']) ?> &middot; <?= e($student['reg_number']) ?>
    </p>

    <?php if (isset($errors['form'])): ?>
        <div class="alert alert-danger" role="alert"><?= e($errors['form'][0]) ?></div>
    <?php endif; ?>

    <form method="post" action="<?= e(base_url('/records/' . (int) $student['id'] . '/visits')) ?>" class="row g-3" novalidate>
        <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">

        <fieldset class="border rounded p-3 col-12">
            <legend class="h6 px-2 w-auto">Visit details</legend>

            <div class="row g-3">
                <div class="col-md-4">
                    <label for="visited_at" class="form-label">Date and time <span aria-hidden="true">*</span></label>
                    <input type="datetime-local" class="form-control<?= isset($errors['visited_at']) ? ' is-invalid' : '' ?>" id="visited_at" name="visited_at" required value="<?= e($old['visited_at'] ?? '') ?>" aria-describedby="visited_at-error">
                    <?php if (isset($errors['visited_at'])): ?>
                        <div class="invalid-feedback" id="visited_at-error"><?= e($errors['visited_at'][0]) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-4">
                    <label for="visit_type" class="form-label">Visit type</label>
                    <select class="form-select" id="visit_type" name="visit_type">
                        <?php foreach (['initial', 'follow_up', 'emergency', 'routine', 'referral'] as $vt): ?>
                            <option value="<?= e($vt) ?>"<?= ($old['visit_type'] ?? '') === $vt ? ' selected' : '' ?>><?= e(str_replace('_', ' ', $vt)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="visit_status" class="form-label">Status</label>
                    <select class="form-select" id="visit_status" name="status">
                        <option value="open"<?= ($old['status'] ?? '') === 'open' ? ' selected' : '' ?>>Open</option>
                        <option value="closed"<?= ($old['status'] ?? '') === 'closed' ? ' selected' : '' ?>>Closed</option>
                    </select>
                </div>

                <div class="col-12">
                    <label for="reason" class="form-label">Reason for visit <span aria-hidden="true">*</span></label>
                    <input type="text" class="form-control<?= isset($errors['reason']) ? ' is-invalid' : '' ?>" id="reason" name="reason" required maxlength="255" value="<?= e($old['reason'] ?? '') ?>" aria-describedby="reason-error">
                    <?php if (isset($errors['reason'])): ?>
                        <div class="invalid-feedback" id="reason-error"><?= e($errors['reason'][0]) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-md-6">
                    <label for="chief_complaint" class="form-label">Chief complaint</label>
                    <textarea class="form-control" id="chief_complaint" name="chief_complaint" rows="3" maxlength="4000"><?= e($old['chief_complaint'] ?? '') ?></textarea>
                </div>

                <div class="col-md-6">
                    <label for="assessment_notes" class="form-label">Assessment notes</label>
                    <textarea class="form-control" id="assessment_notes" name="assessment_notes" rows="3" maxlength="8000"><?= e($old['assessment_notes'] ?? '') ?></textarea>
                </div>

                <div class="col-md-6">
                    <label for="outcome" class="form-label">Outcome</label>
                    <select class="form-select" id="outcome" name="outcome">
                        <option value="">Not specified</option>
                        <?php foreach (['treated', 'referred', 'admitted', 'observation', 'discharged'] as $oc): ?>
                            <option value="<?= e($oc) ?>"<?= ($old['outcome'] ?? '') === $oc ? ' selected' : '' ?>><?= e($oc) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </fieldset>

        <fieldset class="border rounded p-3 col-12">
            <legend class="h6 px-2 w-auto">Diagnosis (optional)</legend>
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="diagnosis_icd" class="form-label">ICD code</label>
                    <input type="text" class="form-control" id="diagnosis_icd" name="diagnosis_icd" maxlength="20" value="<?= e($old['diagnosis_icd'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label for="diagnosis_name" class="form-label">Diagnosis name</label>
                    <input type="text" class="form-control<?= isset($errors['diagnosis_name']) ? ' is-invalid' : '' ?>" id="diagnosis_name" name="diagnosis_name" maxlength="150" value="<?= e($old['diagnosis_name'] ?? '') ?>" aria-describedby="diagnosis_name-error">
                    <?php if (isset($errors['diagnosis_name'])): ?>
                        <div class="invalid-feedback" id="diagnosis_name-error"><?= e($errors['diagnosis_name'][0]) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label for="diagnosis_severity" class="form-label">Severity</label>
                    <select class="form-select" id="diagnosis_severity" name="diagnosis_severity">
                        <?php foreach (['mild', 'moderate', 'severe', 'critical'] as $sv): ?>
                            <option value="<?= e($sv) ?>"<?= ($old['diagnosis_severity'] ?? '') === $sv ? ' selected' : '' ?>><?= e($sv) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </fieldset>

        <fieldset class="border rounded p-3 col-12">
            <legend class="h6 px-2 w-auto">Treatment and medication (optional)</legend>
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="treatment_name" class="form-label">Treatment name</label>
                    <input type="text" class="form-control" id="treatment_name" name="treatment_name" maxlength="150" value="<?= e($old['treatment_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="treatment_type" class="form-label">Treatment type</label>
                    <select class="form-select" id="treatment_type" name="treatment_type">
                        <?php foreach (['medication', 'procedure', 'therapy', 'counseling', 'referral', 'other'] as $tt): ?>
                            <option value="<?= e($tt) ?>"<?= ($old['treatment_type'] ?? '') === $tt ? ' selected' : '' ?>><?= e($tt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="treatment_status" class="form-label">Treatment status</label>
                    <select class="form-select" id="treatment_status" name="treatment_status">
                        <?php foreach (['planned', 'in_progress', 'completed', 'discontinued'] as $ts): ?>
                            <option value="<?= e($ts) ?>"<?= ($old['treatment_status'] ?? '') === $ts ? ' selected' : '' ?>><?= e(str_replace('_', ' ', $ts)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="medication_name" class="form-label">Medication name</label>
                    <input type="text" class="form-control" id="medication_name" name="medication_name" maxlength="150" value="<?= e($old['medication_name'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="medication_dosage" class="form-label">Dosage</label>
                    <input type="text" class="form-control" id="medication_dosage" name="medication_dosage" maxlength="100" value="<?= e($old['medication_dosage'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="medication_frequency" class="form-label">Frequency</label>
                    <input type="text" class="form-control" id="medication_frequency" name="medication_frequency" maxlength="100" value="<?= e($old['medication_frequency'] ?? '') ?>">
                </div>
            </div>
        </fieldset>

        <fieldset class="border rounded p-3 col-12">
            <legend class="h6 px-2 w-auto">Vital signs (optional)</legend>
            <div class="row g-3">
                <div class="col-md-3">
                    <label for="temperature_c" class="form-label">Temperature (&deg;C)</label>
                    <input type="number" step="0.1" min="30" max="45" class="form-control<?= isset($errors['temperature_c']) ? ' is-invalid' : '' ?>" id="temperature_c" name="temperature_c" value="<?= e($old['temperature_c'] ?? '') ?>" aria-describedby="temperature_c-error">
                    <?php if (isset($errors['temperature_c'])): ?>
                        <div class="invalid-feedback" id="temperature_c-error"><?= e($errors['temperature_c'][0]) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label for="blood_pressure_sys" class="form-label">BP systolic (mmHg)</label>
                    <input type="number" min="50" max="260" class="form-control<?= isset($errors['blood_pressure_sys']) ? ' is-invalid' : '' ?>" id="blood_pressure_sys" name="blood_pressure_sys" value="<?= e($old['blood_pressure_sys'] ?? '') ?>" aria-describedby="blood_pressure_sys-error">
                    <?php if (isset($errors['blood_pressure_sys'])): ?>
                        <div class="invalid-feedback" id="blood_pressure_sys-error"><?= e($errors['blood_pressure_sys'][0]) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label for="blood_pressure_dia" class="form-label">BP diastolic (mmHg)</label>
                    <input type="number" min="30" max="160" class="form-control<?= isset($errors['blood_pressure_dia']) ? ' is-invalid' : '' ?>" id="blood_pressure_dia" name="blood_pressure_dia" value="<?= e($old['blood_pressure_dia'] ?? '') ?>" aria-describedby="blood_pressure_dia-error">
                    <?php if (isset($errors['blood_pressure_dia'])): ?>
                        <div class="invalid-feedback" id="blood_pressure_dia-error"><?= e($errors['blood_pressure_dia'][0]) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label for="heart_rate" class="form-label">Heart rate (bpm)</label>
                    <input type="number" min="20" max="260" class="form-control<?= isset($errors['heart_rate']) ? ' is-invalid' : '' ?>" id="heart_rate" name="heart_rate" value="<?= e($old['heart_rate'] ?? '') ?>" aria-describedby="heart_rate-error">
                    <?php if (isset($errors['heart_rate'])): ?>
                        <div class="invalid-feedback" id="heart_rate-error"><?= e($errors['heart_rate'][0]) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label for="respiratory_rate" class="form-label">Respiratory rate (/min)</label>
                    <input type="number" min="4" max="80" class="form-control<?= isset($errors['respiratory_rate']) ? ' is-invalid' : '' ?>" id="respiratory_rate" name="respiratory_rate" value="<?= e($old['respiratory_rate'] ?? '') ?>" aria-describedby="respiratory_rate-error">
                    <?php if (isset($errors['respiratory_rate'])): ?>
                        <div class="invalid-feedback" id="respiratory_rate-error"><?= e($errors['respiratory_rate'][0]) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <label for="oxygen_saturation" class="form-label">Oxygen saturation (%)</label>
                    <input type="number" min="40" max="100" class="form-control<?= isset($errors['oxygen_saturation']) ? ' is-invalid' : '' ?>" id="oxygen_saturation" name="oxygen_saturation" value="<?= e($old['oxygen_saturation'] ?? '') ?>" aria-describedby="oxygen_saturation-error">
                    <?php if (isset($errors['oxygen_saturation'])): ?>
                        <div class="invalid-feedback" id="oxygen_saturation-error"><?= e($errors['oxygen_saturation'][0]) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </fieldset>

        <div class="col-12">
            <button type="submit" class="btn btn-primary">Save clinic visit</button>
            <a href="<?= e(base_url('/records/' . (int) $student['id'])) ?>" class="btn btn-outline-secondary">Cancel</a>
        </div>
    </form>
</section>
