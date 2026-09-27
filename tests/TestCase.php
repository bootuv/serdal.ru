<?php

namespace Tests;

use App\Support\SeoSettings;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // SEO-настройки кэшируются в статическом свойстве на весь процесс: без сброса настройка,
        // сохранённая в одном тесте (например, «индексация выключена»), протекает в следующие
        SeoSettings::flush();
    }
}
