<?php

namespace App\Tests\Unit\Service;

use App\Service\StickerService;
use PHPUnit\Framework\TestCase;

final class StickerServiceTest extends TestCase
{
    public function testGetStickersByPackReturnsNonEmptyForKnownPack(): void
    {
        $service = new StickerService();
        $stickers = $service->getStickersByPack('emoji');

        $this->assertNotEmpty($stickers);
        $this->assertIsArray($stickers);
        $this->assertIsString($stickers[0]);
    }

    public function testGetStickersByPackReturnsEmptyForUnknownPack(): void
    {
        $service = new StickerService();
        $stickers = $service->getStickersByPack('unknown_pack');

        $this->assertSame([], $stickers);
    }

    public function testIsStickerValidFindsStickerInPacks(): void
    {
        $service = new StickerService();

        $this->assertTrue($service->isStickerValid('😀'));
        $this->assertFalse($service->isStickerValid('NOT_A_STICKER'));
    }
}