<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $user, $langs, $conf;
$langs->loadLangs(['stocks', 'main', 'womapeche@womapeche']);

// Protection CSRF
if (GETPOST('token', 'alpha') !== $_SESSION['newtoken']) {
    setEventMessages("Token CSRF invalide.", null, 'errors');
    header("Location: list.php");
    exit;
}

$selected_sorties = GETPOST('selected_sorties', 'array');

if (empty($selected_sorties) || count($selected_sorties) < 2) {
    setEventMessages("Veuillez sélectionner au moins deux sorties à regrouper.", null, 'errors');
    header("Location: list.php");
    exit;
}

$db->begin();

try {
    $sorties = array();
    $first_type = null;
    $first_client = null;
    $first_dest = null;
    
    // 1️⃣ Charger et valider les sorties sélectionnées
    foreach ($selected_sorties as $id) {
        $id = (int)$id;
        $res = $db->query("SELECT * FROM " . MAIN_DB_PREFIX . "pech_sortie WHERE rowid = " . $id);
        if (!$res || $db->num_rows($res) == 0) {
            throw new Exception("Sortie ID $id introuvable.");
        }
        $sortie = $db->fetch_object($res);
        if ($sortie->is_regroupe) {
            throw new Exception("La sortie " . $sortie->ref . " est déjà regroupée.");
        }
        
        if ($first_type === null) {
            $first_type = $sortie->type;
            $first_client = $sortie->fk_client;
            $first_dest = $sortie->fk_entrepot_dest;
        } else {
            if ($sortie->type !== $first_type) {
                throw new Exception("Toutes les sorties doivent être du même type.");
            }
            if ($first_type == 1 && $sortie->fk_client !== $first_client) {
                throw new Exception("Toutes les sorties de vente doivent avoir le même client.");
            }
            if ($first_type == 0 && $sortie->fk_entrepot_dest !== $first_dest) {
                throw new Exception("Toutes les sorties de transfert doivent avoir le même entrepôt de destination.");
            }
        }
        $sorties[] = $sortie;
    }
    
    // 2️⃣ Générer une référence pour le bon de sortie regroupé
    $resLast = $db->query("SELECT ref FROM " . MAIN_DB_PREFIX . "pech_bonsortie WHERE ref LIKE 'BS-REG-%' ORDER BY rowid DESC LIMIT 1");
    $nextNum = 1;
    if ($resLast && $db->num_rows($resLast) > 0) {
        $obj = $db->fetch_object($resLast);
        if (preg_match('/BS-REG-(\d+)/', $obj->ref, $m)) {
            $nextNum = (int)$m[1] + 1;
        }
    }
    $ref_bon = 'BS-REG-' . str_pad($nextNum, 6, '0', STR_PAD_LEFT);
    
    // 3️⃣ Créer le bon de sortie unique
    $sql_bon = "INSERT INTO " . MAIN_DB_PREFIX . "pech_bonsortie (
                    ref, fk_user_create, fk_entrepot_source, fk_entrepot_dest, commentaire, statut, entity
                ) VALUES (
                    '" . $db->escape($ref_bon) . "',
                    " . $user->id . ",
                    NULL,
                    " . ($first_dest ? (int)$first_dest : "NULL") . ",
                    'Bon de sortie regroupé pour : " . implode(', ', array_map(function($s) { return $s->ref; }, $sorties)) . "',
                    1,
                    " . $conf->entity . "
                )";
    if (!$db->query($sql_bon)) {
        throw new Exception("Erreur lors de la création du bon de sortie : " . $db->lasterror());
    }
    $bon_id = $db->last_insert_id(MAIN_DB_PREFIX . "pech_bonsortie");
    
    // 4️⃣ Copier les produits et les services des sorties vers le bon de sortie regroupé
    foreach ($sorties as $sortie) {
        // A. Produits
        $sql_prod = "SELECT p.rowid, p.fk_product, p.nb_carton, p.poids_total, pr.label
                     FROM " . MAIN_DB_PREFIX . "pech_sortiedetprod AS p
                     LEFT JOIN " . MAIN_DB_PREFIX . "product AS pr ON pr.rowid = p.fk_product
                     WHERE p.fk_sortie = " . $sortie->rowid;
        $res_prod = $db->query($sql_prod);
        if ($res_prod && $db->num_rows($res_prod) > 0) {
            while ($obj = $db->fetch_object($res_prod)) {
                // Valeur cumulée des cartons
                $sql_cartons = "SELECT SUM(c.prix_moyen + c.frais) AS total_carton
                                FROM " . MAIN_DB_PREFIX . "pech_sortiedetcarton sc
                                LEFT JOIN " . MAIN_DB_PREFIX . "pech_carton c ON c.rowid = sc.fk_carton
                                WHERE sc.fk_sortiedetprod = " . $obj->rowid;
                $res_carton = $db->query($sql_cartons);
                $valeur_carton = 0;
                if ($res_carton && $db->num_rows($res_carton) > 0) {
                    $valeur_carton = (float) $db->fetch_object($res_carton)->total_carton;
                }

                $sql_insert_prod = "INSERT INTO " . MAIN_DB_PREFIX . "pech_bonsortie_detprod (
                                        fk_bonentree, fk_product, nb_carton, poids_carton, valeur, commentaire, statut, entity
                                    ) VALUES (
                                        " . $bon_id . ",
                                        " . $obj->fk_product . ",
                                        " . (int)$obj->nb_carton . ",
                                        " . ((float)$obj->poids_total / max($obj->nb_carton, 1)) . ",
                                        " . ((float)$valeur_carton) . ",
                                        '" . $db->escape($obj->label) . "',
                                        0,
                                        " . $conf->entity . "
                                    )";
                if (!$db->query($sql_insert_prod)) {
                    throw new Exception("Erreur lors de l'insertion du produit : " . $db->lasterror());
                }
            }
        }

        // B. Services
        $sql_serv = "SELECT * FROM " . MAIN_DB_PREFIX . "pech_sortiedetservice WHERE fk_sortie = " . $sortie->rowid;
        $res_serv = $db->query($sql_serv);
        if ($res_serv && $db->num_rows($res_serv) > 0) {
            while ($obj = $db->fetch_object($res_serv)) {
                $sql_insert_serv = "INSERT INTO " . MAIN_DB_PREFIX . "pech_bonsortie_detserv (
                                        fk_bonentree, description, qte, pu, commentaire, statut, entity
                                    ) VALUES (
                                        " . $bon_id . ",
                                        '" . $db->escape($obj->description) . "',
                                        " . (float)$obj->qty . ",
                                        " . (float)$obj->pu . ",
                                        '" . $db->escape($obj->commentaire) . "',
                                        0,
                                        " . $conf->entity . "
                                    )";
                if (!$db->query($sql_insert_serv)) {
                    throw new Exception("Erreur lors de l'insertion du service : " . $db->lasterror());
                }
            }
        }
    }
    
    // 5️⃣ Mettre à jour les sorties d'origine
    foreach ($selected_sorties as $id) {
        $id = (int)$id;
        $sql_update = "UPDATE " . MAIN_DB_PREFIX . "pech_sortie 
                       SET fk_bonsortie = " . $bon_id . ", is_regroupe = 1 
                       WHERE rowid = " . $id;
        if (!$db->query($sql_update)) {
            throw new Exception("Erreur lors de la mise à jour de la sortie ID $id : " . $db->lasterror());
        }
    }
    
    $db->commit();
    setEventMessages("Les sorties ont été regroupées avec succès sous le Bon de Sortie <strong>" . $ref_bon . "</strong>.", null, 'mesgs');
    header("Location: list.php?regroupement=1");
    exit;

} catch (Exception $e) {
    $db->rollback();
    setEventMessages($e->getMessage(), null, 'errors');
    header("Location: list.php");
    exit;
}
