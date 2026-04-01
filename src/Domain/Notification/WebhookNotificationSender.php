<?php
declare(strict_types=1);

namespace App\Domain\Notification;

use RuntimeException;
use InvalidArgumentException;

final class WebhookNotificationSender
{
    /**
     * @param array<string, mixed> $payload
     * @return array{status_code:int,response_body:string}
     */
    public function send(string $providerType, string $webhookUrl, array $payload): array
    {
        if ($providerType !== 'lineworks') {
            throw new InvalidArgumentException('unsupported provider type: ' . $providerType);
        }

        $url = trim($webhookUrl);
        if ($url === '') {
            throw new RuntimeException('webhook_url is empty');
        }

        $lineworksPayload = $this->normalizeLineworksPayload($payload);
        $body = json_encode($lineworksPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($body)) {
            throw new RuntimeException('failed to encode webhook payload');
        }

        if (function_exists('curl_init')) {
            return $this->sendByCurl($url, $body);
        }

        return $this->sendByStream($url, $body);
    }

    /**
     * @return array{status_code:int,response_body:string}
     */
    private function sendByCurl(string $url, string $body): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('curl_init failed');
        }

        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $responseBody = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($responseBody === false) {
            throw new RuntimeException('webhook request failed: ' . $curlError);
        }

        $responseBody = (string) $responseBody;
        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('webhook HTTP ' . $httpCode . ': ' . mb_substr($responseBody, 0, 500));
        }

        return [
            'status_code' => $httpCode,
            'response_body' => $responseBody,
        ];
    }

    /**
     * @return array{status_code:int,response_body:string}
     */
    private function sendByStream(string $url, string $body): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nAccept: application/json\r\n",
                'content' => $body,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);

        $responseBody = @file_get_contents($url, false, $context);
        if ($responseBody === false) {
            throw new RuntimeException('webhook request failed by stream transport');
        }

        $statusCode = 0;
        if (isset($http_response_header) && is_array($http_response_header) && isset($http_response_header[0])) {
            if (preg_match('/HTTP\/\d+\.\d+\s+(\d+)/', $http_response_header[0], $matches) === 1) {
                $statusCode = (int) $matches[1];
            }
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new RuntimeException('webhook HTTP ' . $statusCode . ': ' . mb_substr((string) $responseBody, 0, 500));
        }

        return [
            'status_code' => $statusCode,
            'response_body' => (string) $responseBody,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizeLineworksPayload(array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $body = $payload['body'] ?? null;
        $button = $payload['button'] ?? null;
        $text = is_array($body) ? trim((string) ($body['text'] ?? '')) : '';
        $buttonLabel = is_array($button) ? trim((string) ($button['label'] ?? '')) : '';
        $buttonUrl = is_array($button) ? trim((string) ($button['url'] ?? '')) : '';

        if ($title === '') {
            throw new RuntimeException('lineworks payload title is empty');
        }
        if ($text === '') {
            throw new RuntimeException('lineworks payload body.text is empty');
        }
        if ($buttonLabel === '') {
            throw new RuntimeException('lineworks payload button.label is empty');
        }
        if ($buttonUrl === '' || filter_var($buttonUrl, FILTER_VALIDATE_URL) === false) {
            throw new RuntimeException('lineworks payload button.url is invalid');
        }

        return [
            'title' => $title,
            'body' => [
                'text' => $text,
            ],
            'button' => [
                'label' => $buttonLabel,
                'url' => $buttonUrl,
            ],
        ];
    }
}
