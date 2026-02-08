<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208040106 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commande CHANGE montant_total montant_total DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE demande_aide CHANGE date_creation date_creation DATETIME DEFAULT NULL, CHANGE date_debut_souhaitee date_debut_souhaitee DATETIME DEFAULT NULL, CHANGE date_fin_souhaitee date_fin_souhaitee DATETIME DEFAULT NULL, CHANGE statut statut VARCHAR(255) DEFAULT NULL, CHANGE adresse adresse VARCHAR(255) DEFAULT NULL, CHANGE ville ville VARCHAR(255) DEFAULT NULL, CHANGE lieu lieu VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE patient CHANGE besoins_specifiques besoins_specifiques VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit CHANGE image_name image_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE commande CHANGE montant_total montant_total DOUBLE PRECISION DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE demande_aide CHANGE date_creation date_creation DATETIME DEFAULT \'NULL\', CHANGE date_debut_souhaitee date_debut_souhaitee DATETIME DEFAULT \'NULL\', CHANGE date_fin_souhaitee date_fin_souhaitee DATETIME DEFAULT \'NULL\', CHANGE statut statut VARCHAR(255) DEFAULT \'NULL\', CHANGE adresse adresse VARCHAR(255) DEFAULT \'NULL\', CHANGE ville ville VARCHAR(255) DEFAULT \'NULL\', CHANGE lieu lieu VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE patient CHANGE besoins_specifiques besoins_specifiques VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE produit CHANGE image_name image_name VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE `user` CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
    }
}
