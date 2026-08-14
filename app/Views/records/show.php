<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var array $student */ ?>
<?php /** @var array|null $record */ ?>
<?php /** @var array<int, array<string, mixed>> $histories */ ?>
<?php /** @var array<int, array<string, mixed>> $visits */ ?>
<?php /** @var bool $canManage */ ?>
<?php /** @var bool $canSendAlerts */ ?>
<?php /** @var array<int, array<string, mixed>> $symptomAssessments */ ?>
<?php $errors = \App\Core\Session::get('danger_fields') ?? []; ?>
<?php $oldHistory = \App\Core\Session::get('old_history') ?? []; ?>
<?php \App\Core\Session::remove('danger_fields'); ?>
<?php \App\Core\Session::remove('old_history'); ?>
<section aria-labelledby="record-heading">
    <nav aria-label="Breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="<?= e(base_url('/records')) ?>">Student Health Records</a></li>
            <li class="breadcrumb-item active" aria-current="page"><?= e($student['last_name'] . ', ' . $student['first_name']) ?></li>
        </ol>
    </nav>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <h1 id="record-heading" class="h3 mb-0"><?= e($student['last_name'] . ', ' . $student['first_name']) ?></h1>
        <?php if ($canManage): ?>
            <div class="btn-group" role="group" aria-label="Record actions">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/records/' . (int) $student['id'] . '/edit')) ?>">Edit profile</a>
                <a class="btn btn-sm btn-outline-primary" href="<?= e(base_url('/records/' . (int) $student['id'] . '/visits/new')) ?>">Record visit</a>
            </div>
        <?php endif; ?>
    </div>
    <p class="lead">
        <?= e($student['reg_number']) ?> &middot; <?= e($student['department']) ?> (<?= e($student['level_of_study']) ?>)
    </p>

    <?php if ($record === null): ?>
        <div class="alert alert-warning" role="alert">
            No health profile has been created for this student yet.
            <?php if ($canManage): ?>
                <a href="<?= e(base_url('/records/' . (int) $student['id'] . '/edit')) ?>">Create the health profile</a>.
            <?php endif; ?>
        </div>
    <?php else: ?>
        <h2 class="h5 mt-4">Health profile</h2>
        <dl class="row">
            <dt class="col-3">Blood group</dt>
            <dd class="col-9"><?= e($record['blood_group']) ?></dd>

            <dt class="col-3">Genotype</dt>
            <dd class="col-9"><?= e($record['genotype']) ?></dd>

            <dt class="col-3">Height / weight</dt>
            <dd class="col-9"><?= e((string) $record['height_cm']) ?> cm / <?= e((string) $record['weight_kg']) ?> kg</dd>

            <dt class="col-3">Allergies</dt>
            <dd class="col-9"><?= $record['allergies'] ? e($record['allergies']) : 'None recorded' ?></dd>

            <dt class="col-3">Chronic conditions</dt>
            <dd class="col-9"><?= $record['chronic_conditions'] ? e($record['chronic_conditions']) : 'None recorded' ?></dd>

            <dt class="col-3">Disabilities</dt>
            <dd class="col-9"><?= $record['disabilities'] ? e($record['disabilities']) : 'None recorded' ?></dd>

            <dt class="col-3">Family history</dt>
            <dd class="col-9"><?= $record['family_history'] ? e($record['family_history']) : 'None recorded' ?></dd>

            <dt class="col-3">Notes</dt>
            <dd class="col-9"><?= $record['notes'] ? e($record['notes']) : 'None' ?></dd>

            <dt class="col-3">Last updated</dt>
            <dd class="col-9"><?= e($record['updated_at']) ?></dd>
        </dl>
    <?php endif; ?>

    <h2 class="h5 mt-4">Medical history</h2>
    <?php if ($histories === []): ?>
        <p class="text-muted">No medical history entries recorded.</p>
    <?php else: ?>
        <div class="table-responsive" tabindex="0">
            <table class="table table-hover align-middle">
                <caption class="visually-hidden">Medical history for <?= e($student['last_name'] . ', ' . $student['first_name']) ?></caption>
                <thead>
                    <tr>
                        <th scope="col">Condition</th>
                        <th scope="col">Onset</th>
                        <th scope="col">Severity</th>
                        <th scope="col">Status</th>
                        <th scope="col">Recurring</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($histories as $h): ?>
                        <tr>
                            <th scope="row"><?= e($h['condition_name']) ?></th>
                            <td><?= $h['onset_date'] ? e($h['onset_date']) : 'Not recorded' ?></td>
                            <td><?= e($h['severity']) ?></td>
                            <td><?= e($h['status']) ?></td>
                            <td><?= $h['is_recurring'] ? 'Yes' : 'No' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <details class="mt-3">
            <summary>Add a medical history entry</summary>
            <form method="post" action="<?= e(base_url('/records/' . (int) $student['id'] . '/medical-history')) ?>" class="mt-3 row g-3" novalidate>
                <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
                <div class="col-md-6">
                    <label for="condition_name" class="form-label">Condition name</label>
                    <input type="text" class="form-control<?= isset($errors['condition_name']) ? ' is-invalid' : '' ?>" id="condition_name" name="condition_name" required maxlength="150" value="<?= e($oldHistory['condition_name'] ?? '') ?>" aria-describedby="condition_name-error">
                    <?php if (isset($errors['condition_name'])): ?>
                        <div class="invalid-feedback" id="condition_name-error"><?= e($errors['condition_name'][0]) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label for="onset_date" class="form-label">Onset date</label>
                    <input type="date" class="form-control<?= isset($errors['onset_date']) ? ' is-invalid' : '' ?>" id="onset_date" name="onset_date" value="<?= e($oldHistory['onset_date'] ?? '') ?>" aria-describedby="onset_date-error">
                    <?php if (isset($errors['onset_date'])): ?>
                        <div class="invalid-feedback" id="onset_date-error"><?= e($errors['onset_date'][0]) ?></div>
                    <?php endif; ?>
                </div>
                <div class="col-md-3">
                    <label for="severity" class="form-label">Severity</label>
                    <select class="form-select" id="severity" name="severity">
                        <option value="mild"<?= ($oldHistory['severity'] ?? '') === 'mild' ? ' selected' : '' ?>>Mild</option>
                        <option value="moderate"<?= ($oldHistory['severity'] ?? '') === 'moderate' ? ' selected' : '' ?>>Moderate</option>
                        <option value="severe"<?= ($oldHistory['severity'] ?? '') === 'severe' ? ' selected' : '' ?>>Severe</option>
                        <option value="critical"<?= ($oldHistory['severity'] ?? '') === 'critical' ? ' selected' : '' ?>>Critical</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="2" maxlength="2000"><?= e($oldHistory['description'] ?? '') ?></textarea>
                </div>
                <div class="col-md-6">
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="is_recurring" name="is_recurring" value="1"<?= !empty($oldHistory['is_recurring']) ? ' checked' : '' ?>>
                        <label class="form-check-label" for="is_recurring">Recurring condition</label>
                    </div>
                    <div class="mt-2">
                        <label for="history_status" class="form-label">Status</label>
                        <select class="form-select" id="history_status" name="status">
                            <option value="active"<?= ($oldHistory['status'] ?? '') === 'active' ? ' selected' : '' ?>>Active</option>
                            <option value="resolved"<?= ($oldHistory['status'] ?? '') === 'resolved' ? ' selected' : '' ?>>Resolved</option>
                        </select>
                    </div>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">Add medical history</button>
                </div>
            </form>
        </details>
    <?php endif; ?>

    <h2 class="h5 mt-4">Clinic visit history</h2>
    <?php if ($visits === []): ?>
        <p class="text-muted">No clinic visits recorded.</p>
    <?php else: ?>
        <?php foreach ($visits as $visit): ?>
            <article class="border rounded p-3 my-3" aria-label="Visit on <?= e($visit['visited_at']) ?>">
                <h3 class="h6 d-flex flex-wrap justify-content-between gap-2">
                    <span><?= e($visit['visited_at']) ?> &middot; <?= e($visit['visit_type']) ?></span>
                    <span class="badge text-bg-<?= $visit['status'] === 'closed' ? 'secondary' : 'success' ?>"><span class="visually-hidden">Status: </span><?= e($visit['status']) ?></span>
                </h3>
                <dl class="row mb-1">
                    <dt class="col-3">Reason</dt>
                    <dd class="col-9"><?= e($visit['reason']) ?></dd>
                    <?php if ($visit['chief_complaint']): ?>
                        <dt class="col-3">Chief complaint</dt>
                        <dd class="col-9"><?= e($visit['chief_complaint']) ?></dd>
                    <?php endif; ?>
                    <?php if ($visit['assessment_notes']): ?>
                        <dt class="col-3">Assessment notes</dt>
                        <dd class="col-9"><?= e($visit['assessment_notes']) ?></dd>
                    <?php endif; ?>
                    <?php if ($visit['outcome']): ?>
                        <dt class="col-3">Outcome</dt>
                        <dd class="col-9"><?= e($visit['outcome']) ?></dd>
                    <?php endif; ?>
                </dl>

                <?php if (!empty($visit['diagnoses'])): ?>
                    <h4 class="visually-hidden">Diagnoses</h4>
                    <ul class="mb-2">
                        <?php foreach ($visit['diagnoses'] as $d): ?>
                            <li><?= $d['icd_code'] ? e($d['icd_code']) . ' &mdash; ' : '' ?><?= e($d['name']) ?> (<?= e($d['severity']) ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>

                <?php if (!empty($visit['treatments']) || !empty($visit['medications'])): ?>
                    <h4 class="h6">Treatment / medication</h4>
                    <?php if (!empty($visit['treatments'])): ?>
                        <ul class="mb-2">
                            <?php foreach ($visit['treatments'] as $t): ?>
                                <li><?= e($t['name']) ?> (<?= e($t['treatment_type']) ?>, <?= e($t['status']) ?>)</li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if (!empty($visit['medications'])): ?>
                        <ul class="mb-2">
                            <?php foreach ($visit['medications'] as $m): ?>
                                <li><?= e($m['name']) ?> &mdash; <?= e($m['dosage']) ?><?= $m['frequency'] ? ' (frequency: ' . e($m['frequency']) . ')' : '' ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                <?php endif; ?>

                <?php if (!empty($visit['vital_signs'])): ?>
                    <h4 class="h6">Vital signs</h4>
                    <div class="table-responsive" tabindex="0">
                        <table class="table table-sm align-middle">
                            <caption class="visually-hidden">Vital signs recorded for the visit on <?= e($visit['visited_at']) ?></caption>
                            <thead>
                                <tr>
                                    <th scope="col">Temperature</th>
                                    <th scope="col">Blood pressure</th>
                                    <th scope="col">Heart rate</th>
                                    <th scope="col">Respiratory rate</th>
                                    <th scope="col">Oxygen saturation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($visit['vital_signs'] as $vs): ?>
                                    <tr>
                                        <td><?= $vs['temperature_c'] !== null ? e((string) $vs['temperature_c']) . ' &deg;C' : '&mdash;' ?></td>
                                        <td><?= $vs['blood_pressure_sys'] !== null ? e((string) $vs['blood_pressure_sys']) . '/' . e((string) $vs['blood_pressure_dia']) . ' mmHg' : '&mdash;' ?></td>
                                        <td><?= $vs['heart_rate'] !== null ? e((string) $vs['heart_rate']) . ' bpm' : '&mdash;' ?></td>
                                        <td><?= $vs['respiratory_rate'] !== null ? e((string) $vs['respiratory_rate']) . ' /min' : '&mdash;' ?></td>
                                        <td><?= $vs['oxygen_saturation'] !== null ? e((string) $vs['oxygen_saturation']) . ' %' : '&mdash;' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>

    <h2 class="h5 mt-4">Health insights</h2>
    <?php if ($canManage): ?>
        <form method="post" action="<?= e(base_url('/records/' . (int) $student['id'] . '/insights/generate')) ?>" class="mb-3">
            <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
            <button type="submit" class="btn btn-sm btn-outline-primary">Generate personalized insights</button>
            <span class="form-text ms-2">Informational, non-diagnostic notes generated from this student's records.</span>
        </form>
    <?php endif; ?>
    <?php if ($insights === []): ?>
        <p class="text-muted">No active insights. Generate them to surface informational notes for this student.</p>
    <?php else: ?>
        <div class="table-responsive" tabindex="0">
            <table class="table table-hover align-middle">
                <caption class="visually-hidden">Active personalized health insights for <?= e($student['last_name'] . ', ' . $student['first_name']) ?></caption>
                <thead>
                    <tr>
                        <th scope="col">Type</th>
                        <th scope="col">Title</th>
                        <th scope="col">Content</th>
                        <th scope="col">Status</th>
                        <th scope="col">Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($insights as $insight): ?>
                        <tr>
                            <td><code><?= e($insight['insight_type']) ?></code></td>
                            <th scope="row"><?= e($insight['title']) ?></th>
                            <td><?= e($insight['content']) ?></td>
                            <td><?= $insight['is_read'] ? 'Read' : 'Unread' ?></td>
                            <td><?= e($insight['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <h2 class="h5 mt-4">AI decision support</h2>
    <p class="text-muted small">
        Statistical risk assessments computed by the server-side AI service. These are
        decision-support indicators only, never a diagnosis, and use only de-identified
        aggregate features.
    </p>
    <?php if ($canManage): ?>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <?php foreach (\App\Controllers\AiController::PREDICTION_TYPES as $aiType): ?>
                <form method="post" action="<?= e(base_url('/records/' . (int) $student['id'] . '/predictions/' . rawurlencode($aiType))) ?>">
                    <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Assess <?= e($aiType) ?></button>
                </form>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if ($predictions === []): ?>
        <p class="text-muted">No assessments yet. Run one above to record a decision-support result.</p>
    <?php else: ?>
        <div class="table-responsive" tabindex="0">
            <table class="table table-hover align-middle">
                <caption class="visually-hidden">AI decision-support assessments for <?= e($student['last_name'] . ', ' . $student['first_name']) ?></caption>
                <thead>
                    <tr>
                        <th scope="col">Type</th>
                        <th scope="col">Risk</th>
                        <th scope="col">Score</th>
                        <th scope="col">Confidence</th>
                        <th scope="col">Model</th>
                        <th scope="col">Status</th>
                        <th scope="col">Created</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($predictions as $prediction): ?>
                        <tr>
                            <td><code><?= e($prediction['prediction_type']) ?></code></td>
                            <td>
                                <?php if ($prediction['risk_level'] !== null): ?>
                                    <?php
                                        $badge = match ($prediction['risk_level']) {
                                            'high' => 'danger',
                                            'moderate' => 'warning',
                                            default => 'success',
                                        };
                                    ?>
                                    <span class="badge text-bg-<?= $badge ?>"><span class="visually-hidden">Risk level: </span><?= e($prediction['risk_level']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted">&mdash;</span>
                                <?php endif; ?>
                            </td>
                            <td><?= $prediction['risk_score'] !== null ? e(number_format((float) $prediction['risk_score'], 4)) : '&mdash;' ?></td>
                            <td><?= $prediction['confidence'] !== null ? e(number_format((float) $prediction['confidence'] * 100, 1)) . '%' : '&mdash;' ?></td>
                            <td><code><?= e($prediction['model_version']) ?></code></td>
                            <td>
                                <?php if ($prediction['status'] === 'failed'): ?>
                                    <span class="text-danger" title="<?= e((string) $prediction['explanation']) ?>">failed</span>
                                <?php else: ?>
                                    <span class="text-<?= $prediction['status'] === 'delivered' ? 'success' : 'muted' ?>"><?= e($prediction['status']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($prediction['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($canManage): ?>
        <h2 class="h5 mt-4">Symptom assessment</h2>
        <p class="text-muted small">
            Enter the symptoms the student described. The decision-support assistant
            suggests possible conditions to guide the consultation. This is a
            suggestion only, never a diagnosis — clinical judgment always decides.
        </p>
        <form method="post" action="<?= e(base_url('/records/' . (int) $student['id'] . '/symptoms/assess')) ?>" class="row g-2 align-items-end mb-3" novalidate>
            <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
            <div class="col-md-8">
                <label for="symptoms" class="form-label visually-hidden">Symptoms the student described</label>
                <textarea class="form-control" id="symptoms" name="symptoms" rows="2" maxlength="2000" placeholder="e.g. fever, chills, headache, body aches, loss of appetite" required></textarea>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-sm btn-primary">Run symptom assessment</button>
            </div>
        </form>
        <?php if ($symptomAssessments === []): ?>
            <p class="text-muted">No symptom assessments yet. Run one above to record a suggestion list.</p>
        <?php else: ?>
            <?php foreach ($symptomAssessments as $assessment): ?>
                <article class="border rounded p-3 my-3" aria-label="Symptom assessment on <?= e($assessment['created_at']) ?>">
                    <h3 class="h6 d-flex flex-wrap justify-content-between gap-2">
                        <span>Symptom assessment &middot; <?= e($assessment['created_at']) ?></span>
                        <?php if ($assessment['status'] === 'failed'): ?>
                            <span class="text-danger">failed</span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary"><span class="visually-hidden">Model: </span><?= e($assessment['model_version']) ?></span>
                        <?php endif; ?>
                    </h3>
                    <p class="mb-2"><strong>Symptoms entered:</strong> <?= e($assessment['symptoms_text']) ?></p>
                    <?php
                        $result = json_decode((string) $assessment['result'], true);
                        $conditions = is_array($result) && isset($result['conditions']) ? $result['conditions'] : [];
                    ?>
                    <?php if ($conditions === []): ?>
                        <p class="text-muted mb-0"><?= $assessment['status'] === 'failed' ? e((string) $assessment['explanation']) : 'No conditions matched the entered symptoms.' ?></p>
                    <?php else: ?>
                        <ol class="mb-0">
                            <?php foreach ($conditions as $idx => $item): ?>
                                <li class="mb-2">
                                    <strong><?= e($item['condition']) ?></strong>
                                    <?php
                                        $badge = match ($item['level']) {
                                            'high' => 'danger',
                                            'moderate' => 'warning',
                                            default => 'success',
                                        };
                                    ?>
                                    <span class="badge text-bg-<?= $badge ?>"><span class="visually-hidden">Match level: </span><?= e($item['level']) ?></span>
                                    <span class="text-muted small">score <?= e(number_format((float) $item['score'] * 100, 0)) ?>% &middot; confidence <?= e(number_format((float) $item['confidence'] * 100, 0)) ?>%</span>
                                    <span class="d-block small text-muted"><?= e($item['advice']) ?></span>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($canSendAlerts): ?>
        <h2 class="h5 mt-4">Send an authorized health alert</h2>
        <p class="text-muted small">
            Delivers a privacy-safe advisory to this student's notification inbox.
            The message uses a fixed template and never includes clinical detail.
        </p>
        <form method="post" action="<?= e(base_url('/records/' . (int) $student['id'] . '/health-alert')) ?>" class="row g-2 align-items-end mb-3" novalidate>
            <input type="hidden" name="_csrf" value="<?= e(\App\Security\Security::csrfToken()) ?>">
            <div class="col-auto">
                <label for="alert_template" class="form-label visually-hidden">Health alert type</label>
                <select class="form-select form-select-sm" id="alert_template" name="template">
                    <?php foreach (\App\Controllers\NotificationController::healthAlertTemplates() as $tplKey => $tpl): ?>
                        <option value="<?= e($tplKey) ?>"><?= e($tpl['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-warning" data-confirm="Send a health alert to this student?">Send health alert</button>
            </div>
        </form>
    <?php endif; ?>

    <p class="mt-4">
        <a href="<?= e(base_url('/records')) ?>" class="btn btn-outline-secondary">&larr; Back to all students</a>
    </p>
</section>
