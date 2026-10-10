<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\DataTransferObjects;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Date;
use RoundlyConsulting\KubernetesApi\Exceptions\KubernetesException;
use SensitiveParameter;
use Throwable;

/**
 * A token the apiserver minted for a TokenRequest (`ServiceAccount::requestToken()`):
 * the bearer token, when it expires (UTC), the audiences it is valid for and the object
 * it is bound to, if any. The token is masked in `var_dump()`, `print_r()` and `dump()`.
 */
final readonly class ServiceAccountToken
{
    /**
     * @param  list<string>  $audiences  the audiences the apiserver answered (`spec.audiences`)
     * @param  array<string, string>|null  $boundObjectRef  `kind`, `apiVersion`, `name`, `uid`
     */
    public function __construct(
        #[SensitiveParameter]
        public string $token,
        public CarbonImmutable $expiresAt,
        public array $audiences = [],
        public ?array $boundObjectRef = null,
    ) {}

    /**
     * Map a TokenRequest answer.
     *
     * @throws KubernetesException when it carries no token or no RFC 3339 expiry (the
     *                             message is body-free)
     */
    public static function fromResponse(Response $response): self
    {
        $token = $response->json('status.token');
        $expiresAt = self::timestamp($response->json('status.expirationTimestamp'));

        if (! is_string($token) || $token === '' || $expiresAt === null) {
            throw KubernetesException::malformedResponse($response, 'TokenRequest');
        }

        $audiences = $response->json('spec.audiences');

        return new self(
            token: $token,
            expiresAt: $expiresAt,
            audiences: is_array($audiences) ? array_values(array_filter($audiences, is_string(...))) : [],
            boundObjectRef: self::objectRef($response->json('spec.boundObjectRef')),
        );
    }

    /**
     * Whether the token has expired by `$at` (now by default).
     */
    public function isExpired(?CarbonInterface $at = null): bool
    {
        return ! ($at ?? Date::now())->lessThan($this->expiresAt);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'token' => '********',
            'expiresAt' => $this->expiresAt,
            'audiences' => $this->audiences,
            'boundObjectRef' => $this->boundObjectRef,
        ];
    }

    private static function timestamp(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/i', $value) !== 1) {
            return null;
        }

        try {
            return CarbonImmutable::parse($value)->utc();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>|null
     */
    private static function objectRef(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $ref = [];

        foreach ($value as $key => $field) {
            if (is_string($key) && is_string($field)) {
                $ref[$key] = $field;
            }
        }

        return $ref;
    }
}
