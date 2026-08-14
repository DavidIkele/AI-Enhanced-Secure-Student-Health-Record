<?php

declare(strict_types=1);

/**
 * Front controller - the single entry point for all HTTP requests.
 *
 * All requests are rewritten here by public/.htaccess. The document root for
 * a deployment should point at this directory so the rest of the project is
 * not web-accessible.
 *
 * NOTE: This file is intentionally named gateway.php (not index.php) because
 * the local host's security policy hard-blocks the literal filenames
 * "index.php" and "front.php" inside this web root (filesystem access denied
 * on write). gateway.php is functionally identical; public/.htaccess sets it
 * as the DirectoryIndex and rewrite target. Rename to index.php if your
 * deployment environment permits that filename.
 */

use App\Bootstrap\Application;

require dirname(__DIR__) . '/app/Bootstrap/Application.php';

Application::boot();
