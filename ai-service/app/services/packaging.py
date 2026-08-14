"""Model packaging helpers (PROMPT 9).

Builds small demo decision-support models from deterministic synthetic data and
writes the on-disk package layout consumed by ``ModelRegistry.load``:

    <store>/<prediction_type>/v<version>/
        model.joblib              # fitted sklearn pipeline (StandardScaler+LR)
        meta.json                 # version, metrics, thresholds, sha256, ...
        features.json             # ordered feature names used at training
        sample_features.json      # example de-identified input frame

and updates ``<store>/registry.json`` (status -> ready).

IMPORTANT: the synthetic datasets encode simple, reproducible signal so the
pipeline is trainable and the service can be demoed end-to-end. These are
demonstration models, not clinical. They must be replaced with models fitted
on real, ethically-cleared data before any deployment.
"""

from __future__ import annotations

import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import joblib
import numpy as np
import pandas as pd
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import (
    accuracy_score,
    f1_score,
    precision_score,
    recall_score,
    roc_auc_score,
)
from sklearn.model_selection import train_test_split
from sklearn.pipeline import make_pipeline
from sklearn.preprocessing import StandardScaler

MODEL_SPECS: dict[str, dict[str, Any]] = {
    "malaria_risk": {
        "label": "malaria risk in the next 30 days",
        "features": ["recent_visits_30d", "fever_history", "season_rainy"],
        "base": {
            "recent_visits_30d": ("poisson", 2.0),
            "fever_history": ("bernoulli", 0.3),
            "season_rainy": ("bernoulli", 0.5),
        },
        "weights": {
            "recent_visits_30d": 0.4,
            "fever_history": 1.6,
            "season_rainy": 1.1,
        },
        "intercept": -2.0,
    },
    "asthma_exacerbation": {
        "label": "asthma-related visit in the next 4 weeks",
        "features": ["history_asthma", "recent_visits_30d", "exercise_related"],
        "base": {
            "history_asthma": ("bernoulli", 0.4),
            "recent_visits_30d": ("poisson", 1.5),
            "exercise_related": ("bernoulli", 0.4),
        },
        "weights": {
            "history_asthma": 1.8,
            "recent_visits_30d": 0.5,
            "exercise_related": 1.0,
        },
        "intercept": -2.3,
    },
    "typhoid_risk": {
        "label": "typhoid risk in the next 30 days",
        "features": ["recent_visits_30d", "fever_history", "unclean_water_exposure"],
        "base": {
            "recent_visits_30d": ("poisson", 1.8),
            "fever_history": ("bernoulli", 0.3),
            "unclean_water_exposure": ("bernoulli", 0.25),
        },
        "weights": {
            "recent_visits_30d": 0.4,
            "fever_history": 1.5,
            "unclean_water_exposure": 1.9,
        },
        "intercept": -2.2,
    },
}

THRESHOLDS = {"low": 0.33, "high": 0.66}


def _feature_matrix(spec: dict[str, Any], n: int, rng: np.random.Generator) -> pd.DataFrame:
    frame: dict[str, np.ndarray] = {}
    for name, (kind, param) in spec["base"].items():
        if kind == "poisson":
            frame[name] = rng.poisson(param, size=n).astype(float)
        elif kind == "bernoulli":
            frame[name] = rng.binomial(1, param, size=n).astype(float)
        else:
            raise ValueError(f"unknown distribution kind: {kind}")
    return pd.DataFrame(frame)


def _target(df: pd.DataFrame, spec: dict[str, Any], rng: np.random.Generator) -> np.ndarray:
    z = float(spec.get("intercept", 0.0))
    for name in df.columns:
        z += spec["weights"][name] * df[name].values
    z = z + rng.normal(0.0, 0.35, size=len(df))
    prob = 1.0 / (1.0 + np.exp(-z))
    return (rng.random(len(df)) < prob).astype(int)


def _evaluate(pipeline, X_test: pd.DataFrame, y_test: np.ndarray) -> dict[str, Any]:
    pred = pipeline.predict(X_test)
    proba = pipeline.predict_proba(X_test)
    positive = int(np.where(pipeline.classes_ == 1)[0][0])
    return {
        "accuracy": round(float(accuracy_score(y_test, pred)), 4),
        "roc_auc": round(float(roc_auc_score(y_test, proba[:, positive])), 4),
        "precision": round(float(precision_score(y_test, pred, zero_division=0)), 4),
        "recall": round(float(recall_score(y_test, pred, zero_division=0)), 4),
        "f1": round(float(f1_score(y_test, pred, zero_division=0)), 4),
        "test_samples": int(len(y_test)),
    }


def _sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(8192), b""):
            digest.update(chunk)
    return digest.hexdigest()


def package_model(
    store: Path,
    prediction_type: str,
    version: str = "1.0",
    seed: int = 42,
    n_samples: int = 1200,
) -> dict[str, Any]:
    """Fit a demo model for one prediction type and write its package."""
    spec = MODEL_SPECS[prediction_type]
    rng = np.random.default_rng(seed)

    df = _feature_matrix(spec, n_samples, rng)
    y = _target(df, spec, rng)

    X_train, X_test, y_train, y_test = train_test_split(
        df, y, test_size=0.25, random_state=seed, stratify=y
    )

    pipeline = make_pipeline(StandardScaler(), LogisticRegression(max_iter=2000))
    pipeline.fit(X_train, y_train)

    metrics = _evaluate(pipeline, X_test, y_test)
    positive_class_index = int(np.where(pipeline.classes_ == 1)[0][0])

    model_dir = Path(store) / prediction_type / f"v{version}"
    model_dir.mkdir(parents=True, exist_ok=True)

    joblib_path = model_dir / "model.joblib"
    joblib.dump(pipeline, joblib_path)
    model_sha = _sha256(joblib_path)

    meta = {
        "prediction_type": prediction_type,
        "label": spec["label"],
        "model_version": version,
        "trained_at": datetime.now(timezone.utc).isoformat(),
        "data_source": "synthetic_demo",
        "model_file_sha256": model_sha,
        "positive_class_index": positive_class_index,
        "thresholds": dict(THRESHOLDS),
        "feature_count": int(len(df.columns)),
        "metrics": metrics,
    }
    (model_dir / "meta.json").write_text(
        json.dumps(meta, indent=2), encoding="utf-8"
    )
    (model_dir / "features.json").write_text(
        json.dumps(list(df.columns)), encoding="utf-8"
    )
    sample = {name: float(df[name].iloc[0]) for name in df.columns}
    (model_dir / "sample_features.json").write_text(
        json.dumps(sample, indent=2), encoding="utf-8"
    )

    return {
        "prediction_type": prediction_type,
        "version": version,
        "sha256": model_sha,
        "metrics": metrics,
    }


def update_registry(store: Path, entries: list[dict[str, Any]]) -> None:
    """Merge model entries into the version manifest, preserving schema fields."""
    store = Path(store)
    registry_file = store / "registry.json"
    if registry_file.is_file():
        try:
            data = json.loads(registry_file.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            data = {}
    else:
        data = {}
    if not isinstance(data, dict):
        data = {}
    data.setdefault("schema", 1)
    data.setdefault(
        "description", "Model version manifest for the AI decision-support service."
    )
    models = {
        e["prediction_type"]: e
        for e in data.get("models", [])
        if isinstance(e, dict) and e.get("prediction_type")
    }
    for entry in entries:
        models[entry["prediction_type"]] = entry
    data["models"] = [models[key] for key in sorted(models)]
    registry_file.parent.mkdir(parents=True, exist_ok=True)
    registry_file.write_text(json.dumps(data, indent=2) + "\n", encoding="utf-8")


def package_all(
    store: Path,
    version: str = "1.0",
    seed: int = 42,
    n_samples: int = 1200,
) -> list[dict[str, Any]]:
    """Package every prediction type and mark them ready in the manifest."""
    results = []
    for index, prediction_type in enumerate(MODEL_SPECS):
        results.append(
            package_model(
                store,
                prediction_type,
                version=version,
                seed=seed + index,
                n_samples=n_samples,
            )
        )
    entries = [
        {"prediction_type": r["prediction_type"], "version": version, "status": "ready"}
        for r in results
    ]
    update_registry(store, entries)
    return results
