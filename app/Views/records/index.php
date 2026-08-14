<?php /** @var string $title */ ?>
<?php /** @var string $page */ ?>
<?php /** @var array<int, array<string, mixed>> $students */ ?>
<?php /** @var int $total */ ?>
<?php /** @var int $page */ ?>
<?php /** @var int $pages */ ?>
<section aria-labelledby="records-heading">
    <h1 id="records-heading" class="h3">Student Health Records</h1>
    <p class="lead">Browse enrolled students. Select a student to view their health profile.</p>

    <p><?= e((string) $total) ?> student<?= $total === 1 ? '' : 's' ?> enrolled.</p>

    <?php if ($students === []): ?>
        <div class="alert alert-secondary" role="alert">No students are currently enrolled.</div>
    <?php else: ?>
        <div class="table-responsive" tabindex="0">
            <table class="table table-hover align-middle">
                <caption class="visually-hidden">List of enrolled students</caption>
                <thead>
                    <tr>
                        <th scope="col">Name</th>
                        <th scope="col">Reg. number</th>
                        <th scope="col">Department</th>
                        <th scope="col">Level</th>
                        <th scope="col">Email</th>
                        <th scope="col"><span class="visually-hidden">Action</span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($students as $s): ?>
                        <tr>
                            <th scope="row"><?= e($s['last_name'] . ', ' . $s['first_name']) ?></th>
                            <td><?= e($s['reg_number']) ?></td>
                            <td><?= e($s['department']) ?></td>
                            <td><?= e($s['level_of_study']) ?></td>
                            <td><?= e($s['email']) ?></td>
                            <td>
                                <a class="btn btn-sm btn-outline-primary"
                                   href="<?= e(base_url('/records/' . (int) $s['id'])) ?>">View record</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($pages > 1): ?>
            <nav aria-label="Student records pages">
                <ul class="pagination">
                    <?php for ($i = 1; $i <= $pages; $i++): ?>
                        <li class="page-item<?= $i === $page ? ' active' : '' ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>>
                            <a class="page-link" href="<?= e(base_url('/records?page=' . $i)) ?>"><?= $i ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    <?php endif; ?>
</section>
