# AI decision-support service (FastAPI, loopback-bound in run.py).
# Model artifacts are gitignored, so the demo models are re-trained at
# build time from deterministic synthetic data (scripts/train_models.py).
FROM python:3.12-slim

WORKDIR /srv/ai

# Install dependencies first for better layer caching.
COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

# Application code + training script.
COPY app ./app
COPY scripts ./scripts
COPY run.py ./

# Train + package the demo models (deterministic, seed=42) and verify the
# registry is ready before the image is considered built.
RUN python scripts/train_models.py \
    && python - <<'PY'
import json
from app.config import settings
m = json.loads((settings.registry_file).read_text())
assert all(e.get("status") == "ready" for e in m.get("models", [])), "registry not ready"
PY

# Runtime credentials come from the environment (AI_API_KEY); never baked in.
EXPOSE 8000
CMD ["python", "run.py"]