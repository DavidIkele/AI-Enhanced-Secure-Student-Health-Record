"""Test configuration.

Environment must be set BEFORE app.main is imported because the Settings
object is created at import time. A fresh, isolated model store and log
directory are used per run, so tests never touch the real store.
"""

from __future__ import annotations

import os
import tempfile

os.environ["AI_API_KEY"] = "test-secret-key"
os.environ["APP_ENV"] = "test"
os.environ["AI_MODEL_STORE"] = tempfile.mkdtemp(prefix="ai-test-store-")
os.environ["AI_LOG_DIR"] = tempfile.mkdtemp(prefix="ai-test-logs-")
os.environ["AI_HOST"] = "127.0.0.1"

import pytest
from fastapi.testclient import TestClient

# Package one real demo model (typhoid_risk) into the isolated store so the
# API success path can be exercised. The other prediction types stay absent,
# which keeps the model_not_available failure-path test meaningful.
from app.services.packaging import package_model, update_registry

MODEL_STORE_DIR = os.environ["AI_MODEL_STORE"]
package_model(MODEL_STORE_DIR, "typhoid_risk", seed=44)
update_registry(
    MODEL_STORE_DIR,
    [{"prediction_type": "typhoid_risk", "version": "1.0", "status": "ready"}],
)

from app import main
from app.config import settings

API_KEY = os.environ["AI_API_KEY"]
STORE_DIR = os.environ["AI_MODEL_STORE"]


@pytest.fixture(scope="session")
def client():
    with TestClient(main.app) as test_client:
        yield test_client


@pytest.fixture
def auth_headers() -> dict:
    return {"X-API-Key": API_KEY}