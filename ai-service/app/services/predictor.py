"""Loaded decision-support model wrapper (PROMPT 9).

``TrainedModel`` wraps a fitted scikit-learn pipeline plus the sidecar metadata
that describes it (ordered feature names, risk thresholds, positive class).
Instances are produced only by ``ModelRegistry.load``; they are never built
from untrusted input.

Prediction output is intentionally decision-support only: a bounded risk score,
a coarse risk level, and a confidence value. It is never a diagnosis.
"""

from __future__ import annotations

import math
from dataclasses import dataclass
from typing import Any

import pandas as pd

from ..errors import ModelInputError

RISK_LEVELS = ("low", "moderate", "high")
DEFAULT_THRESHOLDS = {"low": 0.33, "high": 0.66}
MAX_FEATURE_MAGNITUDE = 1e6


@dataclass(frozen=True)
class PredictionOutput:
    """Bounded, de-identified decision-support output."""

    prediction_type: str
    risk_level: str
    risk_score: float
    confidence: float
    model_version: str


class TrainedModel:
    """A deserialized model handle that can score one feature vector."""

    def __init__(
        self,
        *,
        prediction_type: str,
        version: str,
        pipeline: Any,
        features: list[str],
        thresholds: dict[str, float] | None = None,
        positive_class_index: int = 1,
        meta: dict[str, Any] | None = None,
    ) -> None:
        self.prediction_type = prediction_type
        self.version = str(version)
        self._pipeline = pipeline
        self._features = tuple(features)
        self._thresholds: dict[str, float] = {
            **DEFAULT_THRESHOLDS,
            **(thresholds or {}),
        }
        self._positive_class_index = int(positive_class_index)
        self._meta = dict(meta or {})

    @property
    def model_version(self) -> str:
        return self.version

    @property
    def features(self) -> tuple[str, ...]:
        return self._features

    def predict(self, features: dict[str, float]) -> PredictionOutput:
        """Score one de-identified feature vector.

        Feature names are matched strictly against the training feature list:
        a missing or unexpected feature is rejected (fail-closed) instead of
        being silently imputed.
        """
        self._validate_features(features)
        row = pd.DataFrame(
            [{name: float(features[name]) for name in self._features}],
            columns=list(self._features),
        )
        proba = self._pipeline.predict_proba(row)[0]
        score = float(proba[self._positive_class_index])
        confidence = float(proba.max())
        return PredictionOutput(
            prediction_type=self.prediction_type,
            risk_level=self._level_for(score),
            risk_score=round(score, 4),
            confidence=round(confidence, 4),
            model_version=self.version,
        )

    # ------------------------------------------------------------------
    # Internals
    # ------------------------------------------------------------------
    def _validate_features(self, features: dict[str, float]) -> None:
        if not isinstance(features, dict):
            raise ModelInputError(self.prediction_type, detail="features must be an object")

        missing = sorted(set(self._features) - set(features))
        extra = sorted(set(features) - set(self._features))
        if missing or extra:
            detail = []
            if missing:
                detail.append("missing: " + ", ".join(missing))
            if extra:
                detail.append("unexpected: " + ", ".join(extra))
            raise ModelInputError(self.prediction_type, detail="; ".join(detail))

        for name in self._features:
            try:
                numeric = float(features[name])
            except (TypeError, ValueError):
                raise ModelInputError(
                    self.prediction_type, detail=f"feature '{name}' is not numeric"
                )
            if not math.isfinite(numeric) or abs(numeric) > MAX_FEATURE_MAGNITUDE:
                raise ModelInputError(
                    self.prediction_type, detail=f"feature '{name}' is out of range"
                )

    def _level_for(self, score: float) -> str:
        if score < self._thresholds.get("low", DEFAULT_THRESHOLDS["low"]):
            return "low"
        if score < self._thresholds.get("high", DEFAULT_THRESHOLDS["high"]):
            return "moderate"
        return "high"
