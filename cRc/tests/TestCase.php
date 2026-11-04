<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The app sends up to 200 rows per list (the data grid pages on screen); tests exercise server paging at 20.
        config(['crc.per_page' => 20]);
    }
}
