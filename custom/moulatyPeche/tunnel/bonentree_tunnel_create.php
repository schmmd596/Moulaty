<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

global $db, $langs, $user;

$langs->load("abricot@abricot");
$form = new Form($db);

// ✅ Récupération des données POST
$selected_ids = GETPOST('select_misenplat', 'array');
$fk_entrepot  = GETPOST('fk_entrepot', 'int');
$action       = GETPOST('action', 'alpha');

// ✅ Vérification entrepôt
if ($fk_entrepot <= 0) {
    setEventMessages("⚠️ Veuillez sélectionner un entrepôt avant de continuer.", null, 'warnings');
    llxHeader('', "Erreur - Bon d'entrée");
    print '<div class="error center">Aucun entrepôt sélectionné.<br><br><a class="button" href="tunnel.php">Retour</a></div>';
    llxFooter(); $db->close(); exit;
}

// ✅ Vérification lignes sélectionnées
if (empty($selected_ids)) {
    setEventMessages("⚠️ Aucune ligne sélectionnée pour créer le bon d’entrée.", null, 'warnings');
    header("Location: tunnel.php");
    exit;
}

// ⚙️ Vérifier si certaines lignes ont déjà un bon d'entrée
$sqlCheck = "SELECT rowid FROM ".MAIN_DB_PREFIX."pech_misenplat 
             WHERE fk_bon_entree IS NOT NULL 
             AND rowid IN (".implode(',', array_map('intval', $selected_ids)).")";
$resCheck = $db->query($sqlCheck);
if ($resCheck && $db->num_rows($resCheck) > 0) {
    setEventMessages("❌ Certaines lignes sélectionnées sont déjà associées à un bon d'entrée. Veuillez les décocher.", null, 'errors');
    header("Location: tunnel.php");
    exit;
}

// ⚙️ Récupération des données des mises en plat sélectionnées
/*$sql = "SELECT 
            m.rowid AS misenplat_id,
            m.fk_congelateur AS fk_soc,
            m.fk_reception,
            m.fk_reception_det,
            m.nombre_plat,
            m.poids_plat,
            r.ref AS ref_reception,
            s.nom AS fournisseur,
            r.fk_entrepot,
            p.rowid as produit_id,
            p.label AS produit
        FROM ".MAIN_DB_PREFIX."pech_misenplat AS m
        LEFT JOIN ".MAIN_DB_PREFIX."pech_reception AS r ON r.rowid = m.fk_reception
        LEFT JOIN ".MAIN_DB_PREFIX."societe AS s ON s.rowid = m.fk_congelateur
        LEFT JOIN ".MAIN_DB_PREFIX."pech_receptiondet AS rd ON rd.rowid = m.fk_reception_det
        LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = rd.fk_prod
        WHERE m.rowid IN (".implode(',', array_map('intval', $selected_ids)).")";

$resql = $db->query($sql);
if (!$resql) {
    setEventMessages("Erreur SQL : ".$db->lasterror(), null, 'errors');
    llxFooter(); $db->close(); exit;
}*/
$sql = "SELECT 
            m.rowid AS misenplat_id,
            m.fk_congelateur AS fk_soc,
            m.fk_reception,
            m.fk_reception_det,
            m.nombre_plat,
            m.poids_plat,
            r.ref AS ref_reception,
            s.nom AS fournisseur,
            r.fk_entrepot,
            p.rowid as produit_id,
            p.label AS produit
        FROM ".MAIN_DB_PREFIX."pech_misenplat AS m
        LEFT JOIN ".MAIN_DB_PREFIX."pech_reception AS r ON r.rowid = m.fk_reception
        LEFT JOIN ".MAIN_DB_PREFIX."societe AS s ON s.rowid = m.fk_congelateur
        LEFT JOIN ".MAIN_DB_PREFIX."pech_receptiondet AS rd ON rd.rowid = m.fk_reception_det
        LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = rd.fk_product
        WHERE m.rowid IN (".implode(',', array_map('intval', $selected_ids)).")";

$resql = $db->query($sql);

if (!$resql) {
    llxHeader('', "Confirmation du Bon d'entrée Tunnel");

    print '<div class="error" style="padding:10px;margin:10px;border:1px solid red">';
    print '<strong>❌ Erreur SQL détectée :</strong><br>';
    //print '<pre>'.$sql.'</pre>'; // affiche la requête exécutée
    print '<pre>'.$db->lasterror().'</pre>'; // affiche le message d'erreur exact
    print '</div>';
    llxFooter(); 
    $db->close(); 
    exit;
}
$lines = [];
while ($obj = $db->fetch_object($resql)) $lines[] = $obj;

// 🧾 Action : création effective
if ($action == 'confirm_create') {
    $commentaire = GETPOST('commentaire', 'restricthtml');

    $db->begin();
    try {
        // 1️⃣ Création du bon principal
        $ref = 'BET-' . date('Ymd-His');
        $sqlInsert = "INSERT INTO ".MAIN_DB_PREFIX."pech_bonentree(ref, fk_user, commentaire, date_creation)
                      VALUES ('".$db->escape($ref)."', ".((int)$user->id).", '".$db->escape($commentaire)."', NOW())";
        $res = $db->query($sqlInsert);
        if (!$res) throw new Exception("Erreur lors de la création du bon principal : ".$db->lasterror());

        $id_bonentree = $db->last_insert_id(MAIN_DB_PREFIX."pech_bonentree");

        // 2️⃣ Création des lignes de détails
        foreach ($lines as $line) {
            $qte = $line->poids_plat * $line->nombre_plat;

            $sqlDet = "INSERT INTO ".MAIN_DB_PREFIX."pech_bonentree_det
                       (fk_bonentree, fk_soc, fk_reception, fk_misenplat, fk_prod, fk_entrepot, nombre_plat, poids_total, commentaire)
                       VALUES (
                           ".((int)$id_bonentree).",
                           ".((int)$line->fk_soc).",
                           ".((int)$line->fk_reception).",
                           ".((int)$line->misenplat_id).",
                           ".((int)$line->produit_id).",
                           ".((int)$line->fk_entrepot).",
                           ".((int)$line->nombre_plat).",
                           ".((float)$qte).",
                           '".$db->escape($line->produit)."'
                       )";
            $resDet = $db->query($sqlDet);
            if (!$resDet) throw new Exception("Erreur lors de l’insertion d’une ligne détail : ".$db->lasterror());

            // Mise à jour du fk_bon_entree dans misenplat
            $sqlUpdate = "UPDATE ".MAIN_DB_PREFIX."pech_misenplat 
                          SET fk_bon_entree = ".((int)$id_bonentree)." 
                          WHERE rowid = ".((int)$line->misenplat_id);
            $resUpd = $db->query($sqlUpdate);
            if (!$resUpd) throw new Exception("Erreur lors de la mise à jour de la mise en plat : ".$db->lasterror());
        }

        $db->commit();
        setEventMessages("✅ Bon d'entrée <strong>$ref</strong> créé avec succès.", null, 'mesgs');
        header("Location: tunnel.php");
        exit;

    } catch (Exception $e) {
        $db->rollback();
        setEventMessages($e->getMessage(), null, 'errors');
    }
}

// --- AFFICHAGE DE CONFIRMATION ---
llxHeader('', "Confirmation du Bon d'entrée Tunnel");

print load_fiche_titre("Confirmation création Bon d'entrée");

// --- Formulaire de confirmation ---
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="confirm_create">';
print '<input type="hidden" name="fk_entrepot" value="'.$fk_entrepot.'">';

foreach ($selected_ids as $id) {
    print '<input type="hidden" name="select_misenplat[]" value="'.$id.'">';
}

// --- Tableau récapitulatif ---
print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><th>Fournisseur</th><th>Réception</th><th>Produit</th><th>Nb Plats</th><th>Poids Total</th></tr>';

foreach ($lines as $line) {
    print '<tr class="oddeven">';
    print '<td>'.dol_escape_htmltag($line->fournisseur).'</td>';
    print '<td>'.dol_escape_htmltag($line->ref_reception).'</td>';
    print '<td>'.dol_escape_htmltag($line->produit).'</td>';
    print '<td align="right">'.(int)$line->nombre_plat.'</td>';
    print '<td align="right">'.price($line->poids_plat * $line->nombre_plat).'</td>';
    print '</tr>';
}
print '</table><br>';

// --- Commentaire ---
print '<table class="border" width="100%">';
print '<tr><td><strong>Commentaire du bon d\'entrée</strong></td>';
print '<td><textarea name="commentaire" rows="3" cols="80"></textarea></td></tr>';
print '</table><br>';

// --- Boutons ---
print '<div class="center">';
print '<input type="submit" class="button" value="Créer le Bon d\'entrée">';
print '&nbsp;&nbsp;<a class="button" href="tunnel.php">Annuler</a>';
print '</div>';

print '</form>';

llxFooter();
$db->close();
?>
