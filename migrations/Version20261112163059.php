<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112163059 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE penalite (id INT AUTO_INCREMENT NOT NULL, type VARCHAR(20) NOT NULL, montant_centimes INT NOT NULL, statut VARCHAR(20) NOT NULL, date_creation DATETIME NOT NULL, adherent_id INT NOT NULL, pret_id INT NOT NULL, INDEX IDX_C62D8C5D25F06C53 (adherent_id), INDEX IDX_C62D8C5D1B61704B (pret_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE pret (id INT AUTO_INCREMENT NOT NULL, date_emprunt DATETIME NOT NULL, date_retour_prevue DATETIME NOT NULL, date_retour DATETIME DEFAULT NULL, nombre_prolongations INT DEFAULT 0 NOT NULL, statut VARCHAR(20) NOT NULL, exemplaire_id INT NOT NULL, adherent_id INT NOT NULL, INDEX IDX_52ECE9795843AA21 (exemplaire_id), INDEX IDX_52ECE97925F06C53 (adherent_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE reservation (id INT AUTO_INCREMENT NOT NULL, date_creation DATETIME NOT NULL, date_expiration_retrait DATETIME DEFAULT NULL, statut VARCHAR(20) NOT NULL, adherent_id INT NOT NULL, oeuvre_id INT NOT NULL, edition_id INT DEFAULT NULL, site_retrait_id INT NOT NULL, exemplaire_reserve_id INT DEFAULT NULL, INDEX IDX_42C8495525F06C53 (adherent_id), INDEX IDX_42C8495588194DE8 (oeuvre_id), INDEX IDX_42C8495574281A5E (edition_id), INDEX IDX_42C849555EF1CABF (site_retrait_id), INDEX IDX_42C84955A46EF492 (exemplaire_reserve_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE transfert (id INT AUTO_INCREMENT NOT NULL, motif VARCHAR(20) NOT NULL, date_demande DATETIME NOT NULL, date_reception DATETIME DEFAULT NULL, exemplaire_id INT NOT NULL, site_origine_id INT NOT NULL, site_destination_id INT NOT NULL, reservation_id INT DEFAULT NULL, INDEX IDX_1E4EACBB5843AA21 (exemplaire_id), INDEX IDX_1E4EACBB208E164B (site_origine_id), INDEX IDX_1E4EACBBC03AB848 (site_destination_id), INDEX IDX_1E4EACBBB83297E7 (reservation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE penalite ADD CONSTRAINT FK_C62D8C5D25F06C53 FOREIGN KEY (adherent_id) REFERENCES adherent (id)');
        $this->addSql('ALTER TABLE penalite ADD CONSTRAINT FK_C62D8C5D1B61704B FOREIGN KEY (pret_id) REFERENCES pret (id)');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT FK_52ECE9795843AA21 FOREIGN KEY (exemplaire_id) REFERENCES exemplaire (id)');
        $this->addSql('ALTER TABLE pret ADD CONSTRAINT FK_52ECE97925F06C53 FOREIGN KEY (adherent_id) REFERENCES adherent (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495525F06C53 FOREIGN KEY (adherent_id) REFERENCES adherent (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495588194DE8 FOREIGN KEY (oeuvre_id) REFERENCES oeuvre (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C8495574281A5E FOREIGN KEY (edition_id) REFERENCES edition (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C849555EF1CABF FOREIGN KEY (site_retrait_id) REFERENCES site (id)');
        $this->addSql('ALTER TABLE reservation ADD CONSTRAINT FK_42C84955A46EF492 FOREIGN KEY (exemplaire_reserve_id) REFERENCES exemplaire (id)');
        $this->addSql('ALTER TABLE transfert ADD CONSTRAINT FK_1E4EACBB5843AA21 FOREIGN KEY (exemplaire_id) REFERENCES exemplaire (id)');
        $this->addSql('ALTER TABLE transfert ADD CONSTRAINT FK_1E4EACBB208E164B FOREIGN KEY (site_origine_id) REFERENCES site (id)');
        $this->addSql('ALTER TABLE transfert ADD CONSTRAINT FK_1E4EACBBC03AB848 FOREIGN KEY (site_destination_id) REFERENCES site (id)');
        $this->addSql('ALTER TABLE transfert ADD CONSTRAINT FK_1E4EACBBB83297E7 FOREIGN KEY (reservation_id) REFERENCES reservation (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE penalite DROP FOREIGN KEY FK_C62D8C5D25F06C53');
        $this->addSql('ALTER TABLE penalite DROP FOREIGN KEY FK_C62D8C5D1B61704B');
        $this->addSql('ALTER TABLE pret DROP FOREIGN KEY FK_52ECE9795843AA21');
        $this->addSql('ALTER TABLE pret DROP FOREIGN KEY FK_52ECE97925F06C53');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495525F06C53');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495588194DE8');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C8495574281A5E');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C849555EF1CABF');
        $this->addSql('ALTER TABLE reservation DROP FOREIGN KEY FK_42C84955A46EF492');
        $this->addSql('ALTER TABLE transfert DROP FOREIGN KEY FK_1E4EACBB5843AA21');
        $this->addSql('ALTER TABLE transfert DROP FOREIGN KEY FK_1E4EACBB208E164B');
        $this->addSql('ALTER TABLE transfert DROP FOREIGN KEY FK_1E4EACBBC03AB848');
        $this->addSql('ALTER TABLE transfert DROP FOREIGN KEY FK_1E4EACBBB83297E7');
        $this->addSql('DROP TABLE penalite');
        $this->addSql('DROP TABLE pret');
        $this->addSql('DROP TABLE reservation');
        $this->addSql('DROP TABLE transfert');
    }
}
