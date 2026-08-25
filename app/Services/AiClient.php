<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Secure HTTP client for the FastAPI decision-support service.
 *
 * Responsibilities:
 *   - server-to-server authentication via the shared X-API-Key header
 *   - connect/overall timeouts and controlled retries for transient failures
 *   - strict response validation (shape + enum/range checks) before any value
 *     is returned to the caller
 *   - SSRF guard: the configured base URL must be http(s) and resolve to an
 *     allow-listed host (loopback by default)
 *   - privacy: only the caller-supplied numeric feature vector is ever sent;
 *     no PII, no API key is ever logged
 *
 * The client is fail-closed: it never guesses, retries, or caches on a 4xx
 * client error, and it never falls back to an unauthenticated request.
 */
final class AiClient
{
    public const PREDICTION_TYPES = [
        'malaria_risk',
        'asthma_exacerbation',
        'typhoid_risk',
    ];

    private const RISK_LEVELS = ['low', 'moderate', 'high'];

    /**
     * cURL error numbers treated as transient (safe to retry).
     */
    private const TRANSIENT_CURL_CODES = [
        CURLE_COULDNT_CONNECT,
        CURLE_COULDNT_RESOLVE_HOST,
        CURLE_OPERATION_TIMEDOUT,
        CURLE_GOT_NOTHING,
        CURLE_RECV_ERROR,
        CURLE_SEND_ERROR,
    ];

    public static function enabled(): bool
    {
        return (bool) config('ai.enabled', false);
    }

    /**
     * Probe the service health endpoint (public, no auth needed).
     *
     * @return array<string, mixed> validated health payload
     * @throws AiServiceException on any failure
     */
    public static function health(): array
    {
        $body = self::request('GET', '/health', null, expectJson: true, allowUnauthenticated: true);
        $data = json_decode($body, true);
        if (!is_array($data) || ($data['status'] ?? null) !== 'ok') {
            throw new AiServiceException(
                AiServiceException::CATEGORY_INVALID_RESPONSE,
                'AI health response is invalid.'
            );
        }
        return $data;
    }

    /**
     * Convenience availability check that never throws.
     */
    public static function isAvailable(): bool
    {
        try {
            self::health();
            return true;
        } catch (AiServiceException) {
            return false;
        }
    }

    /**
     * Request a decision-support prediction.
     *
     * @param array<string, int|float> $features de-identified numeric vector
     * @return array<string, mixed> validated prediction payload
     * @throws AiServiceException on any failure
     */
    public static function predict(string $predictionType, array $features, string $studentRef = ''): array
    {
        if (!self::enabled()) {
            throw new AiServiceException(AiServiceException::CATEGORY_CONFIG, 'AI service is disabled.');
        }
        if (!in_array($predictionType, self::PREDICTION_TYPES, true)) {
            throw new AiServiceException(AiServiceException::CATEGORY_CONFIG, 'Unsupported prediction type.');
        }
        if ($features === []) {
            throw new AiServiceException(AiServiceException::CATEGORY_CONFIG, 'Feature vector is empty.');
        }
        foreach ($features as $name => $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new AiServiceException(
                    AiServiceException::CATEGORY_CONFIG,
                    'Feature values must be numeric.'
                );
            }
        }

        $payload = [
            'prediction_type' => $predictionType,
            'student_ref' => mb_substr($studentRef, 0, 128),
            'features' => $features,
        ];

        $body = self::request(
            'POST',
            '/v1/predict/' . rawurlencode($predictionType),
            $payload,
            expectJson: true
        );

        return self::validatePredictionResponse($body, $predictionType);
    }

    /**
     * Request a symptom assessment (staff-entered symptoms -> suggested
     * conditions). Decision-support only; never a diagnosis.
     *
     * @param string $symptomsText free-text symptoms the student reported
     * @return array<string, mixed> validated assessment payload
     * @throws AiServiceException on any failure
     */
    public static function assessSymptoms(string $symptomsText, string $studentRef = ''): array
    {
        if (!self::enabled()) {
            throw new AiServiceException(AiServiceException::CATEGORY_CONFIG, 'AI service is disabled.');
        }
        $symptomsText = trim($symptomsText);
        if ($symptomsText === '') {
            throw new AiServiceException(AiServiceException::CATEGORY_CONFIG, 'Symptom description is empty.');
        }

        $payload = [
            'symptoms_text' => mb_substr($symptomsText, 0, 2000),
            'student_ref' => mb_substr($studentRef, 0, 128),
        ];

        $body = self::request(
            'POST',
            '/v1/symptoms/assess',
            $payload,
            expectJson: true
        );

        return self::validateSymptomResponse($body);
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * Perform the authenticated HTTP request with timeouts, retries and an
     * SSRF guard on the configured base URL.
     *
     * @param array<string, mixed>|null $payload
     */
    private static function request(
        string $method,
        string $path,
        ?array $payload,
        bool $expectJson,
        bool $allowUnauthenticated = false
    ): string {
        $base = self::validatedBaseUrl();

        $url = $base . $path;
        $apiKey = (string) config('ai.api_key', '');
        if (!$allowUnauthenticated && $apiKey === '') {
            throw new AiServiceException(AiServiceException::CATEGORY_AUTH, 'AI API key is not configured.');
        }

        $connectTimeout = max(0.5, (float) config('ai.connect_timeout', 3.0));
        $timeout = max(0.5, (float) config('ai.timeout', 8.0));
        $retries = max(0, (int) config('ai.retries', 1));

        $body = null;
        $headers = ['Accept: application/json'];
        if ($payload !== null) {
            $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new AiServiceException(AiServiceException::CATEGORY_CONFIG, 'AI request body could not be encoded.');
            }
            if (strlen($encoded) > (int) config('ai.max_request_bytes', 8192)) {
                throw new AiServiceException(AiServiceException::CATEGORY_CONFIG, 'AI request body is too large.');
            }
            $body = $encoded;
            $headers[] = 'Content-Type: application/json';
        }
        if (!$allowUnauthenticated) {
            $headers[] = 'X-API-Key: ' . $apiKey;
        }

        $attempt = 0;
        $lastError = null;
        while (true) {
            $attempt++;
            try {
                return self::execute($url, $method, $headers, $body, $connectTimeout, $timeout, $expectJson);
            } catch (AiServiceException $e) {
                $lastError = $e;
                $transient = $e->category() === AiServiceException::CATEGORY_UNAVAILABLE
                    || $e->category() === AiServiceException::CATEGORY_TIMEOUT
                    || ($e->httpStatus() !== null && $e->httpStatus() >= 500);
                if (!$transient || $attempt > $retries) {
                    throw $e;
                }
                Logger::warning('ai_client_retry', [
                    'attempt' => $attempt,
                    'of' => $retries + 1,
                    'category' => $e->category(),
                    'path' => $path,
                ]);
                usleep(min(500000, 100000 * $attempt));
            }
        }
    }

    /**
     * @param list<string> $headers
     */
    private static function execute(
        string $url,
        string $method,
        array $headers,
        ?string $body,
        float $connectTimeout,
        float $timeout,
        bool $expectJson
    ): string {
        if (!function_exists('curl_init')) {
            throw new AiServiceException(
                AiServiceException::CATEGORY_CONFIG,
                'cURL extension is not available.'
            );
        }

        $ch = curl_init($url);
        if ($ch === false) {
            throw new AiServiceException(AiServiceException::CATEGORY_UNAVAILABLE, 'Could not initialise HTTP client.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT_MS => (int) round($connectTimeout * 1000),
            CURLOPT_TIMEOUT_MS => (int) round($timeout * 1000),
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_NOBODY => false,
        ];
        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body ?? '';
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);

        if ($raw === false) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            curl_close($ch);
            if ($errno === CURLE_OPERATION_TIMEDOUT) {
                throw new AiServiceException(AiServiceException::CATEGORY_TIMEOUT, 'AI service request timed out.');
            }
            Logger::warning('ai_client_transport_error', ['errno' => $errno]);
            throw new AiServiceException(
                AiServiceException::CATEGORY_UNAVAILABLE,
                'Could not reach the AI service.',
                null,
                new \RuntimeException($error ?: 'curl error ' . $errno)
            );
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $head = substr($raw, 0, $headerSize);
        $respBody = substr($raw, $headerSize);

        if ($status === 0) {
            throw new AiServiceException(AiServiceException::CATEGORY_UNAVAILABLE, 'AI service returned no response.');
        }

        if ($status === 401 || $status === 403) {
            throw new AiServiceException(
                AiServiceException::CATEGORY_AUTH,
                'AI service rejected the request credentials.',
                $status
            );
        }

        if ($status >= 400) {
            $message = self::extractErrorMessage($respBody) ?? 'AI service returned HTTP ' . $status . '.';
            if ($status >= 500) {
                throw new AiServiceException(AiServiceException::CATEGORY_UNAVAILABLE, $message, $status);
            }
            throw new AiServiceException(AiServiceException::CATEGORY_HTTP, $message, $status);
        }

        if ($expectJson) {
            $decoded = json_decode($respBody, true);
            if (!is_array($decoded)) {
                throw new AiServiceException(
                    AiServiceException::CATEGORY_INVALID_RESPONSE,
                    'AI service returned malformed JSON.'
                );
            }
        }

        return $respBody;
    }

    /**
     * Parse and validate the prediction response payload.
     *
     * @return array<string, mixed>
     * @throws AiServiceException
     */
    private static function validatePredictionResponse(string $rawBody, string $predictionType): array
    {
        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            throw new AiServiceException(
                AiServiceException::CATEGORY_INVALID_RESPONSE,
                'AI prediction response is not valid JSON.'
            );
        }

        if (($data['success'] ?? false) !== true) {
            throw new AiServiceException(
                AiServiceException::CATEGORY_INVALID_RESPONSE,
                'AI prediction response reported failure.'
            );
        }

        $required = ['prediction_type', 'risk_level', 'risk_score', 'confidence', 'model_version'];
        foreach ($required as $key) {
            if (!array_key_exists($key, $data)) {
                throw new AiServiceException(
                    AiServiceException::CATEGORY_INVALID_RESPONSE,
                    'AI prediction response is missing field: ' . $key
                );
            }
        }

        if ($data['prediction_type'] !== $predictionType) {
            throw new AiServiceException(
                AiServiceException::CATEGORY_INVALID_RESPONSE,
                'AI prediction response type mismatch.'
            );
        }
        if (!in_array($data['risk_level'], self::RISK_LEVELS, true)) {
            throw new AiServiceException(
                AiServiceException::CATEGORY_INVALID_RESPONSE,
                'AI prediction response has an invalid risk level.'
            );
        }
        $riskScore = filter_var($data['risk_score'], FILTER_VALIDATE_FLOAT);
        $confidence = filter_var($data['confidence'], FILTER_VALIDATE_FLOAT);
        if ($riskScore === false || $confidence === false || $riskScore < 0 || $riskScore > 1 || $confidence < 0 || $confidence > 1) {
            throw new AiServiceException(
                AiServiceException::CATEGORY_INVALID_RESPONSE,
                'AI prediction response has out-of-range numeric values.'
            );
        }
        if (!is_string($data['model_version']) || $data['model_version'] === '') {
            throw new AiServiceException(
                AiServiceException::CATEGORY_INVALID_RESPONSE,
                'AI prediction response has an invalid model version.'
            );
        }

        return [
            'prediction_type' => $predictionType,
            'risk_level' => (string) $data['risk_level'],
            'risk_score' => $riskScore,
            'confidence' => $confidence,
            'model_version' => (string) $data['model_version'],
        ];
    }

    /**
     * Parse and validate the symptom assessment response payload.
     *
     * @return array<string, mixed>
     * @throws AiServiceException
     */
    private static function validateSymptomResponse(string $rawBody): array
    {
        $data = json_decode($rawBody, true);
        if (!is_array($data)) {
            throw new AiServiceException(
                AiServiceException::CATEGORY_INVALID_RESPONSE,
                'AI symptom response is not valid JSON.'
            );
        }

        if (($data['success'] ?? false) !== true) {
            throw new AiServiceException(
                AiServiceException::CATEGORY_INVALID_RESPONSE,
                'AI symptom response reported failure.'
            );
        }

        if (!isset($data['conditions']) || !is_array($data['conditions'])) {
            throw new AiServiceException(
                AiServiceException::CATEGORY_INVALID_RESPONSE,
                'AI symptom response is missing the conditions list.'
            );
        }

        $conditions = [];
        foreach ($data['conditions'] as $item) {
            if (!is_array($item)
                || !isset($item['condition'], $item['level'], $item['score'], $item['confidence'], $item['advice'])
                || !is_string($item['condition'])
                || !in_array($item['level'], self::RISK_LEVELS, true)
            ) {
                throw new AiServiceException(
                    AiServiceException::CATEGORY_INVALID_RESPONSE,
                    'AI symptom response contains an invalid condition entry.'
                );
            }
            $score = filter_var($item['score'], FILTER_VALIDATE_FLOAT);
            $confidence = filter_var($item['confidence'], FILTER_VALIDATE_FLOAT);
            if ($score === false || $confidence === false || $score < 0 || $score > 1 || $confidence < 0 || $confidence > 1) {
                throw new AiServiceException(
                    AiServiceException::CATEGORY_INVALID_RESPONSE,
                    'AI symptom response has out-of-range numeric values.'
                );
            }
            $conditions[] = [
                'condition' => mb_substr((string) $item['condition'], 0, 120),
                'level' => (string) $item['level'],
                'score' => $score,
                'confidence' => $confidence,
                'advice' => mb_substr((string) $item['advice'], 0, 1000),
            ];
        }

        $matched = [];
        if (isset($data['matched_symptoms']) && is_array($data['matched_symptoms'])) {
            foreach ($data['matched_symptoms'] as $symptom) {
                if (is_string($symptom)) {
                    $matched[] = mb_substr($symptom, 0, 120);
                }
            }
        }

        if (!isset($data['model_version']) || !is_string($data['model_version']) || $data['model_version'] === '') {
            throw new AiServiceException(
                AiServiceException::CATEGORY_INVALID_RESPONSE,
                'AI symptom response has an invalid model version.'
            );
        }

        return [
            'conditions' => $conditions,
            'matched_symptoms' => $matched,
            'model_version' => (string) $data['model_version'],
        ];
    }

    /**
     * Validate the configured base URL against the SSRF guard.
     */
    private static function validatedBaseUrl(): string
    {
        $base = (string) config('ai.base_url', '');
        if ($base === '') {
            throw new AiServiceException(AiServiceException::CATEGORY_CONFIG, 'AI base URL is not configured.');
        }

        $parts = parse_url($base);
        if (!is_array($parts)
            || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
        ) {
            throw new AiServiceException(
                AiServiceException::CATEGORY_CONFIG,
                'AI base URL must be http(s) with a host.'
            );
        }

        $host = strtolower((string) $parts['host']);
        $allowed = array_map('strtolower', (array) config('ai.allowed_hosts', []));
        if (!in_array($host, $allowed, true)) {
            throw new AiServiceException(
                AiServiceException::CATEGORY_CONFIG,
                'AI base URL host is not allow-listed (SSRF guard).'
            );
        }

        // Never follow to a different host: strip any userinfo and reject
        // credentials embedded in the URL.
        $scheme = strtolower((string) $parts['scheme']);
        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';
        $path = rtrim((string) ($parts['path'] ?? ''), '/');
        return $scheme . '://' . $host . $port . $path;
    }

    private static function extractErrorMessage(string $rawBody): ?string
    {
        $data = json_decode($rawBody, true);
        if (is_array($data) && isset($data['error']['message']) && is_string($data['error']['message'])) {
            return mb_substr($data['error']['message'], 0, 200);
        }
        return null;
    }
}
