<?php

declare(strict_types=1);

namespace App\Data;

final readonly class ExportDocument
{
    /**
     * @param  array<string, string>  $translations
     */
    public function __construct(
        public string $locale,
        public int $version,
        public array $translations,
        public string $generatedAt,
        public string $fingerprint,
        public ?string $body = null,
        private ?int $storedCount = null,
    ) {
    }

    public static function withBody(
        string $locale,
        int $version,
        string $generatedAt,
        string $fingerprint,
        string $body,
        int $count,
    ): self {
        return new self(
            locale: $locale,
            version: $version,
            translations: [],
            generatedAt: $generatedAt,
            fingerprint: $fingerprint,
            body: $body,
            storedCount: $count,
        );
    }

    public function entryCount(): int
    {
        return $this->storedCount ?? count($this->translations);
    }

    public function encodedBody(): string
    {
        if ($this->body !== null) {
            return $this->body;
        }

        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }


    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'locale' => $this->locale,
            'version' => $this->version,
            'generated_at' => $this->generatedAt,
            'count' => $this->entryCount(),
            'translations' => $this->translations === [] ? (object) [] : $this->translations,
        ];
    }

    /**
     * @return array{locale: string, version: int, generated_at: string, fingerprint: string, count: int, body: string}
     */
    public function toCacheArray(): array
    {
        return [
            'locale' => $this->locale,
            'version' => $this->version,
            'generated_at' => $this->generatedAt,
            'fingerprint' => $this->fingerprint,
            'count' => $this->entryCount(),
            'body' => $this->encodedBody(),
        ];
    }

    /**
     * @param  array{locale: string, version: int|string, generated_at: string, fingerprint: string, count: int|string, body: string}  $payload
     */
    public static function fromArray(array $payload): self
    {
        return self::withBody(
            locale: $payload['locale'],
            version: (int) $payload['version'],
            generatedAt: $payload['generated_at'],
            fingerprint: $payload['fingerprint'],
            body: $payload['body'],
            count: (int) $payload['count'],
        );
    }
}
