<?php
    namespace App\Services;

    class RealtimeEventService {
        private const SECRET_FILE = __DIR__ . '/../../storage/realtime.secret';

        public static function issueToken(int $userId, int $roleId): string {
            $payload = self::encode(json_encode([
                'user_id' => $userId,
                'role_id' => $roleId,
                'exp' => time() + 900,
            ], JSON_THROW_ON_ERROR));
            $signature = self::encode(hash_hmac('sha256', $payload, self::secret(), true));

            return $payload . '.' . $signature;
        }

        public static function publishPendingCountChanged(): void {
            try {
                $body = '{"type":"pending-count-changed"}';
                $context = stream_context_create([
                    'http' => [
                        'method' => 'POST',
                        'header' => "Content-Type: application/json\r\nX-ARS-Signature: "
                            . hash_hmac('sha256', $body, self::secret()) . "\r\n",
                        'content' => $body,
                        'timeout' => 0.75,
                        'ignore_errors' => true,
                    ],
                ]);
                @file_get_contents('http://realtime:3000/publish', false, $context);
            } catch (\Throwable $exception) {
                error_log('[RealtimeEventService] Pending count event publish failed.');
            }
        }

        private static function secret(): string {
            $secret = @file_get_contents(self::SECRET_FILE);
            if ($secret === false || strlen(trim($secret)) < 32) {
                throw new \RuntimeException('Realtime signing key is unavailable.');
            }

            return trim($secret);
        }

        private static function encode(string $value): string {
            return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        }
    }