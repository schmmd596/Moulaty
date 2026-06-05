<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

include_once '../user_entrepot_access.php';
global $db, $user, $langs, $conf;

$langs->load("reception");

// =============================
// RÉCUPÉRATION DES DONNÉES POST
// =============================
$token          = GETPOST('token', 'alpha');
$id             = GETPOST('id', 'int');
$ref            = GETPOST('ref', 'alpha');
$fk_fournisseur = GETPOST('fk_fournisseur', 'int');
$fk_congelateur = GETPOST('fk_congelateur', 'int');
$fk_entrepot    = GETPOST('fk_entrepot', 'int');
$date_creation  = GETPOST('date_creation', 'alpha');
$comment        = GETPOST('comment', 'restricthtml');
$poids_total    = GETPOST('poids_total', 'int');
$montant_total  = GETPOST('montant_total', 'int');


check_user_entrepot_access($fk_entrepot);
// Tableaux des lignes
$fk_product         = GETPOST('fk_product', 'array');
$reception_mode     = GETPOST('reception_mode', 'array');
$calibre            = GETPOST('calibre', 'array');
$nb_voiture         = GETPOST('nb_voiture', 'array');
$prix_voiture       = GETPOST('prix_voiture', 'array');
$poids_brut         = GETPOST('poids_brut', 'array');
$pu_brut            = GETPOST('pu_brut', 'array');
$poids_net          = GETPOST('poids_net', 'array');
$pu_poids_net       = GETPOST('pu_poids_net', 'array');

// =============================
// VÉRIFICATION DU TOKEN
// =============================
if (empty($token) || $token !== $_SESSION['newtoken']) {
    accessforbidden('Invalid token');
}

// =============================
// VALIDATION DES CHAMPS
// =============================
if (empty($fk_fournisseur) || $fk_congelateur <= 0) {
    setEventMessages("Le fournisseur est obligatoire.", null, 'errors');
    header('Location: card.php');
    exit;
}
if (empty($fk_product) ) {
    setEventMessages("aucun produit est selectionné.", null, 'errors');
    header('Location: card.php');
    exit;
}

if (empty($date_creation)) {
    $date_creation = dol_now();
} else {
    $date_creation = strtotime($date_creation);
}

// =============================
// DÉBUT TRANSACTION
// =============================
$db->begin();

// =============================
// CRÉATION OU MISE À JOUR RÉCEPTION
// =============================
if (empty($id)) {
    // --- Création ---
    $sql = "INSERT INTO ".MAIN_DB_PREFIX."pech_reception (
                ref, fk_fournisseur, fk_congelateur, fk_entrepot,
                date_creation, comment, montant, poids, user_create, entity
            ) VALUES (
                '".$db->escape($ref)."',
                ".((int)$fk_fournisseur).",
                ".(!empty($fk_congelateur) ? (int)$fk_congelateur : 'NULL').",
                ".(!empty($fk_entrepot) ? (int)$fk_entrepot : 'NULL').",
                '".$db->idate($date_creation)."',
                '".$db->escape($comment)."',
                ".price2num($montant_total).",
                ".price2num($poids_total).",
                ".((int)$user->id).",
                ".((int)$conf->entity)."
            )";

    $resql = $db->query($sql);
    if (!$resql) {
        $db->rollback();
        dol_print_error($db);
        exit;
    }

    $id = $db->last_insert_id(MAIN_DB_PREFIX."pech_reception");
} else {
    // --- Mise à jour ---
    $sql = "UPDATE ".MAIN_DB_PREFIX."pech_reception SET 
                fk_fournisseur = ".((int)$fk_fournisseur).",
                fk_congelateur = ".(!empty($fk_congelateur) ? (int)$fk_congelateur : 'NULL').",
                fk_entrepot = ".(!empty($fk_entrepot) ? (int)$fk_entrepot : 'NULL').",
                date_creation = '".$db->idate($date_creation)."',
                comment = '".$db->escape($comment)."',
                montant = ".price2num($montant_total).",
                poids = ".price2num($poids_total)."
            WHERE rowid = ".((int)$id);

    $resql = $db->query($sql);
    if (!$resql) {
        $db->rollback();
        dol_print_error($db);
        exit;
    }

    // Supprimer les anciennes lignes avant de recréer
    $db->query("DELETE FROM ".MAIN_DB_PREFIX."pech_receptiondet WHERE fk_reception = ".((int)$id));
}

// =============================
// INSERTION DES LIGNES
// =============================
 function sql_num($value) {
    return ($value === '' || $value === null) ? 0 : price2num($value);
}
for ($i = 0; $i < count($fk_product); $i++) {
    if (empty($fk_product[$i])) continue; // ignore les lignes vides

    $mode = (int) $reception_mode[$i];
    $total_line = 0;

    if ($mode === 1) { // Mode Voiture
        $total_line = price2num($nb_voiture[$i]) * price2num($prix_voiture[$i]);
    } 
    elseif ($mode === 2) { // Mode Poids Brut
        $total_line = price2num($poids_brut[$i]) * price2num($pu_brut[$i]);
    } 
    elseif ($mode === 3) { // Mode Poids Net
        $total_line = price2num($poids_net[$i]) * price2num($pu_poids_net[$i]);
    }
    // Construction de la requête

if ($poids_net[$i] != 0) {
    $prix_unit = (float) (sql_num($total_line) / sql_num($poids_net[$i]));
 
}else{
    $prix_unit = 0;
 
}


$sql = "INSERT INTO ".MAIN_DB_PREFIX."pech_receptiondet (
            fk_reception, fk_product, reception_mode, calibre,
            nb_voiture, prix_voiture,
            poids_brut, pu_brut,
            poids_net, pu_poids_net,
            total_line, prix_moyen,  entity
        ) VALUES (
            ".((int)$id).",
            ".((int)$fk_product[$i]).",
            ".((int)$reception_mode[$i]).",
            ".($calibre[$i] !== '' ? "'".$db->escape($calibre[$i])."'" : "NULL").",
            ".sql_num($nb_voiture[$i]).",
            ".sql_num($prix_voiture[$i]).",
            ".sql_num($poids_brut[$i]).",
            ".sql_num($pu_brut[$i]).",
            ".sql_num($poids_net[$i]).",
            ".sql_num($pu_poids_net[$i]).",
            ".sql_num($total_line).",
            ".$prix_unit.",
            ".((int)$conf->entity)."
        )";

$resql = $db->query($sql);

if (!$resql) {
    $db->rollback();
    dol_print_error($db, "Erreur d'insertion dans pech_receptiondet : ".$db->lasterror());
    exit;
}
}

// =============================
// FIN TRANSACTION
// =============================
$db->commit();

// =============================
// REDIRECTION
// =============================
setEventMessages(($id ? "Réception mise à jour avec succès." : "Réception créée avec succès."), null, 'mesgs');
header("Location: detail_rec.php?id=".$id);
exit;

?>
