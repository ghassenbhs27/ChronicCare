<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260217224500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add activity badge fields on users for most active user reward';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE users ADD activity_score INT NOT NULL DEFAULT 0, ADD is_most_active TINYINT(1) NOT NULL DEFAULT 0, ADD activity_badge VARCHAR(120) DEFAULT NULL, ADD activity_reward VARCHAR(255) DEFAULT NULL, ADD activity_badge_awarded_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP activity_score, DROP is_most_active, DROP activity_badge, DROP activity_reward, DROP activity_badge_awarded_at');
    }
}
