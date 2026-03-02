<?php

namespace App\Tests\Unit\Service;

use App\Service\TranslationService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

final class TranslationServiceTest extends TestCase
{
    public function testDetectLanguageReturnsFrench(): void
    {
        $service = new TranslationService(new MockHttpClient(), new NullLogger());

        $lang = $service->detectLanguage("C'est un texte avec des accents éèà et des mots français.");
        $this->assertSame('fr', $lang);
    }

    public function testTranslateReturnsErrorWhenTextIsEmpty(): void
    {
        $service = new TranslationService(new MockHttpClient(), new NullLogger());

        $result = $service->translate("   ", 'fr', 'en');

        $this->assertFalse($result['success']);
        $this->assertNull($result['translatedText']);
        $this->assertNotNull($result['error']);
        $this->assertFalse($result['cached']);
    }
}
