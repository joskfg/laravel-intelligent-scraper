<?php

namespace Tests\Unit\Fakes;

use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

abstract class FakeHttpException extends \Exception implements HttpExceptionInterface
{
}
