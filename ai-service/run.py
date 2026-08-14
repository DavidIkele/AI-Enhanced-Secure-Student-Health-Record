"""Run the AI service on the loopback interface only.

The service must never listen on a public interface. The PHP backend calls it
via http://127.0.0.1:<AI_PORT>. To keep internal traffic private, the host is
fixed to 127.0.0.1 regardless of environment.

Usage:
    python run.py
"""

from __future__ import annotations

from pathlib import Path

import uvicorn
from dotenv import load_dotenv

# Load the local .env BEFORE importing app.config: Settings reads environment
# variables at import time, so the key/host/port must be present already.
load_dotenv(Path(__file__).resolve().parent / ".env")

from app.config import SERVICE_NAME, SERVICE_VERSION, settings

if __name__ == "__main__":
    print(f"{SERVICE_NAME} v{SERVICE_VERSION} binding to {settings.host}:{settings.port}", flush=True)
    uvicorn.run(
        "app.main:app",
        host=settings.host,
        port=settings.port,
        log_level="info",
        access_log=False,  # our own sanitized access log middleware is used
    )