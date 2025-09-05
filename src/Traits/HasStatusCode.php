<?php

namespace Aslnbxrz\SimpleException\Traits;

use Symfony\Component\HttpFoundation\Response;

trait HasStatusCode
{
    /**
     * Get the status code for the enum case.
     */
    public function statusCode(): int
    {
        return $this->value;
    }

    /**
     * Get the HTTP status code for the enum case.
     * Auto-maps based on status code value.
     */
    public function httpStatusCode(): int
    {
        $code = $this->value;
        
        // Map common status codes
        return match (true) {
            $code >= 200 && $code < 300 => Response::HTTP_OK,
            $code >= 300 && $code < 400 => Response::HTTP_MOVED_PERMANENTLY,
            $code >= 400 && $code < 500 => Response::HTTP_BAD_REQUEST,
            $code >= 500 && $code < 600 => Response::HTTP_INTERNAL_SERVER_ERROR,
            default => Response::HTTP_INTERNAL_SERVER_ERROR
        };
    }
}