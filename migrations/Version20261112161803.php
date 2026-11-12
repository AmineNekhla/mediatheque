<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261112161803 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE adherent (id INT AUTO_INCREMENT NOT NULL, numero_carte VARCHAR(20) NOT NULL, nom VARCHAR(100) NOT NULL, prenom VARCHAR(100) NOT NULL, debut_abonnement DATE NOT NULL, fin_abonnement DATE NOT NULL, utilisateur_id INT DEFAULT NULL, formule_id INT NOT NULL, site_id INT NOT NULL, UNIQUE INDEX UNIQ_90D3F060158BD4C0 (numero_carte), UNIQUE INDEX UNIQ_90D3F060FB88E14F (utilisateur_id), INDEX IDX_90D3F0602A68F4D1 (formule_id), INDEX IDX_90D3F060F6BD1646 (site_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE exemplaire (id INT AUTO_INCREMENT NOT NULL, code_barres VARCHAR(50) NOT NULL, statut VARCHAR(20) NOT NULL, version INT DEFAULT 1 NOT NULL, edition_id INT NOT NULL, site_attache_id INT NOT NULL, site_actuel_id INT NOT NULL, UNIQUE INDEX UNIQ_5EF83C9268EF62E0 (code_barres), INDEX IDX_5EF83C9274281A5E (edition_id), INDEX IDX_5EF83C92E7E89874 (site_attache_id), INDEX IDX_5EF83C926F95948A (site_actuel_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateur (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, roles JSON NOT NULL, mot_de_passe VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_1D1C63B3E7927C74 (email), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE adherent ADD CONSTRAINT FK_90D3F060FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateur (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE adherent ADD CONSTRAINT FK_90D3F0602A68F4D1 FOREIGN KEY (formule_id) REFERENCES formule_abonnement (id)');
        $this->addSql('ALTER TABLE adherent ADD CONSTRAINT FK_90D3F060F6BD1646 FOREIGN KEY (site_id) REFERENCES site (id)');
        $this->addSql('ALTER TABLE exemplaire ADD CONSTRAINT FK_5EF83C9274281A5E FOREIGN KEY (edition_id) REFERENCES edition (id)');
        $this->addSql('ALTER TABLE exemplaire ADD CONSTRAINT FK_5EF83C92E7E89874 FOREIGN KEY (site_attache_id) REFERENCES site (id)');
        $this->addSql('ALTER TABLE exemplaire ADD CONSTRAINT FK_5EF83C926F95948A FOREIGN KEY (site_actuel_id) REFERENCES site (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE adherent DROP FOREIGN KEY FK_90D3F060FB88E14F');
        $this->addSql('ALTER TABLE adherent DROP FOREIGN KEY FK_90D3F0602A68F4D1');
        $this->addSql('ALTER TABLE adherent DROP FOREIGN KEY FK_90D3F060F6BD1646');
        $this->addSql('ALTER TABLE exemplaire DROP FOREIGN KEY FK_5EF83C9274281A5E');
        $this->addSql('ALTER TABLE exemplaire DROP FOREIGN KEY FK_5EF83C92E7E89874');
        $this->addSql('ALTER TABLE exemplaire DROP FOREIGN KEY FK_5EF83C926F95948A');
        $this->addSql('DROP TABLE adherent');
        $this->addSql('DROP TABLE exemplaire');
        $this->addSql('DROP TABLE utilisateur');
    }
}
