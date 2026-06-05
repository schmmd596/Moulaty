-- WomaPech - SQL de création des tables de réception
-- Remplacez le préfixe "llx_" par votre MAIN_DB_PREFIX si nécessaire.
CREATE TABLE IF NOT EXISTS llx_pech_reception (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    ref VARCHAR(100) Not NULL,
    fk_fournisseur INT NOT NULL,
    fk_congelateur INT DEFAULT NULL,
    fk_entrepot INT DEFAULT NULL,
    reception_mode TINYINT NOT NULL DEFAULT 3, -- 1=Voiture, 2=Poids Brut, 3=Poids Net
    calibre VARCHAR(50) DEFAULT NULL,         -- Calibre du poisson
    date_creation DATETIME NOT NULL,
    comment TEXT DEFAULT NULL,
    traitement TINYINT DEFAULT 0,             -- 0=Traitement, 1=Local
    montant DECIMAL(20,2) DEFAULT 0,         -- Montant total de la réception
    user_create INT DEFAULT NULL,             -- Utilisateur qui a créé la réception
    etat TINYINT DEFAULT 0,                   -- 0=En attente, 1=Validé, 2=Annulé
    fk_facture INT DEFAULT NULL,              -- Lien vers facture si générée
    poids DECIMAL(20,2) DEFAULT 0,           -- Total calculé automatiquement
    entity INT NOT NULL DEFAULT 1,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)  ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS llx_pech_receptiondet (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_reception INT NOT NULL,
    fk_product INT NOT NULL,
    
    -- Colonnes pour mode voiture
    nb_voiture INT DEFAULT 0,
    prix_voiture DECIMAL(20,2) DEFAULT 0,
    
    -- Colonnes pour poids brut
    poids_brut DECIMAL(20,2) DEFAULT 0,
    pu_brut DECIMAL(20,2) DEFAULT 0,
    
    -- Colonnes pour poids net
    poids_net DECIMAL(20,2) DEFAULT 0,
    pu_poids_accepte DECIMAL(20,2) DEFAULT 0,
    
    total_line DECIMAL(20,2) DEFAULT 0,
    
    entity INT NOT NULL DEFAULT 1,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (fk_reception) REFERENCES llx_pech_reception(rowid) ON DELETE CASCADE,
    FOREIGN KEY (fk_product) REFERENCES llx_product(rowid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
 fk_conjulateur
CREATE TABLE IF NOT EXISTS llx_pech_misenplat (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_congelateur INT NOT NULL,                 -- Fournisseur conjulateur
    fk_reception INT NOT NULL,                   -- Référence de la réception
    fk_reception_det INT DEFAULT NULL,           -- Détail de la réception
    nombre_plat INT DEFAULT 0,                   -- Nombre total de plats
    poids_plat DOUBLE(24,8) DEFAULT 0,           -- Poids d’un plat individuel
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP, -- Date de création
    statut TINYINT DEFAULT 0,                    -- Statut (0=brouillon,1=validé,etc.)
    fk_bon_entree INT DEFAULT NULL,              -- Bon d’entrée lié
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP, -- Dernière modif
    pointeur VARCHAR(100) DEFAULT NULL,          -- Identifiant ou traceur
    commentaire TEXT                             -- Commentaires
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE IF NOT EXISTS llx_pech_plat (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_misenplat INT NOT NULL,                   -- Lien vers la mise en plat
    fk_prod INT DEFAULT NULL,                    -- Produit concerné
    fk_carton INT DEFAULT NULL,                  -- Carton associé
    poids DOUBLE(24,8) DEFAULT 0,                -- Poids du plat
    statut TINYINT DEFAULT 0,                    -- Statut du plat
    commentaire TEXT,                            -- Commentaires libres
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
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
    fk_misenplat VARCHAR(100)  NULL,               -- l'ensemble des misenplat
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    commentaire TEXT DEFAULT NULL,                 -- Commentaire ou note de production
    statut TINYINT DEFAULT 0,                      -- 0=brouillon, 1=validé, 2=fermé
    entity INT DEFAULT 1,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE (ref),
    FOREIGN KEY (fk_user_create) REFERENCES llx_user(rowid),
    FOREIGN KEY (fk_entrepot) REFERENCES llx_entrepot(rowid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ====================================
-- TABLE LOTDET (Détail de chaque lot)
-- ====================================
CREATE TABLE IF NOT EXISTS llx_pech_lotdet (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_lot INT NOT NULL,                           -- Référence du lot
    fk_product INT NOT NULL,                       -- Produit concerné
    fk_misenplat INT NOT NULL ,
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
    commentaire TEXT DEFAULT NULL,
    entity INT DEFAULT 1,
    tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


CREATE TABLE IF NOT EXISTS llx_pech_bonentree (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    ref VARCHAR(50) NOT NULL,                 -- Exemple : BET-2025-0001
    fk_user INT DEFAULT NULL,                 -- Utilisateur ayant créé le bon
    commentaire TEXT,                         -- Commentaire général du bon
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    statut INT DEFAULT 0,                     -- 0=Brouillon, 1=Validé, etc.
    entity INT DEFAULT 1,                     -- Multi-entité Dolibarr
    UNIQUE(ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS llx_pech_bonentree_det (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_bonentree INT NOT NULL,                -- Lien vers le bon principal
    fk_soc INT DEFAULT NULL,                  -- conjulateur fournisseur (société)
    fk_reception INT DEFAULT NULL,            -- Réception liée
    fk_misenplat INT DEFAULT NULL,        -- Mise en plat liée
    fk_prod INT DEFAULT NULL,             -- produit liée
    fk_entrepot INT DEFAULT NULL,             -- Entrepôt concerné
    nombre_plat INT DEFAULT 0,                -- Nombre de plats
    poids_total DECIMAL(10,2) DEFAULT 0,      -- Poids total
    commentaire TEXT,                         -- Commentaire spécifique à la ligne
    date_ligne DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (fk_bonentree) REFERENCES llx_womapech_bonentree(rowid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
