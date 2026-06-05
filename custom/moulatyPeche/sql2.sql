
CREATE TABLE IF NOT EXISTS llx_pech_reception (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    ref VARCHAR(100) NOT NULL,                      -- Référence de la réception
    fk_fournisseur INT NOT NULL,                    -- Fournisseur (llx_societe)
    fk_congelateur INT DEFAULT NULL,                -- Fournisseur (llx_societe) Congélateur  utilisé
    fk_entrepot INT DEFAULT NULL,                   -- Entrepôt de stockage

    date_creation DATETIME NOT NULL,                -- Date de création
    comment TEXT DEFAULT NULL,                      -- Commentaire éventuel
    montant DECIMAL(20,2) DEFAULT 0,                -- Montant total de la réception
    frais DECIMAL(20,2) DEFAULT 0,
    user_create INT DEFAULT NULL,                   -- Utilisateur créateur
    etat TINYINT DEFAULT 0,                         -- 0=En attente, 1=Validé, 2=Annulé
    fk_facture INT DEFAULT NULL,                    -- Lien vers la facture générée
    fk_bon_recep INT DEFAULT NULL,                  -- Lien vers la facture générée
    poids DECIMAL(20,2) DEFAULT 0,                  -- Poids total calculé automatiquement

    entity INT NOT NULL DEFAULT 1,                  -- Multi-entité Dolibarr
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS llx_pech_receptiondet (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_reception INT NOT NULL,                      -- Référence vers réception
    fk_product INT NOT NULL,                        -- Produit (poisson)
    reception_mode TINYINT NOT NULL DEFAULT 3,       -- 1=Voiture, 2=Poids Brut, 3=Poids Net
    calibre VARCHAR(50) DEFAULT NULL,               -- Calibre du poisson

    -- Mode Voiture
    nb_voiture INT DEFAULT 0,
    prix_voiture DECIMAL(20,2) DEFAULT 0,

    -- Mode Poids Brut
    poids_brut DECIMAL(20,2) DEFAULT 0,
    pu_brut DECIMAL(20,2) DEFAULT 0,

    -- Mode Poids Net
    poids_net DECIMAL(20,2) DEFAULT 0,
    pu_poids_net DECIMAL(20,2) DEFAULT 0,

    total_line DECIMAL(20,2) DEFAULT 0,             -- Montant total de la ligne

    prix_moyen DECIMAL(20,4) DEFAULT 0,             -- Montant total de la ligne

    entity INT NOT NULL DEFAULT 1,
    poids_plater DECIMAL(20,2) DEFAULT 0,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS llx_pech_bonreception (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    ref VARCHAR(100) NOT NULL,                  -- Référence du bon de réception
    fk_reception INT DEFAULT NULL,              -- Réception associée
    fk_fournisseur INT DEFAULT NULL,            -- Fournisseur
    fk_congelateur INT DEFAULT NULL,               -- Fournisseur FRIGO
    date_creation DATETIME NOT NULL,            -- Date de création
    user_create INT DEFAULT NULL,               -- Utilisateur créateur
    comment TEXT DEFAULT NULL,                  -- Commentaire éventuel
    total DECIMAL(20,2) DEFAULT 0,             -- Montant total du bon
    poids DECIMAL(20,2) DEFAULT 0,             -- Poids total du bon
    entity INT NOT NULL DEFAULT 1,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS llx_pech_bonreceptiondet (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_bonreception INT NOT NULL,              -- Référence vers bon de réception
    description VARCHAR(1000) not NULL,    

    qte INT DEFAULT 0,
    PU DECIMAL(20,2) DEFAULT 0,


    total_line DECIMAL(20,2) DEFAULT 0,        -- Montant total de la ligne
    entity INT NOT NULL DEFAULT 1,
    type INT NOT NULL DEFAULT 0,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS llx_pech_bon_misenplat (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    ref VARCHAR(100) NOT NULL,                          -- Référence du bon
    fk_entrepot INT NOT NULL,                           -- Entrepôt concerné
    fk_receptiondet varchar(255) NULL, 
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,   -- Date de création
    fk_user INT DEFAULT NULL,                           -- Utilisateur qui a créé le bon
    total_frais DECIMAL(20,2) DEFAULT 0,
    fk_facture_frais int null,
    statut TINYINT DEFAULT 0,                           -- 0=brouillon, 1=validé, 2=terminé
    commentaire TEXT,                                   -- Note ou observation
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS llx_pech_misenplat (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_bon_misenplat INT NOT NULL,  
    fk_congelateur INT NOT NULL,                 -- Fournisseur conjulateur
    fk_product INT DEFAULT NULL,   
    nombre_plat INT DEFAULT 0,                   -- Nombre total de plats
    nombre_plat_sortie INT DEFAULT 0,                   -- Nombre total de plats sortie
    poids_plat DOUBLE(24,8) DEFAULT 0,           -- Poids d’un plat individuel
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP, -- Date de création
    statut TINYINT DEFAULT 0,                    -- Statut (0=brouillon,1=validé,etc.)
    --fk_bon_mis_plat INT DEFAULT NULL,              -- Bon d’entrée lié

    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Dernière modif
    pointeur VARCHAR(100) DEFAULT NULL,          -- Identifiant ou traceur
    commentaire TEXT                             -- Commentaires
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS llx_pech_plat (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_misenplat INT NOT NULL,                   -- Lien vers la mise en plat
    fk_product INT DEFAULT NULL,                    -- Produit concerné
    fk_carton INT DEFAULT NULL,                  -- Carton associé
    poids DOUBLE(24,2) DEFAULT 0,                -- Poids du plat
    prix_moyen DECIMAL(20,4) DEFAULT 0,             -- prix moyen du plat
    frais DECIMAL(20,4) NOT NULL DEFAULT 0,

    statut TINYINT DEFAULT 0,                    -- Statut du plat
    commentaire TEXT,                            -- Commentaires libres
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS llx_pech_bon_misenplatdet (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_bon_misenplat INT NOT NULL,              -- Référence vers bon de réception
    description VARCHAR(1000) not NULL,    

    qte INT DEFAULT 0,
    PU DECIMAL(20,2) DEFAULT 0,


    total_line DECIMAL(20,2) DEFAULT 0,        -- Montant total de la ligne
    entity INT NOT NULL DEFAULT 1,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ===========================
-- TABLE LOTS (En-tête de lot)
-- ===========================
CREATE TABLE IF NOT EXISTS llx_pech_lot (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    ref VARCHAR(100) NOT NULL,                     -- Référence unique du lot
    fk_user_create INT DEFAULT NULL,               -- Utilisateur ayant créé le lot
    fk_entrepot INT DEFAULT NULL,                  -- Entrepôt ou unité de production
    fk_bonentree INT DEFAULT NULL,
    source_type TINYINT NOT NULL DEFAULT 0,
    fk_fourn INT DEFAULT NULL,
    fk_facture_fourn INT DEFAULT NULL,
    fk_facture INT DEFAULT NULL,
    total_frais DECIMAL(20,2) DEFAULT 0,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    commentaire TEXT DEFAULT NULL,                 -- Commentaire ou note de production
    statut TINYINT DEFAULT 0,                      -- 0=brouillon, 1=validé, 2=fermé
    entity INT DEFAULT 1,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ====================================
-- TABLE LOTDET (Détail de chaque lot)
-- ====================================
CREATE TABLE IF NOT EXISTS llx_pech_lotdet (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_lot INT NOT NULL,                           -- Référence du lot
    fk_product INT NOT NULL,                       -- Produit concerné
    fk_misenplat INT ,
    source_type TINYINT DEFAULT 0,
    poids_carton DECIMAL(20,3) DEFAULT 0,          -- Poids total du carton
    plat_carton INT DEFAULT 0,
    nb_carton INT DEFAULT 0,                       -- Nombre de cartons produits
    taux_rendement DECIMAL(10,2) DEFAULT 0,        -- % rendement (poids_carton / poids_source * 100)
    commentaire TEXT DEFAULT NULL,                 -- Notes sur la ligne
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut TINYINT DEFAULT 0,                      -- 0=brouillon, 1=validé
    entity INT DEFAULT 1,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS llx_pech_lotdet_mixte_misenplat (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_lotdet_mixte INT NOT NULL,
    fk_misenplat INT NOT NULL,
    fk_product int DEFAULT NULL,
    nb_plat INT NOT NULL
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
-- ===============================
-- TABLE CARTON (Cartons produits)
-- ===============================
CREATE TABLE IF NOT EXISTS llx_pech_carton (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_lotdet INT NOT NULL,                        -- Ligne de lot correspondante
    fk_product INT DEFAULT NULL,                   -- Produit final (poisson transformé)
     
    poids DECIMAL(20,3) DEFAULT 0,                 -- Poids d’un carton
    nb_plat INT DEFAULT 0,                         -- Nombre de plats dans le carton
    ref_carton VARCHAR(100) DEFAULT NULL,          -- Référence unique du carton (optionnelle)
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    fk_user_create INT DEFAULT NULL,               -- Utilisateur ayant créé l’enregistrement
    statut TINYINT DEFAULT 0,                      -- 0=brouillon, 1=validé, 2=expédié
    prix_moyen DECIMAL(20,4) DEFAULT 0,
    frais DECIMAL(20,4) NOT NULL DEFAULT 0,
    commentaire TEXT DEFAULT NULL,
    entity INT DEFAULT 1,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



















CREATE TABLE IF NOT EXISTS llx_pech_bonentree (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    ref VARCHAR(100) NOT NULL,                    -- Référence du bon
    fk_user_create INT DEFAULT NULL,              -- Utilisateur créateur
    fk_entrepot INT DEFAULT NULL,                 -- Entrepôt d’entrée
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    commentaire TEXT DEFAULT NULL,
    statut TINYINT DEFAULT 0,                     -- 0=brouillon, 1=validé, 2=clos
    entity INT DEFAULT 1,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS llx_pech_bonentree_detprod (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_bonentree INT NOT NULL,                   -- Bon d’entrée parent
    fk_product INT DEFAULT NULL,                 -- Produit stockable
    nb_carton INT DEFAULT 0,                     -- Nombre de cartons
    poids_carton DECIMAL(20,3) DEFAULT 0,        -- Poids par carton
    total_poids DECIMAL(20,3) GENERATED ALWAYS AS (nb_carton * poids_carton) STORED,
    commentaire TEXT DEFAULT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut TINYINT DEFAULT 0,                    -- 0=brouillon, 1=validé
    entity INT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE IF NOT EXISTS llx_pech_bonentree_detserv (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_bonentree INT NOT NULL,                   -- Bon d’entrée parent
    description VARCHAR(255) NOT NULL,           -- Description du service
    qte DECIMAL(20,3) DEFAULT 1,                 -- Quantité
    pu DECIMAL(20,3) DEFAULT 0,                  -- Prix unitaire
    total DECIMAL(20,3) GENERATED ALWAYS AS (qte * pu) STORED,
    commentaire TEXT DEFAULT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut TINYINT DEFAULT 0,                    -- 0=brouillon, 1=validé
    entity INT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS llx_pech_sortie (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    ref VARCHAR(100) NOT NULL,                -- Référence de sortie
    type TINYINT NOT NULL DEFAULT 1,          -- 0 = transfert interne, 1 = vente
    fk_entrepot_source INT NOT NULL,          -- Entrepôt source
    fk_entrepot_dest INT DEFAULT NULL,        -- Entrepôt destination si type = 0
    fk_client INT DEFAULT NULL,               -- Client si type = 1
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    fk_user INT DEFAULT NULL,                 -- Utilisateur qui crée la sortie
    commentaire TEXT DEFAULT NULL,
    statut TINYINT DEFAULT 0,                 -- 0=brouillon, 1=validé, 2=annulé
    poids_total DECIMAL(20,3) DEFAULT 0,
    nb_carton_total INT DEFAULT 0,
    fk_facture INT DEFAULT NULL,
    fk_bonsortie INT DEFAULT NULL,
    fk_facture_client INT DEFAULT NULL,
    total_frais DECIMAL(20,2) DEFAULT 0,
    entity INT DEFAULT 1,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS llx_pech_sortiedetprod (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_sortie INT NOT NULL,                   -- Lien vers llx_pech_sortie
    fk_product INT NOT NULL,                  -- Produit concerné
    nb_carton INT DEFAULT 0,                  -- Nombre de cartons sortis
    poids_total DECIMAL(20,3) DEFAULT 0,      -- Poids total pour ce produit
    pu DECIMAL(20,3) DEFAULT 0,               -- Prix unitaire (par kg ou autre unité)
    total_line DECIMAL(20,3) GENERATED ALWAYS AS (pu * poids_total) STORED, -- Total = PU * poids_total
    commentaire TEXT DEFAULT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut TINYINT DEFAULT 0,                 -- 0 = brouillon, 1 = validé
    entity INT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS llx_pech_sortiedetcarton (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_sortiedetprod INT NOT NULL,           -- Lien vers la ligne produit
    fk_carton INT NOT NULL,                   -- Carton concerné
    poids DECIMAL(20,3) DEFAULT 0,           -- Poids du carton
    commentaire TEXT DEFAULT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut TINYINT DEFAULT 0,
    entity INT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS llx_pech_sortiedetservice (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_sortie INT NOT NULL,                -- Lien vers la sortie principale
    description VARCHAR(255) NOT NULL,     -- Libellé du service
    qty DECIMAL(10,2) DEFAULT 1,           -- Quantité du service
    pu DECIMAL(20,3) DEFAULT 0,            -- Prix unitaire du service
    total DECIMAL(20,3) GENERATED ALWAYS AS (qty * pu) STORED,  -- Total automatique
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    fk_user INT DEFAULT NULL,              -- Utilisateur qui ajoute la ligne
    statut TINYINT DEFAULT 0,              -- 0=brouillon, 1=validé
    entity INT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS llx_pech_bonsortie(
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    ref VARCHAR(100) NOT NULL,                    -- Référence du bon
    fk_user_create INT DEFAULT NULL,              -- Utilisateur créateur
    fk_entrepot_source INT DEFAULT NULL,                 -- Entrepôt d’entrée
    fk_entrepot_dest INT DEFAULT NULL,                 -- Entrepôt d’entrée
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    commentaire TEXT DEFAULT NULL,
    statut TINYINT DEFAULT 0,                     -- 0=brouillon, 1=validé, 2=clos
    entity INT DEFAULT 1,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS llx_pech_bonsortie_detprod (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_bonentree INT NOT NULL,                   -- Bon d’entrée parent
    fk_product INT DEFAULT NULL,                 -- Produit stockable
    nb_carton INT DEFAULT 0,                     -- Nombre de cartons
    poids_carton DECIMAL(20,3) DEFAULT 0,        -- Poids par carton
    total_poids DECIMAL(20,3) GENERATED ALWAYS AS (nb_carton * poids_carton) STORED,
    valeur DECIMAL(20,4) DEFAULT 0, 
    commentaire TEXT DEFAULT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut TINYINT DEFAULT 0,                    -- 0=brouillon, 1=validé
    entity INT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE IF NOT EXISTS llx_pech_bonsortie_detserv (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_bonentree INT NOT NULL,                   -- Bon d’entrée parent
    description VARCHAR(255) NOT NULL,           -- Description du service
    qte DECIMAL(20,3) DEFAULT 1,                 -- Quantité
    pu DECIMAL(20,3) DEFAULT 0,                  -- Prix unitaire
    total DECIMAL(20,3) GENERATED ALWAYS AS (qte * pu) STORED,
    commentaire TEXT DEFAULT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut TINYINT DEFAULT 0,                    -- 0=brouillon, 1=validé
    entity INT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS llx_user_entrepot (
    rowid INT AUTO_INCREMENT PRIMARY KEY,

    fk_user INT NOT NULL,                -- ID de l'utilisateur
    fk_entrepot INT NOT NULL,            -- ID de l'entrepôt

    role VARCHAR(50) DEFAULT 'user',     -- Rôle dans l'entrepôt : admin, superviseur, agent, etc.
    can_edit TINYINT(1) DEFAULT 0,       -- Permission spéciale d'édition
    can_view TINYINT(1) DEFAULT 1,       -- Permission de lecture

    entity INT DEFAULT 1,                -- Support multi-entreprise Dolibarr
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_user_entrepot (fk_user, fk_entrepot),

    CONSTRAINT fk_user_entrepot_user FOREIGN KEY (fk_user) 
        REFERENCES llx_user(rowid) ON DELETE CASCADE,

    CONSTRAINT fk_user_entrepot_entrepot FOREIGN KEY (fk_entrepot) 
        REFERENCES llx_entrepot(rowid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS llx_user_bank (
    rowid INT(11) NOT NULL AUTO_INCREMENT,
    fk_user INT(11) NOT NULL,
    fk_bank INT(11) NOT NULL,
    entity INT(11) NOT NULL DEFAULT 1,
    PRIMARY KEY (rowid),
    UNIQUE KEY uk_user_bank (fk_user, fk_bank)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


-- Pour filtrer rapidement les lignes du lot
CREATE INDEX idx_lotdet_fk_lot 
ON llx_pech_lotdet (fk_lot);

-- Pour accélérer le JOIN + filtre statut
CREATE INDEX idx_carton_lotdet_statut 
ON llx_pech_carton (fk_lotdet, statut);



CREATE INDEX idx_carton_fk_lotdet2 ON llx_pech_carton (fk_lotdet);
CREATE INDEX idx_plat_fk_carton ON llx_pech_plat (fk_carton);
CREATE INDEX idx_misenplat_fk_bon ON llx_pech_misenplat (fk_bon_misenplat);
