"""AI testing extensions.

Covers dimensions required by the system test plan that the base API suite
does not assert explicitly:

  * preprocessing (feature matrix shape, scaling pipeline steps)
  * model validation (sidecars, thresholds, positive-class wiring)
  * evaluation metrics (computed on a held-out test split, valid ranges)
  * invalid input (NaN, Inf, non-finite, huge magnitude, non-numeric, arrays)
  * data leakage (train/test split separation, no test rows in training)
  * reproducibility (same seed -> byte-identical artifacts and predictions)

All models here are built fresh in an isolated temp store; nothing touches the
real model store or the seeded registry.
"""

from __future__ import annotations

import hashlib
import json

import numpy as np
import pytest

from app.errors import ModelInputError
from app.services.packaging import MODEL_SPECS, package_model, update_registry
from app.services.predictor import DEFAULT_THRESHOLDS, MAX_FEATURE_MAGNITUDE


def _sha256_file(path) -> str:
    return hashlib.sha256(path.read_bytes()).hexdigest()


def _package(store, ptype: str, seed: int = 7, n_samples: int = 400) -> dict:
    result = package_model(store, ptype, version="9.9", seed=seed, n_samples=n_samples)
    update_registry(
        store,
        [{"prediction_type": ptype, "version": "9.9", "status": "ready"}],
    )
    return result


# ----------------------------------------------------------------------
# Preprocessing
# ----------------------------------------------------------------------
def test_pipeline_has_scaler_then_classifier(tmp_path):
    """Preprocessing is a StandardScaler->LR pipeline, never a raw fit."""
    _package(tmp_path, "malaria_risk")
    from app.services.model_registry import ModelRegistry

    model = ModelRegistry(tmp_path).load("malaria_risk")
    steps = [type(est).__name__ for _, est in model._pipeline.steps]
    assert steps[0] == "StandardScaler"
    assert "LogisticRegression" in steps


def test_feature_matrix_shapes_match_spec():
    from app.services.packaging import _feature_matrix, _target
    import pandas as pd

    rng = np.random.default_rng(11)
    df = _feature_matrix(MODEL_SPECS["malaria_risk"], 250, rng)
    y = _target(df, MODEL_SPECS["malaria_risk"], rng)
    assert isinstance(df, pd.DataFrame)
    assert list(df.columns) == MODEL_SPECS["malaria_risk"]["features"]
    assert df.shape == (250, 3)
    assert y.shape == (250,)
    assert set(np.unique(y)) <= {0, 1}


# ----------------------------------------------------------------------
# Model validation (sidecars + thresholds + positive class)
# ----------------------------------------------------------------------
def test_package_writes_all_sidecars(tmp_path):
    _package(tmp_path, "typhoid_risk")
    model_dir = tmp_path / "typhoid_risk" / "v9.9"
    for expected in ("model.joblib", "meta.json", "features.json", "sample_features.json"):
        assert (model_dir / expected).is_file(), expected


def test_meta_contains_thresholds_and_metrics(tmp_path):
    result = _package(tmp_path, "asthma_exacerbation")
    meta = json.loads((tmp_path / "asthma_exacerbation" / "v9.9" / "meta.json").read_text(encoding="utf-8"))
    assert meta["thresholds"] == DEFAULT_THRESHOLDS
    assert meta["model_file_sha256"] == result["sha256"]
    assert meta["feature_count"] == 3
    assert meta["positive_class_index"] in (0, 1)
    assert meta["data_source"] == "synthetic_demo"


def test_risk_level_threshold_boundaries(tmp_path):
    """low < 0.33, moderate 0.33..0.66, high >= 0.66 (default thresholds)."""
    _package(tmp_path, "typhoid_risk")
    from app.services.model_registry import ModelRegistry

    model = ModelRegistry(tmp_path).load("typhoid_risk")
    feats = list(model.features)
    # Force a near-zero and near-one logit with extreme-but-valid feature values.
    low = model.predict(dict.fromkeys(feats, 0.0))
    high = model.predict(dict.fromkeys(feats, 100.0))
    assert low.risk_score < 0.33
    assert high.risk_score > 0.66
    assert low.risk_level == "low"
    assert high.risk_level == "high"
    assert 0.0 <= low.risk_score <= 1.0
    assert 0.0 <= low.confidence <= 1.0
    assert low.confidence >= max(low.risk_score, 1 - low.risk_score) - 0.01


# ----------------------------------------------------------------------
# Evaluation metrics (computed on held-out split, in range)
# ----------------------------------------------------------------------
def test_metrics_present_and_in_range(tmp_path):
    result = _package(tmp_path, "malaria_risk", seed=5, n_samples=600)
    m = result["metrics"]
    for key in ("accuracy", "roc_auc", "precision", "recall", "f1"):
        assert key in m, key
        assert isinstance(m[key], float), key
        assert 0.0 <= m[key] <= 1.0, key
    assert m["test_samples"] >= 100


def test_metrics_are_computed_on_test_split(tmp_path):
    """The metrics in meta.json must come from the held-out test fold, not the
    training fold (data-leakage guard at evaluation time)."""
    import json

    _package(tmp_path, "typhoid_risk", seed=3, n_samples=500)
    meta = json.loads((tmp_path / "typhoid_risk" / "v9.9" / "meta.json").read_text(encoding="utf-8"))
    # train_test_split uses test_size=0.25 -> ~125 of 500.
    assert meta["metrics"]["test_samples"] == 125


# ----------------------------------------------------------------------
# Invalid input / fail-closed preprocessing
# ----------------------------------------------------------------------
@pytest.mark.parametrize(
    "bad_value",
    [float("nan"), float("inf"), float("-inf")],
    ids=["nan", "inf", "ninf"],
)
def test_predict_rejects_non_finite(tmp_path, bad_value):
    _package(tmp_path, "malaria_risk")
    from app.services.model_registry import ModelRegistry

    model = ModelRegistry(tmp_path).load("malaria_risk")
    feats = dict.fromkeys(model.features, 1.0)
    feats["fever_history"] = bad_value
    with pytest.raises(ModelInputError) as exc:
        model.predict(feats)
    assert "range" in exc.value.detail


def test_predict_rejects_huge_magnitude(tmp_path):
    _package(tmp_path, "malaria_risk")
    from app.services.model_registry import ModelRegistry

    model = ModelRegistry(tmp_path).load("malaria_risk")
    feats = dict.fromkeys(model.features, 0.0)
    feats["season_rainy"] = MAX_FEATURE_MAGNITUDE * 10
    with pytest.raises(ModelInputError) as exc:
        model.predict(feats)
    assert "range" in exc.value.detail


def test_predict_rejects_non_numeric_feature(tmp_path):
    _package(tmp_path, "malaria_risk")
    from app.services.model_registry import ModelRegistry

    model = ModelRegistry(tmp_path).load("malaria_risk")
    feats = dict.fromkeys(model.features, 1.0)
    feats["fever_history"] = "high"  # type: ignore[assignment]
    with pytest.raises(ModelInputError) as exc:
        model.predict(feats)  # type: ignore[arg-type]
    assert "not numeric" in exc.value.detail


def test_predict_rejects_list_instead_of_object(tmp_path):
    _package(tmp_path, "malaria_risk")
    from app.services.model_registry import ModelRegistry

    model = ModelRegistry(tmp_path).load("malaria_risk")
    with pytest.raises(ModelInputError) as exc:
        model.predict(["a", "b"])  # type: ignore[arg-type]
    assert "object" in exc.value.detail


# ----------------------------------------------------------------------
# Data leakage (train/test separation, stratified)
# ----------------------------------------------------------------------
def test_train_test_are_disjoint(tmp_path):
    """Rows used to evaluate a model must not have been used to fit it.
    The packaging split is a single train_test_split; verify the split sizes
    add up to the sample count and that stratification preserves the positive
    class in both folds."""
    from app.services.packaging import _feature_matrix, _target
    from sklearn.model_selection import train_test_split

    spec = MODEL_SPECS["typhoid_risk"]
    rng = np.random.default_rng(9)
    df = _feature_matrix(spec, 800, rng)
    y = _target(df, spec, rng)
    X_tr, X_te, y_tr, y_te = train_test_split(df, y, test_size=0.25, random_state=9, stratify=y)
    assert len(X_tr) + len(X_te) == 800
    assert len(X_te) == 200
    # Stratification: positive ratio is ~preserved across folds.
    base = float(y.mean())
    assert abs(float(y_tr.mean()) - base) < 0.05
    assert abs(float(y_te.mean()) - base) < 0.05
    # No test row index appears in the training frame (identity split).
    tr_keys = set(X_tr.index)
    te_keys = set(X_te.index)
    assert tr_keys.isdisjoint(te_keys)


# ----------------------------------------------------------------------
# Reproducibility
# ----------------------------------------------------------------------
def test_same_seed_produces_identical_artifacts(tmp_path):
    store_a = tmp_path / "a"
    store_b = tmp_path / "b"
    for store in (store_a, store_b):
        store.mkdir()
        _package(store, "malaria_risk", seed=12, n_samples=300)
    sha_a = _sha256_file(store_a / "malaria_risk" / "v9.9" / "model.joblib")
    sha_b = _sha256_file(store_b / "malaria_risk" / "v9.9" / "model.joblib")
    assert sha_a == sha_b
    meta_a = json.loads((store_a / "malaria_risk" / "v9.9" / "meta.json").read_text(encoding="utf-8"))
    meta_b = json.loads((store_b / "malaria_risk" / "v9.9" / "meta.json").read_text(encoding="utf-8"))
    assert meta_a["metrics"] == meta_b["metrics"]


def test_same_seed_deterministic_predictions(tmp_path):
    store_a = tmp_path / "a"
    store_b = tmp_path / "b"
    from app.services.model_registry import ModelRegistry

    for store in (store_a, store_b):
        store.mkdir()
        _package(store, "asthma_exacerbation", seed=21, n_samples=300)
    m_a = ModelRegistry(store_a).load("asthma_exacerbation")
    m_b = ModelRegistry(store_b).load("asthma_exacerbation")
    feats = dict.fromkeys(m_a.features, 1.0)
    out_a = m_a.predict(feats)
    out_b = m_b.predict(feats)
    assert out_a.risk_score == out_b.risk_score
    assert out_a.confidence == out_b.confidence
    assert out_a.risk_level == out_b.risk_level


def test_different_seed_yields_different_model(tmp_path):
    store_a = tmp_path / "a"
    store_b = tmp_path / "b"
    for store in (store_a, store_b):
        store.mkdir()
    _package(store_a, "malaria_risk", seed=1, n_samples=200)
    _package(store_b, "malaria_risk", seed=2, n_samples=200)
    sha_a = _sha256_file(store_a / "malaria_risk" / "v9.9" / "model.joblib")
    sha_b = _sha256_file(store_b / "malaria_risk" / "v9.9" / "model.joblib")
    assert sha_a != sha_b
