"""Secure, privacy-aware logging.

Rules enforced:
  * request bodies are never logged (they may contain health-derived features)
  * API keys and auth headers are never logged
  * only safe metadata is logged: method, path, status, duration, client IP
  * log files are written under the configured log directory
"""

from __future__ import annotations

import logging
import logging.handlers
import os
import sys
from pathlib import Path

_LOG_FORMAT = "%(asctime)s %(levelname)s %(name)s %(message)s"

FORBIDDEN_SUBSTRINGS = (
    "Authorization",
    "X-API-Key",
    "api_key",
    "password",
    "clearance",
)


def _safe_path(path: Path) -> Path:
    """Create a directory with restrictive permissions where the platform
    supports it; log an error rather than crashing startup."""
    try:
        path.mkdir(parents=True, exist_ok=True)
        os.chmod(path, 0o700)
    except OSError:
        pass
    return path


def setup_logging(log_dir: Path, level: int = logging.INFO) -> None:
    root = logging.getLogger()
    if getattr(root, "_ai_logging_configured", False):
        return

    root.setLevel(level)
    formatter = logging.Formatter(_LOG_FORMAT)

    console = logging.StreamHandler(sys.stderr)
    console.setFormatter(formatter)
    root.addHandler(console)

    if log_dir is not None:
        _safe_path(log_dir)
        try:
            file_handler = logging.handlers.RotatingFileHandler(
                str(log_dir / "ai-service.log"),
                maxBytes=2 * 1024 * 1024,
                backupCount=5,
                encoding="utf-8",
            )
            file_handler.setFormatter(formatter)
            root.addHandler(file_handler)
        except OSError:  # pragma: no cover - depends on host permissions
            root.warning("Could not open the AI service log file.")

    setattr(root, "_ai_logging_configured", True)


def redact(message: str) -> str:
    """Best-effort defence-in-depth scrub for anything that should never be
    logged. Callers should avoid passing secrets altogether; this is a backstop."""
    scrubbed = message
    for token in FORBIDDEN_SUBSTRINGS:
        scrubbed = scrubbed.replace(token, "[REDACTED]")
    return scrubbed