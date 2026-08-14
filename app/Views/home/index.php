<?php
$auth = new \App\Services\AuthService();
$signedIn = $auth->check();
$portalStaff = $signedIn && $auth->id() !== null && (\App\Security\AccessControl::can((int) $auth->id(), 'records.manage') || \App\Security\AccessControl::can((int) $auth->id(), 'analytics.view'));
$portalUrl = $signedIn ? base_url($portalStaff ? '/dashboard' : '/profile') : base_url('/auth/login');
$portalLabel = $signedIn ? ($portalStaff ? 'Open my dashboard' : 'Open my profile') : 'Sign in to the portal';
?>

<section class="home-hero px-4 px-md-5 py-5" aria-labelledby="home-hero-title">
    <p class="home-eyebrow mb-2">Nnamdi Azikiwe University &middot; Health Centre</p>
    <h1 id="home-hero-title" class="display-5 fw-bold lh-sm mb-3">Secure digital health records for every student</h1>
    <p class="home-hero-lead col-lg-10 mb-4">
        One private platform for clinic visits, medical history, appointments, health analytics
        and AI-assisted decision support — built around confidentiality and role-based access.
    </p>

    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-lg btn-hero-primary" href="<?= e($portalUrl) ?>"><?= e($portalLabel) ?></a>
        <a class="btn btn-lg btn-hero-ghost" href="<?= e(base_url('/#features')) ?>">Explore features</a>
    </div>

    <?php if (!$signedIn): ?>
        <p class="mb-4">
            New student? <a class="link-warning fw-semibold text-decoration-underline" href="<?= e(base_url('/auth/register')) ?>">Create your account</a> to book appointments and receive health alerts.
        </p>
    <?php endif; ?>

    <div class="home-hero-proof px-4 py-3">
        <ul class="list-unstyled d-flex flex-column flex-md-row flex-wrap gap-2 gap-md-4 mb-0">
            <li class="d-flex align-items-center gap-2">
                <svg class="home-proof-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l7 3v5c0 4.6-3.1 8.2-7 10-3.9-1.8-7-5.4-7-10V5l7-3z"/></svg>
                Role-based access control
            </li>
            <li class="d-flex align-items-center gap-2">
                <svg class="home-proof-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                Strong password hashing &amp; sessions
            </li>
            <li class="d-flex align-items-center gap-2">
                <svg class="home-proof-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                Complete audit trail
            </li>
        </ul>
    </div>
</section>

<section id="features" class="pt-5" aria-labelledby="features-title">
    <div class="text-center mb-4">
        <p class="section-kicker mb-1">What the platform provides</p>
        <h2 id="features-title" class="h2 fw-bold">Everything a modern university health centre needs</h2>
        <p class="text-secondary mx-auto col-lg-8">
            Working modules for students, clinic staff and administrators — all in one place.
        </p>
    </div>

    <div class="row g-4">
        <div class="col-md-6 col-lg-4">
            <div class="feature-card h-100">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l7 3v5c0 4.6-3.1 8.2-7 10-3.9-1.8-7-5.4-7-10V5l7-3z"/></svg>
                </div>
                <h3 class="h5 fw-semibold">Secure, role-based access</h3>
                <p class="text-secondary mb-0">
                    Students, clinic staff and administrators each see only what their role permits,
                    protected by rate limiting, account lockout and secure sessions.
                </p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="feature-card h-100">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><rect x="8" y="2" width="8" height="4" rx="1"/></svg>
                </div>
                <h3 class="h5 fw-semibold">Student health records</h3>
                <p class="text-secondary mb-0">
                    Consolidated medical history, clinic visits and vitals in one private record per
                    student — updated only by authorised staff.
                </p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="feature-card h-100">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                </div>
                <h3 class="h5 fw-semibold">Appointments without clashes</h3>
                <p class="text-secondary mb-0">
                    Request, review and manage clinic appointments, with automatic conflict detection
                    against staff availability and schedules.
                </p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="feature-card h-100">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 20V10M12 20V4M6 20v-6"/></svg>
                </div>
                <h3 class="h5 fw-semibold">Analytics &amp; outbreak alerts</h3>
                <p class="text-secondary mb-0">
                    Visit analytics and illness-pattern detection help the health centre respond early
                    to emerging health trends on campus.
                </p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="feature-card h-100">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <h3 class="h5 fw-semibold">AI-assisted decision support</h3>
                <p class="text-secondary mb-0">
                    Non-diagnostic AI risk flags support staff in triage and review. Outputs are always
                    informational — never a medical diagnosis.
                </p>
            </div>
        </div>
        <div class="col-md-6 col-lg-4">
            <div class="feature-card h-100">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/></svg>
                </div>
                <h3 class="h5 fw-semibold">Notifications &amp; audit logging</h3>
                <p class="text-secondary mb-0">
                    In-app notifications for students and staff, plus a tamper-visible audit log of every
                    sensitive action in the system.
                </p>
            </div>
        </div>
    </div>
</section>

<section id="audiences" class="pt-5" aria-labelledby="audiences-title">
    <div class="text-center mb-4">
        <p class="section-kicker mb-1">Who it is for</p>
        <h2 id="audiences-title" class="h2 fw-bold">Built for everyone at the health centre</h2>
        <p class="text-secondary mx-auto col-lg-9">
            Every role sees exactly the information it needs — nothing more. Students,
            clinic staff and administrators each work in their own private area of the
            platform, and access is enforced by role-based permissions on every request.
        </p>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="audience-card h-100">
                <h3 class="h5 fw-semibold">Students</h3>
                <p class="text-secondary small mb-2">Follow your own health journey from anywhere.</p>
                <p class="small mb-2">
                    Log in to your personal profile to see your appointments, clinic visits,
                    health alerts and personalised insights — and to manage how the health
                    centre communicates with you.
                </p>
                <ul class="audience-list">
                    <li>Request and track clinic appointments</li>
                    <li>Receive health alerts and announcements</li>
                    <li>Dismiss or keep personalised insights</li>
                    <li>Update your contact details and preferences</li>
                    <li>Download a copy of your own data at any time</li>
                </ul>
            </div>
        </div>
        <div class="col-md-4">
            <div class="audience-card h-100">
                <h3 class="h5 fw-semibold">Clinic staff</h3>
                <p class="text-secondary small mb-2">Run the clinic with complete records.</p>
                <p class="small mb-2">
                    Doctors and nurses maintain each student's health record, record visits,
                    capture symptoms for AI-assisted assessment and manage the appointment
                    calendar — all within a single, audited workspace.
                </p>
                <ul class="audience-list">
                    <li>Manage records, vitals and medical history</li>
                    <li>Enter symptoms for AI decision-support suggestions</li>
                    <li>Review AI risk flags before decisions</li>
                    <li>Approve, reject and reschedule visits</li>
                    <li>Monitor visit analytics and outbreak alerts</li>
                </ul>
            </div>
        </div>
        <div class="col-md-4">
            <div class="audience-card h-100">
                <h3 class="h5 fw-semibold">Administrators</h3>
                <p class="text-secondary small mb-2">Govern access and monitor everything.</p>
                <p class="small mb-2">
                    Administrators control accounts, roles and permissions, broadcast
                    announcements and review a complete audit trail of every sensitive
                    action — keeping the platform accountable and secure.
                </p>
                <ul class="audience-list">
                    <li>Manage accounts, roles and permissions</li>
                    <li>Broadcast system announcements</li>
                    <li>Review the full, immutable audit trail</li>
                    <li>Deactivate or restore user accounts</li>
                    <li>Oversee the AI service and system health</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<section id="security" class="pt-5" aria-labelledby="security-title">
    <div class="security-band px-4 px-md-5 py-4">
        <div class="d-flex flex-column flex-lg-row align-items-start gap-4">
            <div class="security-band-head">
                <p class="section-kicker mb-1">Security &amp; privacy by design</p>
                <h2 id="security-title" class="h3 fw-bold mb-0">Your health data stays private</h2>
            </div>
            <ul class="list-unstyled d-flex flex-column flex-md-row flex-wrap gap-2 gap-md-4 mb-0 ms-lg-auto">
                <li class="d-flex align-items-center gap-2">
                    <svg class="home-proof-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 2l7 3v5c0 4.6-3.1 8.2-7 10-3.9-1.8-7-5.4-7-10V5l7-3z"/></svg>
                    Role-based access control
                </li>
                <li class="d-flex align-items-center gap-2">
                    <svg class="home-proof-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                    Complete audit logging
                </li>
                <li class="d-flex align-items-center gap-2">
                    <svg class="home-proof-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>
                    CSRF, session &amp; rate-limit protection
                </li>
            </ul>
        </div>
        <p class="small mb-2 mt-3">
            Health records are highly sensitive, so the platform treats them that way. Access is
            granted only through role-based permissions that are checked server-side on every
            request, and the database uses a least-privilege account that can only read and
            write the data the application needs — never admin-level control. Every sensitive
            action (sign-ins, profile changes, record updates, AI assessments) is written to an
            append-only audit log, so activity can always be traced to the person responsible.
        </p>
        <p class="small mb-0">
            Passwords are hashed with a strong, one-way algorithm; sessions are protected and
            expire automatically; forms are shielded against cross-site request forgery; and
            failed sign-in attempts are rate-limited and locked out to resist brute-force
            attacks. AI decision-support runs on a separate loopback-only service that never
            receives your identity, and its suggestions are always advisory. You can also
            export your own data or deactivate your account from your profile at any time.
        </p>
        <p class="small mb-0 mt-3 security-disclaimer">
            This is a decision-support system. AI outputs are informational and never a medical diagnosis.
            For any medical concern, always consult the University Health Centre directly.
        </p>
    </div>
</section>

<section class="pt-5 pb-4" aria-labelledby="cta-title">
    <div class="home-cta text-center px-4 py-5">
        <h2 id="cta-title" class="h2 fw-bold mb-2">Ready to get started?</h2>
        <p class="home-hero-lead mx-auto col-lg-7 mb-4">
            <?= $signedIn ? ($portalStaff ? 'Return to your dashboard to continue where you left off.' : 'Return to your profile to continue where you left off.') : 'Sign in with your UNIZIK account to access your health records and the clinic portal.' ?>
        </p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a class="btn btn-lg btn-hero-primary" href="<?= e($portalUrl) ?>"><?= e($portalLabel) ?></a>
            <a class="btn btn-lg btn-hero-ghost" href="<?= e(base_url('/system/health')) ?>">Check system health</a>
        </div>
    </div>
</section>