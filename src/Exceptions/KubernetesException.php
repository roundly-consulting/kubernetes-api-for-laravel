<?php

declare(strict_types=1);

namespace RoundlyConsulting\KubernetesApi\Exceptions;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;

class KubernetesException extends RequestException
{
    public function __construct(Response $response, ?string $message = null)
    {
        parent::__construct($response);

        if ($message) {
            $this->message = $message;
        }
    }
}
