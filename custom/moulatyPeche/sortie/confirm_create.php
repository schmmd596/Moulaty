<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

global $db, $user, $conf;

if (empty($user->id)) accessforbidden();

$action = GETPOST('action', 'alpha');


// ============================================================================
// 🔹 CREATION D'UN BON DE SORTIE
// ============================================================================
if ($action === 'confirm_sortie' || $action === 'update_sortie') {

    $db->begin();
    try {
        $id_sortie          = GETPOST('id', 'int');
        $type_sortie        = GETPOST('type', 'int');
        $fk_entrepot_source = GETPOST('fk_entrepot_source', 'int');
        $fk_entrepot_dest   = GETPOST('fk_entrepot_dest', 'int');
        $fk_client          = GETPOST('fk_client', 'int');
        $commentaire        = GETPOST('commentaire', 'restricthtml');

        if (empty($fk_entrepot_source)) {
            throw new Exception("Entrepôt source manquant.");
        }

        // Récupération des produits
        $products      = GETPOST('products', 'array');
        $nb_carton     = GETPOST('nb_carton', 'array');
        $poids         = GETPOST('poids', 'array');
        $pu            = GETPOST('pu', 'array');
        $cartons_rowid = GETPOST('cartons_rowid', 'array');

        if (empty($products)) {
            throw new Exception("Aucun produit sélectionné.");
        }

        // 🔹 Création ou modification
        if ($action === 'confirm_sortie') {
            $resqll2 = $db->query("SELECT ref FROM ".MAIN_DB_PREFIX."pech_sortie ORDER BY rowid DESC LIMIT 1");
            $ref1 = '';
                if ($resqll2 && $db->num_rows($resqll2) > 0) {
                    $obj2 = $db->fetch_object($resqll2);
                    $ref1 = $obj2->ref;
                }
                $nextNumber = (!empty($ref1) && preg_match('/SO-(\d+)/', $ref1, $matches)) ? ((int)$matches[1] + 1) : 1;
            $ref1 = 'SO-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
            $ref = $ref1;

            $sql = "INSERT INTO ".MAIN_DB_PREFIX."pech_sortie
                    (ref,type,fk_entrepot_source,fk_entrepot_dest,fk_client,fk_user,commentaire,statut,poids_total,nb_carton_total,entity)
                    VALUES (
                        '".$db->escape($ref)."',
                        ".(int)$type_sortie.",
                        ".(int)$fk_entrepot_source.",
                        ".(!empty($fk_entrepot_dest)?(int)$fk_entrepot_dest:"NULL").",
                        ".(!empty($fk_client)?(int)$fk_client:"NULL").",
                        ".(int)$user->id.",
                        ".(!empty($commentaire)?"'".$db->escape($commentaire)."'":"NULL").",
                        0,0,0,".(int)$conf->entity."
                    )";
            if (!$db->query($sql)) throw new Exception("Erreur insertion sortie : ".$db->lasterror());

            $fk_sortie = $db->last_insert_id(MAIN_DB_PREFIX.'pech_sortie');
        } else {
            // Modification
            if ($id_sortie <= 0) throw new Exception("ID de sortie invalide.");

            $res = $db->query("SELECT statut FROM ".MAIN_DB_PREFIX."pech_sortie WHERE rowid=$id_sortie");
            $obj = $db->fetch_object($res);
            if ($obj->statut != 0) throw new Exception("Impossible de modifier un bon déjà validé ou livré.");

            $sql = "UPDATE ".MAIN_DB_PREFIX."pech_sortie SET
                        type = ".(int)$type_sortie.",
                        fk_entrepot_source = ".(int)$fk_entrepot_source.",
                        fk_entrepot_dest = ".(!empty($fk_entrepot_dest)?(int)$fk_entrepot_dest:"NULL").",
                        fk_client = ".(!empty($fk_client)?(int)$fk_client:"NULL").",
                        commentaire = ".(!empty($commentaire)?"'".$db->escape($commentaire)."'":"NULL")."
                    WHERE rowid = $id_sortie";
            if (!$db->query($sql)) throw new Exception("Erreur mise à jour sortie : ".$db->lasterror());

            $fk_sortie = $id_sortie;

            // Supprimer anciens produits et cartons
            $db->query("DELETE FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton WHERE fk_sortiedetprod IN 
                        (SELECT rowid FROM ".MAIN_DB_PREFIX."pech_sortiedetprod WHERE fk_sortie=$fk_sortie)");
            $db->query("DELETE FROM ".MAIN_DB_PREFIX."pech_sortiedetprod WHERE fk_sortie=$fk_sortie");
        }

        // 🔹 Insertion des produits et cartons
        $poids_total_global     = 0;
        $nb_carton_total_global = 0;

        foreach ($products as $i => $pid) {
            $pid = (int)$pid;
            if ($pid <= 0) continue;

            $nbc = (int)($nb_carton[$i] ?? 0);
            $p   = (float)($poids[$i] ?? 0);
            $prix = (float)($pu[$i] ?? 0);

            if ($p <= 0 || $nbc <= 0) {
                throw new Exception("Produit ID $pid : poids ou nombre de cartons invalide.");
            }

            $poids_total_global     += $p;
            $nb_carton_total_global += $nbc;

            $sqlp = "INSERT INTO ".MAIN_DB_PREFIX."pech_sortiedetprod
                     (fk_sortie,fk_product,nb_carton,poids_total,pu,commentaire,statut,entity)
                     VALUES ($fk_sortie,$pid,$nbc,$p,$prix,NULL,0,".(int)$conf->entity.")";
            if (!$db->query($sqlp)) throw new Exception("Erreur insertion produit ID $pid : ".$db->lasterror());

            $fk_sortiedetprod = $db->last_insert_id(MAIN_DB_PREFIX.'pech_sortiedetprod');

            // Cartons liés
            $rowids = $cartons_rowid[$i] ?? '';
            if (!empty($rowids)) {
                $ids = explode(',', $rowids);
                foreach ($ids as $cid) {
                    $cid = (int)$cid;
                    if ($cid <= 0) continue;

                    $res = $db->query("SELECT poids FROM ".MAIN_DB_PREFIX."pech_carton WHERE rowid=$cid");
                    $obj = $db->fetch_object($res);
                    $poids_carton = $obj->poids ?? 0;

                    $sqlc = "INSERT INTO ".MAIN_DB_PREFIX."pech_sortiedetcarton
                             (fk_sortiedetprod,fk_carton,poids,statut,entity)
                             VALUES ($fk_sortiedetprod,$cid,$poids_carton,0,".(int)$conf->entity.")";
                    if (!$db->query($sqlc)) throw new Exception("Erreur insertion carton ID $cid : ".$db->lasterror());
                }
            }
        }

        // 🔹 Mise à jour des totaux globaux
        $sqlu = "UPDATE ".MAIN_DB_PREFIX."pech_sortie
                 SET poids_total=$poids_total_global, nb_carton_total=$nb_carton_total_global
                 WHERE rowid=$fk_sortie";
        if (!$db->query($sqlu)) throw new Exception("Erreur mise à jour totaux : ".$db->lasterror());

        $db->commit();

        setEventMessages("✅ Bon de sortie ".($action==='confirm_sortie'?'créé':'mis à jour')." avec succès.", null, 'mesgs');
        header("Location: detail.php?id=$fk_sortie");
        exit;

    } catch (Exception $e) {
        $db->rollback();
        setEventMessages("❌ Erreur lors de la ".($action==='confirm_sortie'?'création':'modification')." : ".$e->getMessage(), null, 'errors');
        header("Location: list.php");
        exit;
    }
}
?>
