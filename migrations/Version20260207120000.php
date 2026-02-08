<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260207120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add name, family_name, email, sex and age columns to consultation table';
    }

    public function up(Schema $schema): void
    {
        // this up() migration adds the new columns to the consultation table
        $this->addSql("ALTER TABLE consultation ADD name VARCHAR(100) DEFAULT NULL");
        $this->addSql("ALTER TABLE consultation ADD family_name VARCHAR(100) DEFAULT NULL");
        $this->addSql("ALTER TABLE consultation ADD email VARCHAR(180) DEFAULT NULL");
        $this->addSql("ALTER TABLE consultation ADD sex VARCHAR(10) DEFAULT NULL");
        $this->addSql("ALTER TABLE consultation ADD age INT DEFAULT NULL");
    }

    public function down(Schema $schema): void
    {
        // revert the changes made in up()
        $this->addSql("ALTER TABLE consultation DROP COLUMN name");
        $this->addSql("ALTER TABLE consultation DROP COLUMN family_name");
        $this->addSql("ALTER TABLE consultation DROP COLUMN email");
        $this->addSql("ALTER TABLE consultation DROP COLUMN sex");
        $this->addSql("ALTER TABLE consultation DROP COLUMN age");
    }
}
