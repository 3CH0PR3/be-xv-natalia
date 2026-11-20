<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Redis;
use Throwable;

abstract class TestCase extends BaseTestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    try {
      Redis::flushdb();
    } catch (Throwable) {
      // Ignorar si Redis no está disponible en este contexto
    }
  }
}
