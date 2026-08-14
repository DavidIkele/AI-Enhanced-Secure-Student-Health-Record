"""Authentication for server-to-server calls.

The PHP backend authenticates with a shared API key supplied through the
AI_API_KEY environment variable. The key is compared with a constant-time
comparison. Fail-closed: if no key is configured, protected endpoints are
unavailable so that the service can never be called without credentials.
"""

from __future__ import annotations

import hmac

from fastapi import Depends, Request
from fastapi.security import HTTPAuthorizationCredentials, HTTPBearer

from .errors import AuthenticationError
from .config import settings

_bearer = HTTPBearer(auto_error=False)


def require_api_key(
    request: Request,
    credentials: HTTPAuthorizationCredentials | None = Depends(_bearer),
) -> None:
    credential = request.headers.get("X-API-Key")
    if credential is None and credentials is not None:
        credential = credentials.credentials

    if credential is None:
        raise AuthenticationError("Missing API key.")

    configured = settings.api_key
    if not configured:
        raise AuthenticationError("Service has no API key configured; access denied.")

    if not hmac.compare_digest(credential, configured):
        raise AuthenticationError("Invalid API key.")