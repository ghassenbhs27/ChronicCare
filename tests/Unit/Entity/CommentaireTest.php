<?php

namespace App\Tests\Unit\Entity;

use App\Entity\Commentaire;
use App\Entity\Publication;
use PHPUnit\Framework\TestCase;

final class CommentaireTest extends TestCase
{
    public function testAddReplySetsParentAndIncreasesReplyCount(): void
    {
        $publication = new Publication();

        $parent = new Commentaire();
        $parent->setPublication($publication);
        $parent->setContenu('Parent comment');

        $reply = new Commentaire();
        $reply->setPublication($publication);
        $reply->setContenu('Reply comment');

        $parent->addReply($reply);

        $this->assertSame($parent, $reply->getParent());
        $this->assertTrue($parent->hasReplies());
        $this->assertSame(1, $parent->getReplyCount());
        $this->assertSame(1, $reply->getNestingLevel());
        $this->assertSame(0, $parent->getNestingLevel());
    }
}