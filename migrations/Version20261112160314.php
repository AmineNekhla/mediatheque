<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112160314 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create oeuvre, edition, personne and contribution tables';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE contribution (id INT AUTO_INCREMENT NOT NULL, role VARCHAR(30) NOT NULL, personne_id INT NOT NULL, oeuvre_id INT DEFAULT NULL, edition_id INT DEFAULT NULL, INDEX IDX_EA351E15A21BD112 (personne_id), INDEX IDX_EA351E1588194DE8 (oeuvre_id), INDEX IDX_EA351E1574281A5E (edition_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE edition (id INT AUTO_INCREMENT NOT NULL, isbn VARCHAR(20) DEFAULT NULL, type_document VARCHAR(20) NOT NULL, editeur VARCHAR(150) DEFAULT NULL, langue VARCHAR(10) DEFAULT NULL, annee_publication INT DEFAULT NULL, nombre_pages INT DEFAULT NULL, url_couverture VARCHAR(500) DEFAULT NULL, source VARCHAR(20) NOT NULL, a_verifier TINYINT DEFAULT 0 NOT NULL, oeuvre_id INT NOT NULL, UNIQUE INDEX UNIQ_A891181FCC1CF4E6 (isbn), INDEX IDX_A891181F88194DE8 (oeuvre_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE oeuvre (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, langue_originale VARCHAR(10) DEFAULT NULL, annee_premiere_publication INT DEFAULT NULL, resume LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE personne (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) DEFAULT NULL, nationalite VARCHAR(100) DEFAULT NULL, biographie LONGTEXT DEFAULT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE contribution ADD CONSTRAINT FK_EA351E15A21BD112 FOREIGN KEY (personne_id) REFERENCES personne (id)');
        $this->addSql('ALTER TABLE contribution ADD CONSTRAINT FK_EA351E1588194DE8 FOREIGN KEY (oeuvre_id) REFERENCES oeuvre (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE contribution ADD CONSTRAINT FK_EA351E1574281A5E FOREIGN KEY (edition_id) REFERENCES edition (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE edition ADD CONSTRAINT FK_A891181F88194DE8 FOREIGN KEY (oeuvre_id) REFERENCES oeuvre (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contribution DROP FOREIGN KEY FK_EA351E15A21BD112');
        $this->addSql('ALTER TABLE contribution DROP FOREIGN KEY FK_EA351E1588194DE8');
        $this->addSql('ALTER TABLE contribution DROP FOREIGN KEY FK_EA351E1574281A5E');
        $this->addSql('ALTER TABLE edition DROP FOREIGN KEY FK_A891181F88194DE8');
        $this->addSql('DROP TABLE contribution');
        $this->addSql('DROP TABLE edition');
        $this->addSql('DROP TABLE oeuvre');
        $this->addSql('DROP TABLE personne');
    }
}
