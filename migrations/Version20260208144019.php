<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260208144019 extends AbstractMigration
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
        $this->addSql('CREATE TABLE commande (id INT AUTO_INCREMENT NOT NULL, quantite INT NOT NULL, montant_total DOUBLE PRECISION DEFAULT NULL, statut VARCHAR(50) NOT NULL, date_commande DATETIME NOT NULL, produit_id INT NOT NULL, demandeur_id INT DEFAULT NULL, INDEX IDX_6EEAA67DF347EFB (produit_id), INDEX IDX_6EEAA67D95A6EE59 (demandeur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE consultation (id INT AUTO_INCREMENT NOT NULL, date_consultation DATETIME NOT NULL, motif VARCHAR(255) NOT NULL, diagnostic VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE formation (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, start_date DATE NOT NULL, end_date DATE NOT NULL, category VARCHAR(255) NOT NULL, statut VARCHAR(20) NOT NULL, medecin_id INT NOT NULL, INDEX IDX_404021BF4F31A84 (medecin_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE medecin (id INT AUTO_INCREMENT NOT NULL, specialite VARCHAR(255) NOT NULL, numero_ordre INT NOT NULL, annees_experience INT NOT NULL, disponible TINYINT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE ordonnance (id INT AUTO_INCREMENT NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE patient (id INT AUTO_INCREMENT NOT NULL, pathologie VARCHAR(255) NOT NULL, besoins_specifiques VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE produit (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, prix DOUBLE PRECISION NOT NULL, stock INT NOT NULL, categorie VARCHAR(255) NOT NULL, image_name VARCHAR(255) DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE `user` (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_IDENTIFIER_EMAIL (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE admin ADD CONSTRAINT FK_880E0D765200282E FOREIGN KEY (formation_id) REFERENCES formation (id)');
        $this->addSql('ALTER TABLE aide_soignant ADD CONSTRAINT FK_76F03A724F31A84 FOREIGN KEY (medecin_id) REFERENCES medecin (id)');
        $this->addSql('ALTER TABLE aide_soignant_formation ADD CONSTRAINT FK_34AC6E10A920551D FOREIGN KEY (aide_soignant_id) REFERENCES aide_soignant (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE aide_soignant_formation ADD CONSTRAINT FK_34AC6E105200282E FOREIGN KEY (formation_id) REFERENCES formation (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67DF347EFB FOREIGN KEY (produit_id) REFERENCES produit (id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67D95A6EE59 FOREIGN KEY (demandeur_id) REFERENCES `user` (id)');
        $this->addSql('ALTER TABLE formation ADD CONSTRAINT FK_404021BF4F31A84 FOREIGN KEY (medecin_id) REFERENCES medecin (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin DROP FOREIGN KEY FK_880E0D765200282E');
        $this->addSql('ALTER TABLE aide_soignant DROP FOREIGN KEY FK_76F03A724F31A84');
        $this->addSql('ALTER TABLE aide_soignant_formation DROP FOREIGN KEY FK_34AC6E10A920551D');
        $this->addSql('ALTER TABLE aide_soignant_formation DROP FOREIGN KEY FK_34AC6E105200282E');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67DF347EFB');
        $this->addSql('ALTER TABLE commande DROP FOREIGN KEY FK_6EEAA67D95A6EE59');
        $this->addSql('ALTER TABLE formation DROP FOREIGN KEY FK_404021BF4F31A84');
        $this->addSql('DROP TABLE admin');
        $this->addSql('DROP TABLE aide_soignant');
        $this->addSql('DROP TABLE aide_soignant_formation');
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE consultation');
        $this->addSql('DROP TABLE formation');
        $this->addSql('DROP TABLE medecin');
        $this->addSql('DROP TABLE ordonnance');
        $this->addSql('DROP TABLE patient');
        $this->addSql('DROP TABLE produit');
        $this->addSql('DROP TABLE `user`');
    }
}
