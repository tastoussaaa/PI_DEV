<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Manually add missing columns to ordonnance table - raw SQL approach
 */
final class Version20260223120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing columns to ordonnance table';
    }

    public function up(Schema $schema): void
    {
        // Use raw SQL to add columns by checking if they exist first
        $this->addSql("
            ALTER TABLE ordonnance 
            ADD COLUMN IF NOT EXISTS medicament VARCHAR(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS dosage VARCHAR(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS duree VARCHAR(255) DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS instructions LONGTEXT DEFAULT NULL,
            ADD COLUMN IF NOT EXISTS created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ADD COLUMN IF NOT EXISTS consultation_id INT DEFAULT NULL
        ");

        // Add foreign key if it doesn't exist
        $this->addSql("
            ALTER TABLE ordonnance 
            ADD CONSTRAINT FK_ORDONNANCE_CONSULTATION FOREIGN KEY (consultation_id) REFERENCES consultation (id)
        ");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ordonnance DROP FOREIGN KEY FK_ORDONNANCE_CONSULTATION');
        $this->addSql('ALTER TABLE ordonnance DROP COLUMN consultation_id');
        $this->addSql('ALTER TABLE ordonnance DROP COLUMN created_at');
        $this->addSql('ALTER TABLE ordonnance DROP COLUMN instructions');
        $this->addSql('ALTER TABLE ordonnance DROP COLUMN duree');
        $this->addSql('ALTER TABLE ordonnance DROP COLUMN dosage');
        $this->addSql('ALTER TABLE ordonnance DROP COLUMN medicament');
    }
}
