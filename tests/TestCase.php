<?php

namespace Tests;

use App\Services\Bbb\BbbClientFactory;
use App\Support\SeoSettings;
use Tests\Support\FacadeBbbClientFactory;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // SEO-настройки кэшируются в статическом свойстве на весь процесс: без сброса настройка,
        // сохранённая в одном тесте (например, «индексация выключена»), протекает в следующие
        SeoSettings::flush();

        // Серверы видеосвязи отвечают через фасад Bigbluebutton — его подменяют тесты (без сети)
        $this->app->bind(BbbClientFactory::class, FacadeBbbClientFactory::class);
    }
}
