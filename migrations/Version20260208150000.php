<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create formation_application table for tracking aide soignant applications to formations';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE formation_application (id INT AUTO_INCREMENT NOT NULL, aide_soignant_id INT NOT NULL, formation_id INT NOT NULL, status VARCHAR(20) NOT NULL, applied_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, rejection_reason VARCHAR(255) DEFAULT NULL, INDEX IDX_D47E8FA0A920551D (aide_soignant_id), INDEX IDX_D47E8FA05200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE formation_application ADD CONSTRAINT FK_D47E8FA0A920551D FOREIGN KEY (aide_soignant_id) REFERENCES aide_soignant (id)');
        $this->addSql('ALTER TABLE formation_application ADD CONSTRAINT FK_D47E8FA05200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE formation_application DROP FOREIGN KEY FK_D47E8FA0A920551D');
        $this->addSql('ALTER TABLE formation_application DROP FOREIGN KEY FK_D47E8FA05200282E');
        $this->addSql('DROP TABLE formation_application');
    }
}
