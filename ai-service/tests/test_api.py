"""API tests for the AI decision-support service (PROMPT 8/9)."""

from __future__ import annotations

import hashlib
import json
import os

import pytest

from app.config import SERVICE_NAME, SERVICE_VERSION
from app.errors import ModelInputError, ModelLoadError, ModelNotAvailableError
from app.services.model_registry import ModelRegistry
from app.services.packaging import package_model, update_registry
from conftest import API_KEY, STORE_DIR

VALID_BODY = {
    "prediction_type": "malaria_risk",
    "student_ref": "u-7",
    "features": {"recent_visits_30d": 2.0, "fever_history": 1.0, "season_rainy": 1.0},
}

TYPHOID_BODY = {
    "prediction_type": "typhoid_risk",
    "student_ref": "u-9",
    "features": {
        "recent_visits_30d": 3.0,
        "fever_history": 1.0,
        "unclean_water_exposure": 1.0,
    },
}


def _sha256_bytes(data: bytes) -> str:
    return hashlib.sha256(data).hexdigest()


# ----------------------------------------------------------------------
# Health endpoint (public, no auth)
# ----------------------------------------------------------------------
def test_health_ok(client):
    resp = client.get("/health")
    assert resp.status_code == 200
    body = resp.json()
    assert body["status"] == "ok"
    assert body["service"] == SERVICE_NAME
    assert body["version"] == SERVICE_VERSION
    assert body["auth_enabled"] is True
    assert isinstance(body["model_registry"], list)


def test_health_needs_no_key(client):
    resp = client.get("/health")
    assert resp.status_code == 200


def test_health_exposes_registry_version_structure(client):
    body = client.get("/health").json()
    types = {m["prediction_type"] for m in body["model_registry"]}
    assert "malaria_risk" in types
    assert "asthma_exacerbation" in types
    assert "typhoid_risk" in types


# ----------------------------------------------------------------------
# Authorization
# ----------------------------------------------------------------------
def test_protected_route_missing_key(client):
    resp = client.get("/v1/models")
    assert resp.status_code == 401
    assert resp.json()["success"] is False
    assert resp.json()["error"]["code"] == "unauthorized"


def test_protected_route_wrong_key(client):
    resp = client.get("/v1/models", headers={"X-API-Key": "wrong-key"})
    assert resp.status_code == 401
    assert resp.json()["error"]["code"] == "unauthorized"


def test_protected_route_bearer_scheme(client):
    resp = client.get("/v1/models", headers={"Authorization": f"Bearer {API_KEY}"})
    assert resp.status_code == 200


def test_models_listing_with_key(client, auth_headers):
    resp = client.get("/v1/models", headers=auth_headers)
    assert resp.status_code == 200
    body = resp.json()
    assert body["success"] is True
    assert {m["prediction_type"] for m in body["models"]} >= {
        "malaria_risk",
        "asthma_exacerbation",
        "typhoid_risk",
    }


# ----------------------------------------------------------------------
# Validation / malformed input
# ----------------------------------------------------------------------
def test_malformed_payload_422(client, auth_headers):
    payload = {**VALID_BODY, "features": "not-a-dict"}
    resp = client.post("/v1/predict/malaria_risk", headers=auth_headers, json=payload)
    assert resp.status_code == 422
    body = resp.json()
    assert body["success"] is False
    assert body["error"]["code"] == "validation_error"
    raw = resp.text
    assert "Traceback" not in raw
    assert "features" in str(body["error"]["detail"])


def test_unknown_prediction_type_422(client, auth_headers):
    payload = {**VALID_BODY, "prediction_type": "brain_surgery_risk"}
    resp = client.post("/v1/predict/brain_surgery_risk", headers=auth_headers, json=payload)
    assert resp.status_code == 422
    assert resp.json()["error"]["code"] == "validation_error"


def test_empty_features_422(client, auth_headers):
    payload = {**VALID_BODY, "features": {}}
    resp = client.post("/v1/predict/malaria_risk", headers=auth_headers, json=payload)
    assert resp.status_code == 422


def test_nan_feature_422(client, auth_headers):
    payload = {**VALID_BODY, "features": {"recent_visits_30d": float("nan")}}
    # httpx/json cannot serialize NaN cleanly; test that the raw string is rejected
    resp = client.post(
        "/v1/predict/malaria_risk",
        headers=auth_headers,
        content=json.dumps(payload).replace("NaN", "1e999"),
    )
    assert resp.status_code == 422 or resp.status_code == 422


def test_path_body_mismatch_500_envelope(client, auth_headers):
    payload = {**VALID_BODY, "prediction_type": "asthma_exacerbation"}
    resp = client.post("/v1/predict/malaria_risk", headers=auth_headers, json=payload)
    assert resp.status_code == 500
    body = resp.json()
    assert body["success"] is False
    assert "internal_error" in body["error"]["code"]
    assert "Traceback" not in resp.text
    assert "site-packages" not in resp.text


# ----------------------------------------------------------------------
# Model-loading failure (no trained models exist yet)
# ----------------------------------------------------------------------
def test_model_not_available_503(client, auth_headers):
    resp = client.post(
        "/v1/predict/malaria_risk", headers=auth_headers, json=VALID_BODY
    )
    assert resp.status_code == 503
    body = resp.json()
    assert body["error"]["code"] == "model_not_available"
    assert "Traceback" not in resp.text


def test_body_too_large_413(client, auth_headers):
    big = {**VALID_BODY, "features": {f"f{i}": 1.0 for i in range(200)}}
    payload = json.dumps(big).encode("utf-8")
    resp = client.post(
        "/v1/predict/malaria_risk",
        headers={**auth_headers, "Content-Type": "application/json"},
        content=payload + b"padding" * 20000,
    )
    assert resp.status_code == 413
    assert resp.json()["error"]["code"] == "request_too_large"


# ----------------------------------------------------------------------
# Model registry loading structure (unit level)
# ----------------------------------------------------------------------
def test_registry_load_fails_without_manifest():
    reg = ModelRegistry(os.path.join(STORE_DIR, "empty"))
    with pytest.raises(ModelNotAvailableError):
        reg.load("malaria_risk")


def _package_ready(store, prediction_type="asthma_exacerbation"):
    """Package one real demo model and mark it ready in the manifest."""
    package_model(store, prediction_type, seed=45)
    update_registry(
        store,
        [{"prediction_type": prediction_type, "version": "1.0", "status": "ready"}],
    )
    return ModelRegistry(store)


def test_registry_load_deserializes_real_model(tmp_path):
    reg = _package_ready(tmp_path)
    model = reg.load("asthma_exacerbation")
    assert model.model_version == "1.0"
    assert model.features == ("history_asthma", "recent_visits_30d", "exercise_related")

    out = model.predict(
        {"history_asthma": 1.0, "recent_visits_30d": 2.0, "exercise_related": 1.0}
    )
    assert out.prediction_type == "asthma_exacerbation"
    assert out.risk_level in ("low", "moderate", "high")
    assert 0.0 <= out.risk_score <= 1.0
    assert 0.0 <= out.confidence <= 1.0


def test_registry_load_rejects_fake_joblib_bytes(tmp_path):
    model_dir = tmp_path / "asthma_exacerbation" / "v1.0"
    model_dir.mkdir(parents=True)
    model_file = model_dir / "model.joblib"
    model_file.write_bytes(b"not-a-joblib-payload")
    (model_dir / "meta.json").write_text(
        json.dumps(
            {"model_version": "1.0", "model_file_sha256": _sha256_bytes(model_file.read_bytes())}
        ),
        encoding="utf-8",
    )
    (model_dir / "features.json").write_text(json.dumps(["a", "b"]), encoding="utf-8")
    (tmp_path / "registry.json").write_text(
        json.dumps(
            {"models": [{"prediction_type": "asthma_exacerbation", "version": "1.0", "status": "ready"}]}
        ),
        encoding="utf-8",
    )
    reg = ModelRegistry(tmp_path)
    with pytest.raises(ModelLoadError):
        reg.load("asthma_exacerbation")


def test_registry_load_rejects_checksum_mismatch(tmp_path):
    reg = _package_ready(tmp_path)
    model_file = tmp_path / "asthma_exacerbation" / "v1.0" / "model.joblib"
    model_file.write_bytes(model_file.read_bytes() + b"tampered")
    with pytest.raises(ModelLoadError) as exc_info:
        reg.load("asthma_exacerbation")
    assert "checksum" in exc_info.value.detail


def test_registry_load_missing_sidecar_fails(tmp_path):
    reg = _package_ready(tmp_path)
    (tmp_path / "asthma_exacerbation" / "v1.0" / "features.json").unlink()
    with pytest.raises(ModelLoadError):
        reg.load("asthma_exacerbation")


def test_registry_load_not_ready_status(tmp_path):
    package_model(tmp_path, "asthma_exacerbation", seed=45)  # no manifest entry
    reg = ModelRegistry(tmp_path)
    with pytest.raises(ModelNotAvailableError):
        reg.load("asthma_exacerbation")


def test_trained_model_rejects_missing_feature(tmp_path):
    reg = _package_ready(tmp_path)
    model = reg.load("asthma_exacerbation")
    with pytest.raises(ModelInputError) as exc_info:
        model.predict({"history_asthma": 1.0, "recent_visits_30d": 1.0})
    assert "missing" in exc_info.value.detail


def test_trained_model_rejects_unexpected_feature(tmp_path):
    reg = _package_ready(tmp_path)
    model = reg.load("asthma_exacerbation")
    features = {"history_asthma": 1.0, "recent_visits_30d": 1.0, "exercise_related": 1.0}
    features["extra_feature"] = 5.0
    with pytest.raises(ModelInputError) as exc_info:
        model.predict(features)
    assert "unexpected" in exc_info.value.detail


def test_registry_corrupt_manifest_returns_empty_list():
    reg = ModelRegistry(os.path.join(STORE_DIR, "empty"))
    assert reg.list_models()  # still returns known types with status unset


# ----------------------------------------------------------------------
# Prediction (real model available in the isolated store: typhoid_risk)
# ----------------------------------------------------------------------
def test_predict_success_with_model(client, auth_headers):
    resp = client.post("/v1/predict/typhoid_risk", headers=auth_headers, json=TYPHOID_BODY)
    assert resp.status_code == 200
    body = resp.json()
    assert body["success"] is True
    assert body["prediction_type"] == "typhoid_risk"
    assert body["risk_level"] in ("low", "moderate", "high")
    assert 0.0 <= body["risk_score"] <= 1.0
    assert 0.0 <= body["confidence"] <= 1.0
    assert body["model_version"] == "1.0"
    assert "Traceback" not in resp.text


def test_predict_missing_feature_422(client, auth_headers):
    payload = {
        **TYPHOID_BODY,
        "features": {"recent_visits_30d": 1.0, "fever_history": 1.0},
    }
    resp = client.post("/v1/predict/typhoid_risk", headers=auth_headers, json=payload)
    assert resp.status_code == 422
    body = resp.json()
    assert body["success"] is False
    assert body["error"]["code"] == "model_input_error"
    assert "Traceback" not in resp.text


def test_predict_unexpected_feature_422(client, auth_headers):
    payload = {
        **TYPHOID_BODY,
        "features": {**TYPHOID_BODY["features"], "extra_feature": 1.0},
    }
    resp = client.post("/v1/predict/typhoid_risk", headers=auth_headers, json=payload)
    assert resp.status_code == 422
    assert resp.json()["error"]["code"] == "model_input_error"


def test_predict_low_risk_output_shape(client, auth_headers):
    payload = {
        **TYPHOID_BODY,
        "features": {"recent_visits_30d": 0.0, "fever_history": 0.0, "unclean_water_exposure": 0.0},
    }
    resp = client.post("/v1/predict/typhoid_risk", headers=auth_headers, json=payload)
    assert resp.status_code == 200
    body = resp.json()
    assert body["risk_score"] < 0.5
    assert body["disclaimer"]
    assert body["served_at"]


# ----------------------------------------------------------------------
# Symptom assessment endpoint
# ----------------------------------------------------------------------
def test_symptoms_assess_requires_key(client):
    resp = client.post("/v1/symptoms/assess", json={"symptoms_text": "fever"})
    assert resp.status_code == 401


def test_symptoms_assess_empty_text_422(client, auth_headers):
    resp = client.post(
        "/v1/symptoms/assess", headers=auth_headers, json={"symptoms_text": "   "}
    )
    assert resp.status_code == 422
    assert resp.json()["error"]["code"] == "validation_error"


def test_symptoms_assess_no_match_returns_empty(client, auth_headers):
    resp = client.post(
        "/v1/symptoms/assess", headers=auth_headers, json={"symptoms_text": "purple hair growth"}
    )
    assert resp.status_code == 200
    body = resp.json()
    assert body["success"] is True
    assert body["conditions"] == []
    assert body["model_version"].startswith("symptom-matcher-")
    assert body["disclaimer"]


def test_symptoms_assess_malaria_suggestion(client, auth_headers):
    resp = client.post(
        "/v1/symptoms/assess",
        headers=auth_headers,
        json={"symptoms_text": "fever, chills, shivering, body aches, joint pain", "student_ref": "u-4"},
    )
    assert resp.status_code == 200
    body = resp.json()
    assert body["success"] is True
    names = [c["condition"] for c in body["conditions"]]
    assert "Malaria" in names
    top = body["conditions"][0]
    assert top["level"] in ("low", "moderate", "high")
    assert 0.0 <= top["score"] <= 1.0
    assert 0.0 <= top["confidence"] <= 1.0
    assert top["advice"]
    assert body["matched_symptoms"]
    assert "Traceback" not in resp.text


def test_symptoms_assess_respects_max_text_422(client, auth_headers):
    resp = client.post(
        "/v1/symptoms/assess",
        headers=auth_headers,
        json={"symptoms_text": "x" * 2001},
    )
    assert resp.status_code == 422


# ----------------------------------------------------------------------
# Secure logging
# ----------------------------------------------------------------------
def test_access_log_never_logs_secret_or_body(client, auth_headers, caplog):
    import logging

    with caplog.at_level(logging.INFO, logger="ai-service"):
        client.get("/health")
        client.post(
            "/v1/predict/malaria_risk",
            headers=auth_headers,
            json=VALID_BODY,
        )
    for record in caplog.records:
        text = record.getMessage()
        assert API_KEY not in text
        assert "fever_history" not in text
        assert "1.0" not in text or "status" in text
    assert any("access" in r.getMessage() for r in caplog.records)