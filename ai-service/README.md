# AI Decision-Support Service

Separate Python (3.12+) FastAPI service for the Student Health Record System.
Provides the model-version registry and prediction API. **Decision support only
— never a medical diagnosis.**

## Security model (PROMPT 8)
- Binds to `127.0.0.1` only. Never expose it publicly.
- Every endpoint except `GET /health` requires a shared `AI_API_KEY`
  (`X-API-Key` header or `Authorization: Bearer`). Fail-closed when unset.
- No CORS, no docs UI, no static files → browsers cannot call it directly.
- Request bodies are never logged; auth headers/secrets never logged.
- Structured error JSON; stack traces and paths never returned to callers.
- Max body size enforced (also: see `AI_MAX_BODY_BYTES`).

## Privacy
PHP sends only de-identified numeric feature vectors (`student_ref` is an
opaque id chosen by PHP and must never be PII). No health identities are
stored or logged here.

## Quick start
```
cd ai-service
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt
# set a strong key, e.g.
set AI_API_KEY=<generated>
python run.py
```

## Endpoints
| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET | `/health` | no | probe used by PHP availability checks |
| GET | `/v1/models` | yes | model-version registry |
| POST | `/v1/predict/{type}` | yes | decision-support prediction |
| POST | `/v1/symptoms/assess` | yes | symptom-to-condition suggestions (staff) |

### Symptom assessment (`POST /v1/symptoms/assess`)
The doctor/nurse enters the symptoms a student described as free text
(`symptoms_text`, max 2000 chars). The matcher scores the text against a
curated knowledge base of common university-health-centre conditions and
returns ranked suggestions (`condition`, `level`, `score`, `confidence`,
`advice`) plus the matched symptom terms. Output is decision-support only —
never a diagnosis. No PII is sent or stored.

## Model layout (PROMPT 9)
```
models/
  registry.json                  # version manifest (source of truth)
  <prediction_type>/v<version>/
    model.joblib                 # fitted sklearn pipeline
    meta.json                    # metrics, trained_at, thresholds, sha256
    features.json                # ordered feature names used at training
    sample_features.json         # example input frame (de-identified)
```

## Trained models (PROMPT 9)

The service ships with demo pipelines fitted on deterministic synthetic data
(documented in `app/services/packaging.py`). Rebuild or re-train them with:

```
.venv\Scripts\python scripts\train_models.py            # all three, version 1.0
.venv\Scripts\python scripts\train_models.py --version 1.1 --samples 2000
```

Each run repackages `malaria_risk`, `asthma_exacerbation` and `typhoid_risk`,
records metrics + a SHA-256 of `model.joblib` in `meta.json`, and flips the
manifest entries to `ready`. Model artifacts are gitignored; only the manifest
is committed.

### Loading & prediction pipeline

- `ModelRegistry.load(type)` verifies the manifest is `ready`, re-checks the
  model file SHA-256 against the value recorded at packaging time, requires
  `meta.json` + `features.json`, and deserializes the joblib pipeline. Any
  failure is a structured `model_load_failed` (503) — never a stack trace.
- Feature names are matched **strictly** against `features.json`: a missing or
  unexpected feature is a structured `model_input_error` (422), never silently
  imputed.
- Output is a bounded `risk_score` (probability), a coarse `risk_level`
  (`low`/`moderate`/`high` via `meta.json` thresholds), and `confidence`.
  Decision-support only — never a diagnosis.

## Tests
```
.venv\Scripts\activate
pytest
```
Covers startup/health, validation (malformed/NaN/unknown/big bodies), auth
(missing/invalid/bearer), model-unavailable loading failure, real model
load + prediction, feature-mismatch rejection, checksum/deserialization
failure, and log redaction.