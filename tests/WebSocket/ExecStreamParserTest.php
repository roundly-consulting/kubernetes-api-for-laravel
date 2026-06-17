<?php

declare(strict_types=1);

use RoundlyConsulting\KubernetesApi\WebSocket\ExecStreamParser;

it('demultiplexes stdout and stderr channels', function () {
    $parser = new ExecStreamParser;

    $parser->feed(chr(ExecStreamParser::CHANNEL_STDOUT).'hello ');
    $parser->feed(chr(ExecStreamParser::CHANNEL_STDOUT).'world');
    $parser->feed(chr(ExecStreamParser::CHANNEL_STDERR).'oops');

    $result = $parser->result();

    expect($result->stdout)->toBe('hello world')
        ->and($result->stderr)->toBe('oops')
        ->and($result->exitCode)->toBe(0)
        ->and($result->successful())->toBeTrue();
});

it('ignores empty and unknown-channel messages', function () {
    $parser = new ExecStreamParser;

    $parser->feed('');
    $parser->feed(chr(0).'stdin echo');
    $parser->feed(chr(9).'unknown');

    expect($parser->result()->stdout)->toBe('');
});

it('returns zero exit code for a success status', function () {
    $parser = new ExecStreamParser;

    $parser->feed(chr(ExecStreamParser::CHANNEL_ERROR).json_encode(['status' => 'Success']));

    expect($parser->exitCode())->toBe(0);
});

it('reads a non-zero exit code from the error channel status', function () {
    $parser = new ExecStreamParser;

    $status = json_encode([
        'status' => 'Failure',
        'reason' => 'NonZeroExitCode',
        'details' => ['causes' => [['reason' => 'ExitCode', 'message' => '137']]],
    ]);

    $parser->feed(chr(ExecStreamParser::CHANNEL_ERROR).$status);

    expect($parser->exitCode())->toBe(137)
        ->and($parser->result()->successful())->toBeFalse();
});

it('defaults to exit code 1 for a failure without an explicit code', function () {
    $parser = new ExecStreamParser;

    $parser->feed(chr(ExecStreamParser::CHANNEL_ERROR).json_encode(['status' => 'Failure']));

    expect($parser->exitCode())->toBe(1);
});
