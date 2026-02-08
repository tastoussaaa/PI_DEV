<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260207171002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, formation_id INT DEFAULT NULL, INDEX IDX_880E0D765200282E (formation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE aide_soignant (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, prenom VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, telephone INT NOT NULL, niveau_experience INT NOT NULL, disponible TINYINT NOT NULL, medecin_id INT DEFAULT NULL, INDEX IDX_76F03A724F31A84 (medecin_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE aide_soignant_formation (aide_soignant_id INT NOT NULL, formation_id INT NOT NULL, INDEX IDX_34AC6E10A920551D (aide_soignant_id), INDEX IDX_34AC6E105200282E (formation_id), PRIMARY KEY (aide_soignant_id, formation_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE admin ADD CONSTRAINT FK_880E0D765200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE aide_soignant ADD CONSTRAINT FK_76F03A724F31A84 FOREIGN KEY (medecin_id) REFERENCES medecin (id)');
        $this->addSql('ALTER TABLE aide_soignant_formation ADD CONSTRAINT FK_34AC6E10A920551D FOREIGN KEY (aide_soignant_id) REFERENCES aide_soignant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE aide_soignant_formation ADD CONSTRAINT FK_34AC6E105200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commande CHANGE montant_total montant_total DOUBLE PRECISION DEFAULT NULL');
        $this->addSql('ALTER TABLE formation ADD statut VARCHAR(20) NOT NULL, ADD medecin_id INT NOT NULL, CHANGE description description LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BF4F31A84 FOREIGN KEY (medecin_id) REFERENCES medecin (id)');
        $this->addSql('CREATE INDEX IDX_404021BF4F31A84 ON formation (medecin_id)');
        $this->addSql('ALTER TABLE patient CHANGE besoins_specifiques besoins_specifiques VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE produit CHANGE image_name image_name VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE user CHANGE roles roles JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin DROP FOREIGN KEY FK_880E0D765200282E');
        $this->addSql('ALTER TABLE aide_soignant DROP FOREIGN KEY FK_76F03A724F31A84');
        $this->addSql('ALTER TABLE aide_soignant_formation DROP FOREIGN KEY FK_34AC6E10A920551D');
        $this->addSql('ALTER TABLE aide_soignant_formation DROP FOREIGN KEY FK_34AC6E105200282E');
        $this->addSql('DROP TABLE admin');
        $this->addSql('DROP TABLE aide_soignant');
        $this->addSql('DROP TABLE aide_soignant_formation');
        $this->addSql('ALTER TABLE commande CHANGE montant_total montant_total DOUBLE PRECISION DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BF4F31A84');
        $this->addSql('DROP INDEX IDX_404021BF4F31A84 ON formation');
        $this->addSql('ALTER TABLE formation DROP statut, DROP medecin_id, CHANGE description description VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE patient CHANGE besoins_specifiques besoins_specifiques VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE produit CHANGE image_name image_name VARCHAR(255) DEFAULT \'NULL\'');
        $this->addSql('ALTER TABLE `user` CHANGE roles roles LONGTEXT NOT NULL COLLATE `utf8mb4_bin`');
    }
}
