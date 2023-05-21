<?php

declare(strict_types=1);

namespace Buggregator\Client\Traffic\Dispatcher;

use Buggregator\Client\Logger;
use Buggregator\Client\Socket\StreamClient;
use Buggregator\Client\Traffic\Dispatcher;
use Buggregator\Client\Traffic\Http\HandlerPipeline;
use Buggregator\Client\Traffic\Http\HttpParser;

final class Http implements Dispatcher
{
    private readonly HttpParser $parser;

    public function __construct(
        private readonly HandlerPipeline $handler,
    ) {
        $this->parser = new HttpParser();
    }

    public function dispatch(StreamClient $stream): iterable
    {
        $time = new \DateTimeImmutable();
        $request = $this->parser->parseStream($stream);

        $response = $this->handler->handle($request);

        $stream->sendData((string)$response);

        $stream->disconnect();

        yield new Frame\Http(
            $request,
            $time,
        );
    }

    public function detect(string $data): ?bool
    {
        if (!\str_contains($data, "\r\n")) {
            return null;
        }

        if (\preg_match('/^(GET|POST|PUT|HEAD|OPTIONS) \\S++ HTTP\\/1\\.\\d\\r$/m', $data) === 1) {
            return true;
        }

        Logger::info($data);

        return false;
    }
}
