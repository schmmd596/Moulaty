<?php
/* Copyright (C) 2025 Abdou Mahfoudh
 *
 * Module descriptor for Womapeche
 */

include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

//include_once DOL_DOCUMENT_ROOT . '/core/modules/DolibarrModules.class.php';

class modMoulatyPeche extends DolibarrModules
{
    public function __construct($db)
    {
        global $langs, $conf;

        $this->db = $db;

        $this->numero = 105000;   // ID unique pour ce module
        $this->rights_class = 'moulatyPeche';
        $this->family = "other";
        $this->module_position = 500;
        $this->name = preg_replace('/^mod/i','',get_class($this));
        $this->description = "Module de gestion des poissons";
        $this->version = '1.0';
        $this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);
        $this->special = 0;
        $this->picto='fa-fish';

        // Répertoires de données
        $this->dirs = array("/moulatyPeche/temp");

        // Fichiers de langue
        $this->langfiles = array("moulatyPeche@moulatyPeche");

        // Permissions
        $this->rights = array();
        $this->rights_class = 'moulatyPeche';
        $r=0;
        $this->rights[$r][0] = 105001;
        $this->rights[$r][1] = 'Lire le module MoulatyPeche';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'read';
        $r++;
        $this->rights[$r][0] = 105002;
        $this->rights[$r][1] = 'Valider les Receptions les mien plat le cartonnage et la sortie';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'read_r';
        $r++;

        $this->rights[$r][0] = 105003;
        $this->rights[$r][1] = 'creer de bon et des facture ';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'read_b';
        $r++;
        
        $this->rights[$r][0] = 105004;
        $this->rights[$r][1] = 'Modifier les devises ';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'read_d';
        $r++;

        // Menus
        $this->menu = array();
global $langs;
$langs->load("moulatyPeche@moulatyPeche");
        // Menu principal "Gestion des poissons"
        $this->menu[] = array(
            'fk_menu'   => '',
            'type'      => 'top',
            'titre'     => 'MenuGestionPoissons',
            'mainmenu'  => 'moulatyPeche',
            'leftmenu'  => '',
            'prefix'    => img_picto('', $this->picto, 'class="pictofixedwidth valignmiddle"'),
            'url'       => '/custom/moulatyPeche/index.php',
            'langs'     => 'moulatyPeche@moulatyPeche',
            'position'  => 100,
            'enabled'   => '1',
            'perms'     => '$user->rights->moulatyPeche->read'
        );

        /** -------------------------
 *  MENU RECEPTION
 * ------------------------- */
$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche',
    'type'      => 'left',
    'titre'     => 'MenuReception',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'reception',
    'url'       => '/custom/moulatyPeche/Reception/index.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'prefix'    => '<i class="fa fa-truck"></i> ',
    'position'  => 101,
    'enabled'   => '1',
    'perms'     => '1'
);

$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche,fk_leftmenu=reception',
    'type'      => 'left',
    'titre'     => 'MenuReceptionNew',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'reception_new',
    'url'       => '/custom/moulatyPeche/Reception/card.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 102,
    'enabled'   => '1',
    'perms'     => '1'
);

$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche,fk_leftmenu=reception',
    'type'      => 'left',
    'titre'     => 'MenuReceptionList',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'reception_list',
    'url'       => '/custom/moulatyPeche/Reception/list.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 103,
    'enabled'   => '1',
    'perms'     => '1'
);

/** -------------------------
 *  MENU MIS EN PLAT
 * ------------------------- */
$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche',
    'type'      => 'left',
    'titre'     => 'MenuMisenplat',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'Misenplat',
    'url'       => '/custom/moulatyPeche/misenplat/index.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'prefix'    => '<i class="fa fa-utensils"></i> ',
    'position'  => 111,
    'enabled'   => '1',
    'perms'     => '1'
);

$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche,fk_leftmenu=Misenplat',
    'type'      => 'left',
    'titre'     => 'MenuMisenplatNew',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'Misenplat_new',
    'url'       => '/custom/moulatyPeche/misenplat/nouveau.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 112,
    'enabled'   => '1',
    'perms'     => '1'
);

$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche,fk_leftmenu=Misenplat',
    'type'      => 'left',
    'titre'     => 'MenuMisenplatList',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'Misenplat_list',
    'url'       => '/custom/moulatyPeche/misenplat/list.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 113,
    'enabled'   => '1',
    'perms'     => '1'
);


/** -------------------------
 *  MENU CARTONNAGE
 * ------------------------- */
$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche',
    'type'      => 'left',
    'titre'     => 'MenuCartonnage',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'Congelation',
    'url'       => '/custom/moulatyPeche/Lots/index.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'prefix'    => '<i class="fa fa-box"></i> ',
    'position'  => 121,
    'enabled'   => '1',
    'perms'     => '1'
);

$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche,fk_leftmenu=Congelation',
    'type'      => 'left',
    'titre'     => 'MenuCartonnageNew',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'Congelation_new',
    'url'       => '/custom/moulatyPeche/tunnel/tunnel.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 122,
    'enabled'   => '1',
    'perms'     => '1'
);

$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche,fk_leftmenu=Congelation',
    'type'      => 'left',
    'titre'     => 'MenuCartonnageDirect',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'Congelation_new2',
    'url'       => '/custom/moulatyPeche/Lots/recep_cart.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 123,
    'enabled'   => '1',
    'perms'     => '1'
);

$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche,fk_leftmenu=Congelation',
    'type'      => 'left',
    'titre'     => 'MenuCartonnageList',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'lots',
    'url'       => '/custom/moulatyPeche/Lots/list.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 124,
    'enabled'   => '1',
    'perms'     => '1'
);

$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche,fk_leftmenu=Congelation',
    'type'      => 'left',
    'titre'     => 'EtatDuStock',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'lots4',
    'url'       => '/custom/moulatyPeche/Lots/etat_stock2.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 125,
    'enabled'   => '1',
    'perms'     => '1'
);

/** -------------------------
 *  MENU SORTIE
 * ------------------------- */
$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche',
    'type'      => 'left',
    'titre'     => 'MenuSortie',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'Sortie',
    'url'       => '/custom/moulatyPeche/sortie/index.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'prefix'    => '<i class="fa fa-shipping-fast"></i> ',
    'position'  => 131,
    'enabled'   => '1',
    'perms'     => '1'
);

$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche,fk_leftmenu=Sortie',
    'type'      => 'left',
    'titre'     => 'MenuSortieEntrepot',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'Sortie_entrepot',
    'url'       => '/custom/moulatyPeche/sortie/sortie_card.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 132,
    'enabled'   => '1',
    'perms'     => '1'
);

$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche,fk_leftmenu=Sortie',
    'type'      => 'left',
    'titre'     => 'MenuSortieClient',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'Sortie_client',
    'url'       => '/custom/moulatyPeche/sortie/sortie_client.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 133,
    'enabled'   => '1',
    'perms'     => '1'
);

$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche,fk_leftmenu=Sortie',
    'type'      => 'left',
    'titre'     => 'SimilationSortie',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'Sortie_list2',
    'url'       => '/custom/moulatyPeche/sortie/simulateur_sortie.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 134,
    'enabled'   => '1',
    'perms'     => '1'
);

$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche,fk_leftmenu=Sortie',
    'type'      => 'left',
    'titre'     => 'MenuSortieList',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'Sortie_list',
    'url'       => '/custom/moulatyPeche/sortie/list.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 135,
    'enabled'   => '1',
    'perms'     => '1'
);

/** -------------------------
 *  MENU ROLES & UTILISATEURS
 * ------------------------- */
$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche',
    'type'      => 'left',
    'titre'     => 'MenuRoles',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'roles',
    'url'       => '/custom/moulatyPeche/roles/index.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'prefix'    => '<i class="fa fa-key"></i> ',
    'position'  => 151,
    'enabled'   => '1',
    'perms'     => '$user->admin'
);

$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche,fk_leftmenu=roles',
    'type'      => 'left',
    'titre'     => 'MenuUserEntrepot',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'user_entrepot',
    'url'       => '/custom/moulatyPeche/roles/users_entrepot.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 152,
    'enabled'   => '1',
    'perms'     => '$user->admin'
);

$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche,fk_leftmenu=roles',
    'type'      => 'left',
    'titre'     => 'MenuUserBank',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'user_bank',
    'url'       => '/custom/moulatyPeche/roles/user_bank.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 153,
    'enabled'   => '1',
    'perms'     => '$user->admin'
);



$this->menu[] = array(
    'fk_menu'   => 'fk_mainmenu=moulatyPeche',
    'type'      => 'left',
    'titre'     => 'Devise',
    'mainmenu'  => 'moulatyPeche',
    'leftmenu'  => 'Devise',
    'url'       => '/custom/moulatyPeche/devises/card.php',
    'langs'     => 'moulatyPeche@moulatyPeche',
    'position'  => 163,
    'enabled'   => '1',
    'perms'     => '$user->rights->moulatyPeche->read_d'
);

}



    public function init($options = '')
{
    $sql = array();

    // Table pech_reception
    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_reception (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        ref VARCHAR(100) NOT NULL,
        fk_fournisseur INT NOT NULL,
        fk_congelateur INT DEFAULT NULL,
        fk_entrepot INT DEFAULT NULL,
        date_creation DATETIME NOT NULL,
        comment TEXT DEFAULT NULL,
        montant DECIMAL(20,2) DEFAULT 0,
        frais DECIMAL(20,2) DEFAULT 0,
        user_create INT DEFAULT NULL,
        etat TINYINT DEFAULT 0,
        fk_facture INT DEFAULT NULL,
        fk_bon_recep INT DEFAULT NULL,
        poids DECIMAL(20,2) DEFAULT 0,
        entity INT NOT NULL DEFAULT 1,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    // Table pech_receptiondet
    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_receptiondet (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        fk_reception INT NOT NULL,
        fk_product INT NOT NULL,
        reception_mode TINYINT NOT NULL DEFAULT 3,
        calibre VARCHAR(50) DEFAULT NULL,
        nb_voiture INT DEFAULT 0,
        prix_voiture DECIMAL(20,2) DEFAULT 0,
        poids_brut DECIMAL(20,2) DEFAULT 0,
        pu_brut DECIMAL(20,2) DEFAULT 0,
        poids_net DECIMAL(20,2) DEFAULT 0,
        pu_poids_net DECIMAL(20,2) DEFAULT 0,
        total_line DECIMAL(20,2) DEFAULT 0,
        prix_moyen DECIMAL(20,4) DEFAULT 0,
        entity INT NOT NULL DEFAULT 1,
        poids_plater DECIMAL(20,2) DEFAULT 0,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    // Table pech_bonreception
    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_bonreception (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        ref VARCHAR(100) NOT NULL,
        fk_reception INT DEFAULT NULL,
        fk_fournisseur INT DEFAULT NULL,
        fk_congelateur INT DEFAULT NULL,
        date_creation DATETIME NOT NULL,
        user_create INT DEFAULT NULL,
        comment TEXT DEFAULT NULL,
        total DECIMAL(20,2) DEFAULT 0,
        poids DECIMAL(20,2) DEFAULT 0,
        entity INT NOT NULL DEFAULT 1,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    // Table pech_bonreceptiondet
    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_bonreceptiondet (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        fk_bonreception INT NOT NULL,
        description VARCHAR(1000) NOT NULL,
        qte INT DEFAULT 0,
        PU DECIMAL(20,2) DEFAULT 0,
        total_line DECIMAL(20,2) DEFAULT 0,
        entity INT NOT NULL DEFAULT 1,
        type INT NOT NULL DEFAULT 0,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    // Table pech_bon_misenplat
    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_bon_misenplat (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        ref VARCHAR(100) NOT NULL,
        fk_entrepot INT NOT NULL,
        fk_receptiondet VARCHAR(255) NULL,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        fk_user INT DEFAULT NULL,
        total_frais DECIMAL(20,2) DEFAULT 0,
        fk_facture_frais int null,
        statut TINYINT DEFAULT 0,
        commentaire TEXT,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    // Table pech_misenplat
    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_misenplat (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        fk_bon_misenplat INT NOT NULL,
        fk_congelateur INT NOT NULL,
        fk_product INT DEFAULT NULL,
        nombre_plat INT DEFAULT 0,
        nombre_plat_sortie INT DEFAULT 0,
        poids_plat DOUBLE(24,8) DEFAULT 0,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        statut TINYINT DEFAULT 0,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        pointeur VARCHAR(100) DEFAULT NULL,
        commentaire TEXT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    // Table pech_plat
    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_plat (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        fk_misenplat INT NOT NULL,
        fk_product INT DEFAULT NULL,
        fk_carton INT DEFAULT NULL,
        poids DOUBLE(24,4) DEFAULT 0,
        prix_moyen DECIMAL(20,4) DEFAULT 0,             -- prix moyen du plat
        frais DECIMAL(20,4) NOT NULL DEFAULT 0,

        statut TINYINT DEFAULT 0,
        commentaire TEXT,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
     $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_bon_misenplatdet (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        fk_bon_misenplat INT NOT NULL,              -- Référence vers bon de réception
        description VARCHAR(1000) not NULL,    

        qte INT DEFAULT 0,
        PU DECIMAL(20,2) DEFAULT 0,


        total_line DECIMAL(20,2) DEFAULT 0,        -- Montant total de la ligne
        entity INT NOT NULL DEFAULT 1,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    // Table pech_lot
    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_lot (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        ref VARCHAR(100) NOT NULL,
        fk_user_create INT DEFAULT NULL,
        fk_entrepot INT DEFAULT NULL,
        fk_bonentree INT DEFAULT NULL,
        source_type TINYINT NOT NULL DEFAULT 0,
        fk_fourn INT DEFAULT NULL,
        fk_facture_fourn INT DEFAULT NULL,
        fk_facture INT DEFAULT NULL,
        total_frais DECIMAL(20,4) DEFAULT 0,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        commentaire TEXT DEFAULT NULL,
        statut TINYINT DEFAULT 0,
        entity INT DEFAULT 1,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";


    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat (
    rowid INT AUTO_INCREMENT PRIMARY KEY,
    fk_lotdet_mixte INT NOT NULL,
    fk_misenplat INT NOT NULL,
    fk_product int DEFAULT NULL,
    nb_plat INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
    // Table pech_lotdet
    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_lotdet (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        fk_lot INT NOT NULL,
        fk_product INT NOT NULL,
        fk_misenplat INT NOT NULL,
        poids_carton DECIMAL(20,3) DEFAULT 0,
        plat_carton INT DEFAULT 0,
        nb_carton INT DEFAULT 0,
        source_type TINYINT DEFAULT 0,
        prix DECIMAL(20,2) DEFAULT 0,
        taux_rendement DECIMAL(10,2) DEFAULT 0,
        commentaire TEXT DEFAULT NULL,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        statut TINYINT DEFAULT 0,
        entity INT DEFAULT 1,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    // Table pech_carton
    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_carton (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        fk_lotdet INT NOT NULL,
        fk_product INT DEFAULT NULL,
        poids DECIMAL(20,3) DEFAULT 0,
        nb_plat INT DEFAULT 0,
        ref_carton VARCHAR(100) DEFAULT NULL,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        fk_user_create INT DEFAULT NULL,
        prix_moyen DECIMAL(20,4) DEFAULT 0,
        frais DECIMAL(20,4)  DEFAULT 0,
        statut TINYINT DEFAULT 0,
        commentaire TEXT DEFAULT NULL,
        entity INT DEFAULT 1,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    // Table pech_bonentree
    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_bonentree (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        ref VARCHAR(100) NOT NULL,
        fk_user_create INT DEFAULT NULL,
        fk_entrepot INT DEFAULT NULL,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        commentaire TEXT DEFAULT NULL,
        statut TINYINT DEFAULT 0,
        entity INT DEFAULT 1,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_bonentree_detprod (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        fk_bonentree INT NOT NULL,
        fk_product INT DEFAULT NULL,
        nb_carton INT DEFAULT 0,
        poids_carton DECIMAL(20,3) DEFAULT 0,
        total_poids DECIMAL(20,3) GENERATED ALWAYS AS (nb_carton * poids_carton) STORED,
        commentaire TEXT DEFAULT NULL,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        statut TINYINT DEFAULT 0,
        entity INT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_bonentree_detserv (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        fk_bonentree INT NOT NULL,
        description VARCHAR(255) NOT NULL,
        qte DECIMAL(20,3) DEFAULT 1,
        pu DECIMAL(20,3) DEFAULT 0,
        total DECIMAL(20,3) GENERATED ALWAYS AS (qte * pu) STORED,
        commentaire TEXT DEFAULT NULL,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        statut TINYINT DEFAULT 0,
        entity INT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    // Table pech_sortie
    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_sortie (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        ref VARCHAR(100) NOT NULL,
        type TINYINT NOT NULL DEFAULT 1,
        fk_entrepot_source INT NOT NULL,
        fk_entrepot_dest INT DEFAULT NULL,
        fk_client INT DEFAULT NULL,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        fk_user INT DEFAULT NULL,
        commentaire TEXT DEFAULT NULL,
        statut TINYINT DEFAULT 0,
        poids_total DECIMAL(20,3) DEFAULT 0,
        nb_carton_total INT DEFAULT 0,
        fk_facture INT DEFAULT NULL,
        fk_bonsortie INT DEFAULT NULL,
        fk_facture_client INT DEFAULT NULL,
        total_frais DECIMAL(20,4) DEFAULT 0,
        entity INT DEFAULT 1,
        tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_sortiedetprod (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        fk_sortie INT NOT NULL,
        fk_product INT NOT NULL,
        nb_carton INT DEFAULT 0,
        poids_total DECIMAL(20,3) DEFAULT 0,
        pu DECIMAL(20,3) DEFAULT 0,
        total_line DECIMAL(20,3) GENERATED ALWAYS AS (pu * poids_total) STORED,
        commentaire TEXT DEFAULT NULL,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        statut TINYINT DEFAULT 0,
        entity INT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_sortiedetcarton (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        fk_sortiedetprod INT NOT NULL,
        fk_carton INT NOT NULL,
        poids DECIMAL(20,3) DEFAULT 0,
        commentaire TEXT DEFAULT NULL,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        statut TINYINT DEFAULT 0,
        entity INT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";

    $sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_sortiedetservice (
        rowid INT AUTO_INCREMENT PRIMARY KEY,
        fk_sortie INT NOT NULL,
        description VARCHAR(255) NOT NULL,
        qty DECIMAL(10,2) DEFAULT 1,
        pu DECIMAL(20,3) DEFAULT 0,
        total DECIMAL(20,3) GENERATED ALWAYS AS (qty * pu) STORED,
        date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
        fk_user INT DEFAULT NULL,
        statut TINYINT DEFAULT 0,
        entity INT DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
     
$sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_bonsortie(
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_bonsortie_detprod (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";


$sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."pech_bonsortie_detserv (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

$sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."user_entrepot (
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
        REFERENCES ".MAIN_DB_PREFIX."user(rowid) ON DELETE CASCADE,

    CONSTRAINT fk_user_entrepot_entrepot FOREIGN KEY (fk_entrepot) 
        REFERENCES ".MAIN_DB_PREFIX."entrepot(rowid) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";


$sql[] = "CREATE TABLE IF NOT EXISTS ".MAIN_DB_PREFIX."user_bank (
    rowid INT(11) NOT NULL AUTO_INCREMENT,
    fk_user INT(11) NOT NULL,
    fk_bank INT(11) NOT NULL,
    entity INT(11) NOT NULL DEFAULT 1,
    PRIMARY KEY (rowid),
    UNIQUE KEY uk_user_bank (fk_user, fk_bank)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";

    // --- Exécution des requêtes SQL ---
    foreach ($sql as $query) {
        $resql = $this->db->query($query);
        if (!$resql) {
            dol_syslog(__METHOD__." SQL Error: ".$this->db->lasterror(), LOG_ERR);
            return -1;
        }
    }

    // --- Création des répertoires de données ---
    if (!empty($this->dirs)) {
        foreach ($this->dirs as $dir) {
            $this->createDataDir($dir);
        }
    }

    return parent::init($options);
}

/* Helper pour créer un dossier dans DOL_DATA_ROOT */
protected function createDataDir($relpath)
{
    $path = DOL_DATA_ROOT . $relpath;
    if (!file_exists($path)) {
        @mkdir($path, 0755, true);
    }
}

}
