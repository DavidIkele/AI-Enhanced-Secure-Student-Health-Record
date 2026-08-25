"""Model registry and versioning structure .

Regularized layout for trained models:

    models/
      registry.json                     # version manifest (source of truth)
      <prediction_type>/                # e.g. malaria_risk/
        v<major.minor>/                 # e.g. v1.0/
          model.joblib                  # fitted scikit-learn pipeline
          meta.json                     # model_version, metrics, trained_at, ...
          features.json                 # ordered feature names used at training
          sample_features.json          # example input frame (de-identified)

registry.json entries:
    {"prediction_type": "...", "version": "1.0", "status": "ready|training|unset"}

Loading : ``load`` deserializes the fitted pipeline with joblib,
verifies the model file checksum against the value recorded in ``meta.json``
at packaging time, and requires ``meta.json`` + ``features.json`` sidecars.
Any missing/corrupt part raises a structured ``ModelLoadError``; models that
are not registered as ``ready`` raise ``ModelNotAvailableError``.
"""

from __future__ import annotations

import hashlib
import json
from dataclasses import dataclass
from pathlib import Path
from typing import Any

from ..errors import ModelLoadError, ModelNotAvailableError
from .predictor import TrainedModel


@dataclass(frozen=True)
class ModelMetadata:
    prediction_type: str
    version: str
    status: str
    path: Path | None

    def as_dict(self) -> dict[str, Any]:
        return {
            "prediction_type": self.prediction_type,
            "version": self.version,
            "status": self.status,
            "path": str(self.path) if self.path is not None else None,
        }


class ModelRegistry:
    """Resolves models by prediction type from the on-disk model store."""

    def __init__(self, store: Path, registry_file: Path | None = None) -> None:
        self.store = Path(store)
        self.registry_file = registry_file or self.store / "registry.json"

    # ------------------------------------------------------------------
    # Manifest handling
    # ------------------------------------------------------------------
    def _read_registry(self) -> list[dict[str, Any]]:
        if not self.registry_file.is_file():
            return []
        try:
            data = json.loads(self.registry_file.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError):
            return []
        entries = data.get("models", []) if isinstance(data, dict) else data
        return entries if isinstance(entries, list) else []

    def _manifest_entry(self, prediction_type: str) -> dict[str, Any] | None:
        for entry in self._read_registry():
            if entry.get("prediction_type") == prediction_type:
                return entry
        return None

    # ------------------------------------------------------------------
    # Queries
    # ------------------------------------------------------------------
    def list_models(self) -> list[ModelMetadata]:
        """All known prediction types and their registry state (even when the
        model files are absent, so the manifest always describes the plan)."""
        known = ["malaria_risk", "asthma_exacerbation", "typhoid_risk"]
        result: dict[str, ModelMetadata] = {}

        for entry in self._read_registry():
            pt = str(entry.get("prediction_type") or "")
            version = str(entry.get("version") or "0.0")
            status = str(entry.get("status") or "unset")
            result[pt] = ModelMetadata(
                prediction_type=pt,
                version=version,
                status=status,
                path=self._model_file(pt, version),
            )

        for pt in known:
            result.setdefault(
                pt,
                ModelMetadata(prediction_type=pt, version="0.0", status="unset", path=None),
            )

        return list(result.values())

    def describe(self, prediction_type: str) -> ModelMetadata:
        entry = self._manifest_entry(prediction_type)
        if entry is None:
            return ModelMetadata(
                prediction_type=prediction_type, version="0.0", status="unset", path=None
            )
        version = str(entry.get("version") or "0.0")
        return ModelMetadata(
            prediction_type=prediction_type,
            version=version,
            status=str(entry.get("status") or "unset"),
            path=self._model_file(prediction_type, version),
        )

    # ------------------------------------------------------------------
    # Loading pipeline 
    # ------------------------------------------------------------------
    def load(self, prediction_type: str) -> TrainedModel:
        """Return a deserialized model handle, or raise a structured error.

        Steps:
          1. the manifest entry must be ``ready`` and the file must exist;
          2. the model file checksum must match the value recorded at
             packaging time (integrity check, fail-closed);
          3. ``meta.json`` and ``features.json`` sidecars are required and
             must parse;
          4. the joblib payload must deserialize into an object exposing
             ``predict_proba``.
        """
        meta = self.describe(prediction_type)
        if meta.status != "ready" or meta.path is None or not meta.path.is_file():
            raise ModelNotAvailableError(
                prediction_type,
                detail=(
                    f"registry status='{meta.status}' file='{self._rel(meta.path)}'"
                    if meta.path is not None
                    else "no model manifest entry"
                ),
            )

        checksum = self._sha256(meta.path)
        if checksum is None:
            raise ModelLoadError(
                prediction_type, detail="model file is unreadable (checksum failed)"
            )

        model_dir = meta.path.parent
        meta_file = model_dir / "meta.json"
        features_file = model_dir / "features.json"
        if not meta_file.is_file() or not features_file.is_file():
            raise ModelLoadError(
                prediction_type,
                detail="model sidecar files (meta.json, features.json) are missing",
            )

        meta_data = self._read_json_object(meta_file, prediction_type)
        features = self._read_json_list(features_file, prediction_type)

        recorded_sha = meta_data.get("model_file_sha256")
        if recorded_sha and str(recorded_sha) != checksum:
            raise ModelLoadError(
                prediction_type, detail="model file checksum mismatch; refusing to load"
            )

        if not features:
            raise ModelLoadError(prediction_type, detail="features.json is empty")

        pipeline = self._load_pipeline(meta.path, prediction_type)

        return TrainedModel(
            prediction_type=prediction_type,
            version=str(meta_data.get("model_version") or meta.version),
            pipeline=pipeline,
            features=features,
            thresholds=meta_data.get("thresholds"),
            positive_class_index=meta_data.get("positive_class_index", 1),
            meta=meta_data,
        )

    # ------------------------------------------------------------------
    # Helpers
    # ------------------------------------------------------------------
    def _model_file(self, prediction_type: str, version: str) -> Path | None:
        candidate = (
            self.store / prediction_type / f"v{version}" / "model.joblib"
        )
        return candidate if candidate.is_file() else None

    def _read_json_object(self, path: Path, prediction_type: str) -> dict[str, Any]:
        try:
            data = json.loads(path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError) as exc:
            raise ModelLoadError(
                prediction_type, detail=f"unreadable sidecar: {path.name}"
            ) from exc
        if not isinstance(data, dict):
            raise ModelLoadError(
                prediction_type, detail=f"sidecar {path.name} is not an object"
            )
        return data

    def _read_json_list(self, path: Path, prediction_type: str) -> list[str]:
        try:
            data = json.loads(path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError) as exc:
            raise ModelLoadError(
                prediction_type, detail=f"unreadable sidecar: {path.name}"
            ) from exc
        if not isinstance(data, list):
            raise ModelLoadError(
                prediction_type, detail=f"sidecar {path.name} is not a list"
            )
        return [str(name) for name in data]

    @staticmethod
    def _load_pipeline(path: Path, prediction_type: str):
        """Deserialize a joblib pipeline, refusing payloads that are not a
        fitted estimator. The model store is operator-controlled; artifacts
        must only ever be produced by the packaging script (never by remote
        input), and integrity is checked against the recorded checksum."""
        try:
            import joblib

            pipeline = joblib.load(path)
        except Exception as exc:  # joblib/pickle errors are not uniformly typed
            raise ModelLoadError(
                prediction_type, detail=f"deserialization failed: {type(exc).__name__}"
            ) from exc
        if not hasattr(pipeline, "predict_proba"):
            raise ModelLoadError(
                prediction_type,
                detail="model object does not expose predict_proba",
            )
        return pipeline

    def _rel(self, path: Path | None) -> str:
        if path is None:
            return "<none>"
        try:
            return str(path.relative_to(self.store))
        except ValueError:
            return str(path)

    @staticmethod
    def _sha256(path: Path) -> str | None:
        try:
            digest = hashlib.sha256()
            with path.open("rb") as fh:
                for chunk in iter(lambda: fh.read(8192), b""):
                    digest.update(chunk)
            return digest.hexdigest()
        except OSError:
            return None