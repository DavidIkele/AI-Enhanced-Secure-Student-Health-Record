"""AI decision-support service for the Student Health Record System.

The service is built to run privately on 127.0.0.1 only. The PHP backend is the
only intended caller. The browser never talks to this service.

Security posture :
  * all endpoints except GET /health require a shared API key
  * fail-closed: no API key configured => protected endpoints are unavailable
  * request bodies are never logged (they may contain health-derived features)
  * structured error JSON; no stack traces or internal paths are returned
"""

__version__ = "0.1.0"