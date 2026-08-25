# AI Service Setup Guide

The AI service is a separate **Python 3.12+ FastAPI** process that provides
decision-support predictions only. It is **not** a medical diagnostic device.

> Privacy contract: PHP sends only de-identified numeric feature vectors
> (`student_ref` is an opaque id chosen by PHP and must never be PII). No
> health identities are stored or logged by the service.

## Security model

- Binds to `127.0.0.1` only. Never change `AI_HOST` to a public interface.
- Every endpoint except `GET /health` requires the shared `AI_API_KEY`
  (`X-API-Key` header or `Authorization: Bearer`). Fail-closed when unset.
- No CORS, no docs UI, no static files — browsers cannot call it directly.
- Request bodies / auth headers are never logged. Structured error JSON only;
  stack traces and paths never returned to callers.
- Max body size enforced (`AI_MAX_BODY_BYTES`).

## 1. Create a virtual environment

```powershell
cd ai-service
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
```

## 2. Configure

Copy `ai-service\.env.example` to `ai-service\.env` and set a strong key:

```powershell
# generate one, e.g.
python -c "import secrets; print(secrets.token_urlsafe(32))"
```

Edit `.env`:

```
AI_API_KEY=<generated value>
APP_ENV=production
APP_DEBUG=false
AI_HOST=127.0.0.1
AI_PORT=8000
AI_MODEL_STORE=./models
AI_LOG_DIR=./logs
AI_MAX_BODY_BYTES=65536
```

The service **must** be started with the same `AI_API_KEY` that is set in the
root `.env` (`AI_API_KEY` matches the PHP client's `AI_API_KEY`).

## 3. Start the service

```powershell
cd ai-service
.venv\Scripts\python run.py
```

On start it prints the loopback binding, e.g. `binding to 127.0.0.1:8000`.

## 4. Endpoints

| Method | Path | Auth | Purpose |
|---|---|---|---|
| GET | `/health` | no | Probe used by PHP availability checks |
| GET | `/v1/models` | yes | Model-version registry |
| POST | `/v1/predict/{type}` | yes | Decision-support prediction |

## 5. Model layout

```
models/
  registry.json                  # version manifest (source of truth)
  <prediction_type>/v<version>/
    model.joblib                 # fitted sklearn pipeline
    meta.json                    # metrics, trained_at, thresholds, sha256
    features.json                # ordered feature names used at training
    sample_features.json         # example input frame (de-identified)
```

Ships with demo pipelines fitted on deterministic synthetic data
(`app/services/packaging.py`). Rebuild or re-train:

```powershell
.venv\Scripts\python scripts\train_models.py            # all three, version 1.0
.venv\Scripts\python scripts\train_models.py --version 1.1 --samples 2000
```

Each run repackages `malaria_risk`, `asthma_exacerbation` and `typhoid_risk`,
records metrics + a SHA-256 of `model.joblib` in `meta.json`, and flips the
manifest entries to `ready`. Model artifacts are gitignored; only the manifest
is committed.

### Loading & prediction pipeline

- `ModelRegistry.load(type)` verifies the manifest is `ready`, re-checks the
  model file SHA-256 against the value recorded at packaging, requires
  `meta.json` + `features.json`, and deserializes the joblib pipeline. Any
  failure is a structured `model_load_failed` (503) — never a stack trace.
- Feature names matched strictly against `features.json`: a missing /
  unexpected feature is a structured `model_input_error` (422), never silently
  imputed.
- Output: bounded `risk_score` (probability), coarse `risk_level`
  (`low`/`moderate`/`high` via `meta.json` thresholds), and `confidence`.
  Decision-support only — never a diagnosis.

## 6. Run the service tests

```powershell
cd ai-service
.venv\Scripts\activate
pytest
```

Covers startup/health, validation (malformed/NaN/unknown/big bodies), auth
(missing/invalid/bearer), model-unavailable loading failure, real model
load + prediction, feature-mismatch rejection, checksum/deserialization
failure, and log redaction.

## 7. Disable / enable

- To run the app without AI: set `AI_ENABLED=false` in the root `.env`.
  Every AI call degrades gracefully (prediction marked failed, message:
  "Could not reach the AI service.").
- To enable: start the service and set `AI_ENABLED=true`. Verify from the
  administrator `system/health` screen that the AI service shows available.

## 8. Supervision notes

- Keep the service running under a process supervisor in production (task
  scheduler / Windows Service / systemd). It must be restarted if it exits.
- Schedule model re-training off-peak and restart the service after changing
  `models/`.