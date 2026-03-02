<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

<<<<<<< HEAD
=======
/**
 * Manually add missing columns to ordonnance table - raw SQL approach
 */
>>>>>>> 5c42e2f9c8f065578ea2c2cf78d2221bad17907a
final class Version20260223120000 extends AbstractMigration
{
    public function getDescription(): string
    {
<<<<<<< HEAD
        return 'Add medecin_id and patient_id columns to consultation table if they do not exist';
=======
        return 'Add missing columns to ordonnance table';
>>>>>>> 5c42e2f9c8f065578ea2c2cf78d2221bad17907a
    }

    public function up(Schema $schema): void
    {
<<<<<<< HEAD
        // Check if columns exist before adding them
        $schemaManager = $this->connection->createSchemaManager();
        $consultationTable = $schemaManager->introspectTable('consultation');
        
        if (!$consultationTable->hasColumn('medecin_id')) {
            $this->addSql('ALTER TABLE consultation ADD medecin_id INT DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_964685A64F31A84 ON consultation (medecin_id)');
            $this->addSql('ALTER TABLE consultation ADD CONSTRAINT FK_964685A64F31A84 FOREIGN KEY (medecin_id) REFERENCES medecin (id)');
        }
        
        if (!$consultationTable->hasColumn('patient_id')) {
            $this->addSql('ALTER TABLE consultation ADD patient_id INT DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_964685A66B899279 ON consultation (patient_id)');
            $this->addSql('ALTER TABLE consultation ADD CONSTRAINT FK_964685A66B899279 FOREIGN KEY (patient_id) REFERENCES patient (id)');
        }
=======
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
>>>>>>> 5c42e2f9c8f065578ea2c2cf78d2221bad17907a
    }

    public function down(Schema $schema): void
    {
<<<<<<< HEAD
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY FK_964685A64F31A84');
        $this->addSql('ALTER TABLE consultation DROP FOREIGN KEY FK_964685A66B899279');
        $this->addSql('DROP INDEX IDX_964685A64F31A84 ON consultation');
        $this->addSql('DROP INDEX IDX_964685A66B899279 ON consultation');
        $this->addSql('ALTER TABLE consultation DROP medecin_id');
        $this->addSql('ALTER TABLE consultation DROP patient_id');
=======
        $this->addSql('ALTER TABLE ordonnance DROP FOREIGN KEY FK_ORDONNANCE_CONSULTATION');
        $this->addSql('ALTER TABLE ordonnance DROP COLUMN consultation_id');
        $this->addSql('ALTER TABLE ordonnance DROP COLUMN created_at');
        $this->addSql('ALTER TABLE ordonnance DROP COLUMN instructions');
        $this->addSql('ALTER TABLE ordonnance DROP COLUMN duree');
        $this->addSql('ALTER TABLE ordonnance DROP COLUMN dosage');
        $this->addSql('ALTER TABLE ordonnance DROP COLUMN medicament');
>>>>>>> 5c42e2f9c8f065578ea2c2cf78d2221bad17907a
    }
}
