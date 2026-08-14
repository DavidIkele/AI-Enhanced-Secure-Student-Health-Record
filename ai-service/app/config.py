"""Service configuration — everything comes from the environment.

No secrets are hard-coded. The API key must be provided at runtime via the
AI_API_KEY environment variable (or the service runs in a fail-closed state
where only the public health endpoint works).
"""

from __future__ import annotations

import os
from pathlib import Path

SERVICE_NAME = "unizik-ai-service"
SERVICE_VERSION = "0.1.0"

# Upper bound on accepted JSON request bodies, to limit abuse.
MAX_BODY_BYTES = 64 * 1024  # 64 KiB


def _as_bool(value: str | None, default: bool = False) -> bool:
    if value is None:
        return default
    return value.strip().lower() in ("1", "true", "yes", "on")


class Settings:
    def __init__(self) -> None:
        self.env = os.getenv("APP_ENV", "production").strip() or "production"
        self.debug = _as_bool(os.getenv("APP_DEBUG"))
        self.api_key = os.getenv("AI_API_KEY", "").strip()
        self.host = os.getenv("AI_HOST", "127.0.0.1").strip() or "127.0.0.1"
        self.port = int(os.getenv("AI_PORT", "8000") or "8000")
        self.max_body_bytes = int(os.getenv("AI_MAX_BODY_BYTES", str(MAX_BODY_BYTES)))

        # Model store path; defaults to <service>/models.
        here = Path(__file__).resolve().parent.parent
        self.model_store: Path = Path(
            os.getenv("AI_MODEL_STORE", str(here / "models"))
        ).resolve()
        self.registry_file: Path = self.model_store / "registry.json"

        # Log directory; defaults to <service>/logs.
        self.log_dir: Path = Path(
            os.getenv("AI_LOG_DIR", str(here / "logs"))
        ).resolve()

    @property
    def auth_enabled(self) -> bool:
        return bool(self.api_key)


settings = Settings()