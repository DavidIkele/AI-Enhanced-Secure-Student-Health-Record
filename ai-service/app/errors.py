"""Structured, privacy-aware errors for the AI service.

Every error carries a stable machine-readable code, a short user-facing
message, and an optional detail. The FastAPI exception handlers in main.py map
these to the HTTP status code and the canonical error envelope:

    {"success": false, "error": {"code": "...", "message": "...", "detail": ...}}

Stack traces are intentionally never included in responses. Sensitive values
(passwords, keys, tokens) must never appear in messages.
"""

from __future__ import annotations


class AIServiceError(Exception):
    """Base class for all expected service errors."""

    status_code = 500
    code = "internal_error"

    def __init__(self, message: str, detail=None) -> None:
        super().__init__(message)
        self.message = message
        self.detail = detail

    def to_envelope(self) -> dict:
        error = {"code": self.code, "message": self.message}
        if self.detail is not None:
            error["detail"] = self.detail
        return {"success": False, "error": error}


class AuthenticationError(AIServiceError):
    status_code = 401
    code = "unauthorized"

    def __init__(self, message: str = "Missing or invalid API key.") -> None:
        super().__init__(message)


class ModelNotAvailableError(AIServiceError):
    status_code = 503
    code = "model_not_available"

    def __init__(self, prediction_type: str, detail=None) -> None:
        super().__init__(
            f"No trained model is available for '{prediction_type}'.",
            detail=detail,
        )


class ModelLoadError(AIServiceError):
    status_code = 503
    code = "model_load_failed"

    def __init__(self, prediction_type: str, detail=None) -> None:
        super().__init__(
            f"The model for '{prediction_type}' could not be loaded.",
            detail=detail,
        )


class ModelInputError(AIServiceError):
    status_code = 422
    code = "model_input_error"

    def __init__(self, prediction_type: str, detail=None) -> None:
        super().__init__(
            f"Input features do not match the trained model for '{prediction_type}'.",
            detail=detail,
        )


class RequestTooLargeError(AIServiceError):
    status_code = 413
    code = "request_too_large"


class MethodNotAllowedError(AIServiceError):
    status_code = 405
    code = "method_not_allowed"