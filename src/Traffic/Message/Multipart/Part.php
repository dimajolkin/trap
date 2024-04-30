<?php

declare(strict_types=1);

namespace Buggregator\Trap\Traffic\Message\Multipart;

use Buggregator\Trap\Traffic\Message\Headers;
use JsonSerializable;
use RuntimeExceptio;
use RuntimeException;

/**
 * @internal
 */
abstract class Part implements JsonSerializable
{
    use Headers;

    /**
     * @param array<non-empty-string, non-empty-list<string>> $headers
     */
    protected function __construct(
        array $headers,
        protected ?string $name,
    ) {
        $this->setHeaders($headers);
    }

    /**
     * @param array<non-empty-string, non-empty-list<non-empty-string>> $headers
     */
    public static function create(array $headers): Part
    {
        /**
         * Check Content-Disposition header
         *
         * @var string $contentDisposition
         */
        $contentDisposition = self::findHeader($headers, 'Content-Disposition')[0]
            ?? throw new RuntimeException('Missing Content-Disposition header.');
        if ($contentDisposition === '') {
            throw new RuntimeException('Missing Content-Disposition header, can\'t be empty');
        }

        // Get field name and file name
        $name = \preg_match('/\bname=(?:(?<a>[^" ;,]++)|"(?<b>[^"]++)")/', $contentDisposition, $matches) === 1
            ? ($matches['a'] ?: $matches['b'])
            : null;

        // Decode file name
        $fileName = \preg_match('/\bfilename=(?:(?<a>[^" ;,]++)|"(?<b>[^"]++)")/', $contentDisposition, $matches) === 1
            ? ($matches['a'] ?: $matches['b'])
            : null;
        $fileName = $fileName !== null ? \html_entity_decode($fileName) : null;
        $isFile = (string)$fileName !== ''
            || \preg_match('/text\\/.++/', self::findHeader($headers, 'Content-Type')[0] ?? 'text/plain') !== 1;

        return match ($isFile) {
            true => new File($headers, $name, $fileName),
            false => new Field($headers, $name),
        };
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function withName(?string $name): static
    {
        $clone = clone $this;
        $clone->name = $name;
        return $clone;
    }

    /**
     * @return array{
     *      headers: array<non-empty-string, non-empty-list<string>>,
     *      name?: string,
     *  }
     */
    public function jsonSerialize(): array
    {
        $data = [
            'headers' => $this->headers,
        ];

        if ($this->name !== null) {
            $data['name'] = $this->name;
        }

        return $data;
    }
}
