"""Train and package demo decision-support models.

Writes model artifacts for every prediction type into the configured model
store and marks them ``ready`` in ``registry.json``.

Usage (from the ai-service directory):
    .venv\\Scripts\\python scripts\\train_models.py
    .venv\\Scripts\\python scripts\\train_models.py --version 1.1 --samples 2000

The generated data is synthetic and reproducible. Models are for development
and demonstration only — they are not clinical.
"""

from __future__ import annotations

import argparse
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent.parent))

from app.config import settings
from app.services.packaging import package_all


def main() -> None:
    parser = argparse.ArgumentParser(description="Train demo models for the AI service.")
    parser.add_argument("--version", default="1.0", help="version tag for the packages")
    parser.add_argument("--samples", type=int, default=1200, help="samples per model")
    parser.add_argument("--seed", type=int, default=42, help="random seed (deterministic)")
    args = parser.parse_args()

    store = Path(settings.model_store)
    results = package_all(
        store,
        version=args.version,
        seed=args.seed,
        n_samples=args.samples,
    )

    for result in results:
        metrics = result["metrics"]
        print(
            f"{result['prediction_type']:>20} v{result['version']} ready "
            f"(accuracy={metrics['accuracy']:.3f} roc_auc={metrics['roc_auc']:.3f} "
            f"f1={metrics['f1']:.3f}) sha256={result['sha256'][:12]}..."
        )
    print(f"\nPackaged {len(results)} model(s) into {store}")
    print("Run the service with: python run.py")


if __name__ == "__main__":
    main()
