"""FastAPI application for the AI decision-support service.

Layout:
  * GET  /health                 public probe (no auth) for the PHP health checks
  * GET  /v1/models              authenticated registry listing
  * POST /v1/predict/{type}      authenticated, Pydantic-validated decision support

The service listens only on 127.0.0.1 (see run.py). No CORS is enabled and no
static files are served, so browsers cannot call it directly.
"""

from __future__ import annotations

import logging
import time

from fastapi import Depends, FastAPI, Request
from fastapi.exceptions import RequestValidationError
from fastapi.responses import JSONResponse
from starlette.exceptions import HTTPException as StarletteHTTPException

from .config import SERVICE_NAME, SERVICE_VERSION, settings
from .errors import AIServiceError
from .logging_conf import setup_logging
from .schemas import (
    HealthResponse,
    ModelsListResponse,
    PredictionRequest,
    PredictionResponse,
    SymptomAssessRequest,
    SymptomAssessResponse,
)
from .security import require_api_key
from .services import ModelRegistry
from .services.symptom_matcher import assess

logger = logging.getLogger("ai-service")

_STARTED_AT = time.time()


def create_app() -> FastAPI:
    setup_logging(settings.log_dir)

    app = FastAPI(
        title=SERVICE_NAME,
        version=SERVICE_VERSION,
        docs_url=None,          # Swagger UI disabled: never expose interactively
        redoc_url=None,
        openapi_url="/v1/openapi.json" if settings.debug else None,
    )

    registry = ModelRegistry(settings.model_store, settings.registry_file)
    app.state.registry = registry

    # ------------------------------------------------------------------
    # Size guard (request-abuse protection)
    # ------------------------------------------------------------------
    @app.middleware("http")
    async def enforce_body_size(request: Request, call_next):
        length = request.headers.get("content-length")
        if length is not None and length.isdigit() and int(length) > settings.max_body_bytes:
            # Return directly: exceptions raised inside middleware bypass the
            # exception handlers below (Starlette catches them as 500).
            return JSONResponse(
                status_code=413,
                content={
                    "success": False,
                    "error": {
                        "code": "request_too_large",
                        "message": f"Request body exceeds {settings.max_body_bytes} bytes.",
                    },
                },
            )
        return await call_next(request)

    # ------------------------------------------------------------------
    # Access logging (never logs bodies, headers, or tokens)
    # ------------------------------------------------------------------
    @app.middleware("http")
    async def access_log(request: Request, call_next):
        started = time.perf_counter()
        response = await call_next(request)
        duration_ms = (time.perf_counter() - started) * 1000.0
        client = request.client.host if request.client else "?"
        logger.info(
            "access method=%s path=%s status=%d client=%s duration_ms=%.2f",
            request.method,
            request.url.path,
            response.status_code,
            client,
            duration_ms,
        )
        return response

    # ------------------------------------------------------------------
    # Structured error handlers
    # ------------------------------------------------------------------
    @app.exception_handler(RequestValidationError)
    async def validation_handler(_request: Request, exc: RequestValidationError):
        # 422 with a stable code; never echoes the offending values
        return JSONResponse(
            status_code=422,
            content={
                "success": False,
                "error": {
                    "code": "validation_error",
                    "message": "Request failed validation.",
                    "detail": _validator_summary(exc),
                },
            },
        )

    @app.exception_handler(AIServiceError)
    async def service_error_handler(_request: Request, exc: AIServiceError):
        logger.warning("service_error code=%s status=%s", exc.code, exc.status_code)
        return JSONResponse(status_code=exc.status_code, content=exc.to_envelope())

    @app.exception_handler(StarletteHTTPException)
    async def http_error_handler(_request: Request, exc: StarletteHTTPException):
        codes = {
            404: ("not_found", "Resource not found."),
            405: ("method_not_allowed", "Method not allowed."),
        }
        code, message = codes.get(exc.status_code, ("http_error", exc.detail))
        return JSONResponse(
            status_code=exc.status_code,
            content={"success": False, "error": {"code": code, "message": str(message)}},
        )

    @app.exception_handler(Exception)
    async def unhandled_handler(_request: Request, exc: Exception):
        logger.exception("unhandled_exception type=%s", type(exc).__name__)
        return JSONResponse(
            status_code=500,
            content={
                "success": False,
                "error": {"code": "internal_error", "message": "An unexpected error occurred."},
            },
        )

    # ------------------------------------------------------------------
    # Routes
    # ------------------------------------------------------------------
    @app.get("/health", response_model=HealthResponse)
    async def health() -> HealthResponse:
        models = [m.as_dict() for m in registry.list_models()]
        return HealthResponse(
            status="ok",
            service=SERVICE_NAME,
            version=SERVICE_VERSION,
            auth_enabled=settings.auth_enabled,
            uptime_seconds=int(time.time() - _STARTED_AT),
            model_registry=models,
        )

    @app.get("/v1/models", response_model=ModelsListResponse, dependencies=[Depends(require_api_key)])
    async def list_models() -> ModelsListResponse:
        models = [
            {
                "prediction_type": m.prediction_type,
                "version": m.version,
                "path": str(m.path) if m.path is not None else "",
                "sha256": None,
                "available": m.status == "ready" and m.path is not None,
            }
            for m in registry.list_models()
        ]
        return ModelsListResponse(models=models)

    @app.post(
        "/v1/predict/{prediction_type}",
        response_model=PredictionResponse,
        dependencies=[Depends(require_api_key)],
    )
    async def predict(prediction_type: str, body: PredictionRequest) -> PredictionResponse:
        if body.prediction_type != prediction_type:
            raise AIServiceError(
                "prediction_type in the URL and request body must match.",
                detail="Mismatch between path and body.",
            )

        model = registry.load(body.prediction_type)
        output = model.predict(body.features)
        return PredictionResponse(
            prediction_type=body.prediction_type,
            risk_level=output.risk_level,
            risk_score=output.risk_score,
            confidence=output.confidence,
            model_version=output.model_version,
        )

    @app.post(
        "/v1/symptoms/assess",
        response_model=SymptomAssessResponse,
        dependencies=[Depends(require_api_key)],
    )
    async def assess_symptoms(body: SymptomAssessRequest) -> SymptomAssessResponse:
        # Decision-support only: the symptom text is clinical data entered by
        # staff, never PII. The service stores nothing and never logs the body.
        payload = assess(body.symptoms_text)
        return SymptomAssessResponse(
            conditions=payload["conditions"],
            matched_symptoms=payload["matched_symptoms"],
            model_version=payload["model_version"],
        )

    return app


def _validator_summary(exc: RequestValidationError) -> list[dict]:
    """Compact validation summary: field + error type only. Never includes the
    submitted values (privacy) and never leaks file paths."""
    out = []
    for err in exc.errors():
        loc = ".".join(str(part) for part in err.get("loc", []) if part != "body")
        out.append({"field": loc, "type": str(err.get("type", "error"))})
    return out[:10]


app = create_app()