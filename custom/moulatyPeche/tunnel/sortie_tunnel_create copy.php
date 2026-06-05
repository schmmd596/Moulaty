<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';

global $db, $langs, $user;
$langs->load("abricot@abricot");

$form = new Form($db);

// ===============================
// PARAMÈTRES
// ===============================
$selected_ids = GETPOST('select_misenplat', 'array'); // ID de mises en plat sélectionnées
$fk_entrepot  = GETPOST('fk_entrepot', 'int');
$action       = GETPOST('action', 'alpha');
$id_lot       = GETPOST('id_lot', 'int');
$mod = $id_lot;
//print 'hhh :'.$fk_entrepot;
$lines = [];

// ===============================
// RÉCUPÉRATION DES DONNÉES
// ===============================
if ($id_lot > 0) {
    // 🔁 MODIFICATION D’UN LOT EXISTANT
    $sql = "SELECT 
                l.ref AS lot_ref,
                l.commentaire AS comment,
                d.rowid AS lotdet_id,
                d.fk_misenplat AS misenplat_id,
                d.fk_product AS produit_id,
                d.plat_carton,
                d.nb_carton,
                d.poids_carton,
                d.commentaire AS det_comment,
                p.label AS produit,
                m.nombre_plat,
                m.poids_plat,
                e.rowid as fk_entrepot,
                s.nom AS fournisseur
            FROM ".MAIN_DB_PREFIX."pech_lot AS l
            LEFT JOIN ".MAIN_DB_PREFIX."pech_lotdet AS d ON d.fk_lot = l.rowid
            LEFT JOIN ".MAIN_DB_PREFIX."pech_misenplat AS m ON m.rowid = d.fk_misenplat
            LEFT JOIN ".MAIN_DB_PREFIX."societe AS s ON s.rowid = m.fk_congelateur
            LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = d.fk_product
            LEFT JOIN ".MAIN_DB_PREFIX."entrepot AS e ON e.rowid = l.fk_entrepot
            WHERE l.rowid = ".(int)$id_lot;
} else {
    // 🆕 CRÉATION À PARTIR DES MISES EN PLAT
    if (empty($selected_ids)) {
        setEventMessages("⚠️ Aucune mise en plat sélectionnée.", null, 'warnings');
        header("Location: tunnel.php");
        exit;
    }

    $sql = "SELECT 
                m.rowid AS misenplat_id,
                m.fk_congelateur,
                m.nombre_plat,
                m.nombre_plat_sortie,
                m.poids_plat,
                p.rowid AS produit_id,
                p.label AS produit,
                s.nom AS fournisseur
            FROM ".MAIN_DB_PREFIX."pech_misenplat AS m
            LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = m.fk_product
            LEFT JOIN ".MAIN_DB_PREFIX."societe AS s ON s.rowid = m.fk_congelateur
            WHERE m.rowid IN (".implode(',', array_map('intval', $selected_ids)).")";
}

$resql = $db->query($sql);
if (!$resql) dol_print_error($db, $sql);
while ($obj = $db->fetch_object($resql)) $lines[] = $obj;

// ===============================
// ACTION CONFIRMÉE
// ===============================
$resqlll2 = $db->query("SELECT ref FROM ".MAIN_DB_PREFIX."pech_lot ORDER BY rowid DESC LIMIT 1");
$ref1 = '';
    if ($resqlll2 && $db->num_rows($resqlll2) > 0) {
        $obj2 = $db->fetch_object($resqlll2);
        $ref1 = $obj2->ref;
    }
    $nextNumber = (!empty($ref1) && preg_match('/Lot-(\d+)/', $ref1, $matches)) ? ((int)$matches[1] + 1) : 1;
$ref1 = 'Lot-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);


if ($action == 'confirm_savejjj') {
    $commentaire = GETPOST('commentaire', 'restricthtml');

    $db->begin();
    try {
        if ($id_lot > 0) {
            // 🔁 MODIFICATION EXISTANTE
            $sqlUpdateLot = "UPDATE ".MAIN_DB_PREFIX."pech_lot 
                             SET commentaire='".$db->escape($commentaire)."',
                                 fk_user_create=".((int)$user->id)."
                             WHERE rowid=".((int)$id_lot);
            if (!$db->query($sqlUpdateLot)) throw new Exception("Erreur MAJ lot : ".$db->lasterror());

            // Supprimer les anciennes lignes lotdet
            $db->query("DELETE FROM ".MAIN_DB_PREFIX."pech_lotdet WHERE fk_lot=".(int)$id_lot);

            $res = $db->query("SELECT ref FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid=".(int)$id_lot);
            $ref = $db->fetch_object($res)->ref;

        } else {
            // 🆕 CRÉATION
            $ref = $ref1;
            $sqlInsertLot = "INSERT INTO ".MAIN_DB_PREFIX."pech_lot(ref, fk_user_create, fk_entrepot, commentaire, date_creation)
                             VALUES ('".$db->escape($ref)."', ".((int)$user->id).", ".((int)$fk_entrepot).", '".$db->escape($commentaire)."', NOW())";
            if (!$db->query($sqlInsertLot)) throw new Exception("Erreur création lot : ".$db->lasterror());

            $id_lot = $db->last_insert_id(MAIN_DB_PREFIX."pech_lot");
        }

        // 💾 INSÉRER LES LIGNES PRODUITS NORMAUX
        $total_restant_mixte = 0;
        $poids_moyen_mixte   = 0;
        $produits_count_mixte = 0;

        foreach ($lines as $line) {
            $plat_par_carton = GETPOST('plat_par_carton_'.$line->misenplat_id, 'int');
            $nombre_plat     = GETPOST('nombre_plat_'.$line->misenplat_id, 'int');
            if ($plat_par_carton <= 0) $plat_par_carton = 1;
            if ($nombre_plat <= 0) $nombre_plat = $line->nombre_plat;

            $nb_cartons  = intdiv($nombre_plat, $plat_par_carton);
            $restant     = $nombre_plat % $plat_par_carton;
            $poids_total = $line->poids_plat * $plat_par_carton;

            // Insérer la ligne du produit
            $sqlDet = "INSERT INTO ".MAIN_DB_PREFIX."pech_lotdet
                       (fk_lot, fk_product, fk_misenplat, poids_carton, plat_carton, nb_carton, taux_rendement, commentaire, date_creation)
                       VALUES (
                           ".((int)$id_lot).",
                           ".((int)$line->produit_id).",
                           ".((int)$line->misenplat_id).",
                           ".((float)$poids_total).",
                           ".((int)$plat_par_carton).",
                           ".((int)$nb_cartons).",
                           100,
                           '".$db->escape($line->produit)."',
                           NOW()
                       )";
            if (!$db->query($sqlDet)) throw new Exception("Erreur insertion lotdet : ".$db->lasterror());

            // Cumul pour produit mixte
            $total_restant_mixte += $restant;
            $poids_moyen_mixte   += $line->poids_plat;
            $produits_count_mixte++;
        }

        // 💾 INSÉRER LE PRODUIT MIXTE
        // ===============================
        // 🔁 INSERTION DU PRODUIT MIXTE
        // ===============================

        // Récupération de l'ID du produit "mixte"
        $sqlProductMixte = "SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE label = 'MIXTE' LIMIT 1";
        $resMixte = $db->query($sqlProductMixte);
        $fk_product_mixte = null;
        if ($resMixte && $db->num_rows($resMixte) > 0) {
            $fk_product_mixte = $db->fetch_object($resMixte)->rowid;
        }

        // Calcul du total des plats restants
        /*$total_restants = 0;
        foreach ($lines as $line) {
            $plat_par_carton = GETPOST('plat_par_carton_'.$line->misenplat_id, 'int');
            if ($plat_par_carton <= 0) $plat_par_carton = 1;

            $restant = $line->nombre_plat % $plat_par_carton;
            $total_restants += $restant;
        }*/
        $total_restants = GETPOST('nb_plats_mixte', 'int');   

        // Si on a des plats restants, on crée le lot mixte
        if ($total_restants > 0) {
            // Récupération du nombre de plats par carton mixte saisi par l’utilisateur
            $plat_par_carton_mixte = GETPOST('plat_par_carton_mixte', 'int');
            if ($plat_par_carton_mixte <= 0) $plat_par_carton_mixte = 1;

            $nb_cartons_mixte  = intdiv($total_restants, $plat_par_carton_mixte);
            $restant_mixte     = $total_restants % $plat_par_carton_mixte;

            // On suppose que le poids d’un plat mixte = moyenne du poids plat des lignes
            $poids_total_mixte = 0;
            $total_poids_unitaire = 0;
            foreach ($lines as $line) {
                $total_poids_unitaire += $line->poids_plat;
            }
            if (count($lines) > 0) {
                $poids_moyen_plat = $total_poids_unitaire / count($lines);
                $poids_total_mixte = $poids_moyen_plat * $plat_par_carton_mixte;
            }

            // Insertion du produit mixte
            $sqlMixte = "INSERT INTO ".MAIN_DB_PREFIX."pech_lotdet
                        (fk_lot, fk_product, fk_misenplat, poids_carton, plat_carton, nb_carton, taux_rendement, commentaire, date_creation)
                        VALUES (
                            ".((int)$id_lot).",
                            ".($fk_product_mixte ? (int)$fk_product_mixte : 0).",
                            0,
                            ".((float)$poids_total_mixte).",
                            ".((int)$plat_par_carton_mixte).",
                            ".((int)$nb_cartons_mixte).",
                            100,
                            'Produit mixte',
                            NOW()
                        )";
            if (!$db->query($sqlMixte)) throw new Exception("Erreur insertion produit mixte : ".$db->lasterror());
        }


        $db->commit();
        setEventMessages(($mod > 0 ? "✅ Lot modifié" : "✅ Lot créé")." avec succès.".$total_restants , null, 'mesgs');
        header("Location: ../Lots/detail_lot.php?id=".$id_lot);
        exit;

    } catch (Exception $e) {
        $db->rollback();
        setEventMessages($e->getMessage(), null, 'errors');
    }
}

if ($action == 'confirm_save') {
    $commentaire = GETPOST('commentaire', 'restricthtml');

    $db->begin();
    try {
        if ($id_lot > 0) {
            // 🔁 MODIFICATION EXISTANTE
            $sqlUpdateLot = "UPDATE ".MAIN_DB_PREFIX."pech_lot 
                             SET commentaire='".$db->escape($commentaire)."',
                                 fk_user_create=".((int)$user->id)."
                             WHERE rowid=".((int)$id_lot);
            if (!$db->query($sqlUpdateLot)) throw new Exception("Erreur MAJ lot : ".$db->lasterror());

            // Supprimer les anciennes lignes lotdet
            $db->query("DELETE FROM ".MAIN_DB_PREFIX."pech_lotdet WHERE fk_lot=".(int)$id_lot);
            $db->query("DELETE FROM ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat WHERE fk_lotdet_mixte IN
                        (SELECT rowid FROM ".MAIN_DB_PREFIX."pech_lotdet WHERE fk_lot=".(int)$id_lot.")");

            $res = $db->query("SELECT ref FROM ".MAIN_DB_PREFIX."pech_lot WHERE rowid=".(int)$id_lot);
            $ref = $db->fetch_object($res)->ref;

        } else {
            // 🆕 CRÉATION
            $ref = $ref1;
            $sqlInsertLot = "INSERT INTO ".MAIN_DB_PREFIX."pech_lot(ref, fk_user_create, fk_entrepot, commentaire, date_creation)
                             VALUES ('".$db->escape($ref)."', ".((int)$user->id).", ".((int)$fk_entrepot).", '".$db->escape($commentaire)."', NOW())";
            if (!$db->query($sqlInsertLot)) throw new Exception("Erreur création lot : ".$db->lasterror());

            $id_lot = $db->last_insert_id(MAIN_DB_PREFIX."pech_lot");
        }

        // 💾 INSÉRER LES LIGNES PRODUITS NORMAUX
        $total_restant_mixte = 0;
        $poids_moyen_mixte   = 0;

        foreach ($lines as $line) {
            $plat_par_carton = GETPOST('plat_par_carton_'.$line->misenplat_id, 'int');
            $nombre_plat     = GETPOST('nombre_plat_'.$line->misenplat_id, 'int');
            if ($plat_par_carton <= 0) $plat_par_carton = 1;
            if ($nombre_plat <= 0) $nombre_plat = $line->nombre_plat;

            $nb_cartons  = intdiv($nombre_plat, $plat_par_carton);
            $restant     = $nombre_plat % $plat_par_carton;
            $poids_total = $line->poids_plat * $plat_par_carton;

            // Insérer la ligne du produit
            $sqlDet = "INSERT INTO ".MAIN_DB_PREFIX."pech_lotdet
                       (fk_lot, fk_product, fk_misenplat, poids_carton, plat_carton, nb_carton, taux_rendement, commentaire, date_creation)
                       VALUES (
                           ".((int)$id_lot).",
                           ".((int)$line->produit_id).",
                           ".((int)$line->misenplat_id).",
                           ".((float)$poids_total).",
                           ".((int)$plat_par_carton).",
                           ".((int)$nb_cartons).",
                           100,
                           '".$db->escape($line->produit)."',
                           NOW()
                       )";
            if (!$db->query($sqlDet)) throw new Exception("Erreur insertion lotdet : ".$db->lasterror());

            // Cumul pour produit mixte
            $total_restant_mixte += $restant;
            $poids_moyen_mixte   += $line->poids_plat;
        }

        // 💾 INSÉRER LE PRODUIT MIXTE
        $sqlProductMixte = "SELECT rowid FROM ".MAIN_DB_PREFIX."product WHERE label = 'MIXTE' LIMIT 1";
        $resMixte = $db->query($sqlProductMixte);
        $fk_product_mixte = $resMixte && $db->num_rows($resMixte) > 0 ? $db->fetch_object($resMixte)->rowid : 0;

        $total_restants = GETPOST('nb_plats_mixte', 'int');   

        if ($total_restants > 0) {
            $plat_par_carton_mixte = GETPOST('plat_par_carton_mixte', 'int');
            if ($plat_par_carton_mixte <= 0) $plat_par_carton_mixte = 1;

            $nb_cartons_mixte  = intdiv($total_restants, $plat_par_carton_mixte);
            $restant_mixte     = $total_restants % $plat_par_carton_mixte;

            $poids_total_mixte = count($lines) > 0 ? ($poids_moyen_mixte / count($lines)) * $plat_par_carton_mixte : 0;

            // Insertion du produit mixte
            $sqlMixte = "INSERT INTO ".MAIN_DB_PREFIX."pech_lotdet
                        (fk_lot, fk_product, fk_misenplat, poids_carton, plat_carton, nb_carton, taux_rendement, commentaire, date_creation)
                        VALUES (
                            ".((int)$id_lot).",
                            ".((int)$fk_product_mixte).",
                            0,
                            ".((float)$poids_total_mixte).",
                            ".((int)$plat_par_carton_mixte).",
                            ".((int)$nb_cartons_mixte).",
                            100,
                            'Produit mixte',
                            NOW()
                        )";
            if (!$db->query($sqlMixte)) throw new Exception("Erreur insertion produit mixte : ".$db->lasterror());

            $id_lotdet_mixte = $db->last_insert_id(MAIN_DB_PREFIX."pech_lotdet");

            // 🔗 Lier le produit mixte aux mises en plat sources
            /*foreach ($lines as $line) {
                $plat_par_carton = GETPOST('plat_par_carton_'.$line->misenplat_id, 'int');
                $nombre_plat     = GETPOST('nombre_plat_'.$line->misenplat_id, 'int');
                if ($plat_par_carton <= 0) $plat_par_carton = 1;

                $restant = $nombre_plat % $plat_par_carton;
                if ($restant > 0) {
                    $sqlLink = "INSERT INTO ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat
                                (fk_lotdet_mixte, fk_misenplat, nb_plat)
                                VALUES (
                                    ".(int)$id_lotdet_mixte.",
                                    ".(int)$line->misenplat_id.",
                                    ".(int)$restant."
                                )";
                    if (!$db->query($sqlLink)) throw new Exception("Erreur liaison lotdet mixte : ".$db->lasterror());
                }
            }*/

            foreach ($lines as $line) {
                $plat_par_carton = GETPOST('plat_par_carton_'.$line->misenplat_id, 'int');
                $nombre_plat     = GETPOST('nombre_plat_'.$line->misenplat_id, 'int');
                if ($plat_par_carton <= 0) $plat_par_carton = 1;

                $restant = $nombre_plat % $plat_par_carton;
                if ($restant > 0) {
                    $sqlLink = "INSERT INTO ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat
                                (fk_lotdet_mixte, fk_misenplat, fk_product, nb_plat)
                                VALUES (
                                    ".(int)$id_lotdet_mixte.",
                                    ".(int)$line->misenplat_id.",
                                    ".(int)$line->produit_id.",  -- Récupéré directement depuis la mise en plat
                                    ".(int)$restant."
                                )";
                    if (!$db->query($sqlLink)) throw new Exception("Erreur liaison lotdet mixte : ".$db->lasterror());
                }
            }

        }

        $db->commit();
        setEventMessages(($mod > 0 ? "✅ Lot modifié" : "✅ Lot créé")." avec succès. Total restants : ".$total_restants , null, 'mesgs');
        header("Location: ../Lots/detail_lot.php?id=".$id_lot);
        exit;

    } catch (Exception $e) {
        $db->rollback();
        setEventMessages($e->getMessage(), null, 'errors');
    }
}


// ===============================
// AFFICHAGE FORMULAIRE
// ===============================
// ===============================
// AFFICHAGE FORMULAIRE
// ===============================
llxHeader('', ($id_lot ? $langs->trans("ModifyLot") : $langs->trans("CreateNewLot")));
$title = $id_lot ? $langs->trans("ModifyLot")." ".$lines[0]->lot_ref : $langs->trans("CreateNewLot");
print load_fiche_titre($title);

print '
<style>
.lot-container {
    background: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
    margin-bottom: 20px;
}

.lot-table {
    width: 100%;
    border-collapse: collapse;
    background: #fff;
    border-radius: 6px;
    overflow: hidden;
    margin: 15px 0;
}

.lot-table th {
    background: linear-gradient(135deg, #dbeaf5ff, #d9dcddff);
    color: black;
    padding: 12px 8px;
    font-weight: bold;
    text-align: center;
    border: none;
    font-size: 15px;
}

.lot-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #e9ecef;
    text-align: center;
    font-size: 14px;
}

.lot-table tr:hover {
    background: #f8f9fa;
}

.produit-ligne {
    background: #fafafa;
}

.liste_total {
    background: #e8f4fd !important;
    font-weight: bold;
    border-top: 2px solid #3498db;
}

.form-input {
    padding: 6px 8px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 11px;
    text-align: right;
    width: 70px;
}

.form-input:focus {
    border-color: #3498db;
    outline: none;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
}

.comment-section {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin: 15px 0;
    border-left: 4px solid #3498db;
}

.comment-section textarea {
    width: 100%;
    padding: 10px;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 12px;
    resize: vertical;
}

.actions-section {
    text-align: center;
    margin: 20px 0;
}

.btn {
    display: inline-block;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.2s ease;
}

.btn-primary {
    background: #27ae60;
    color: white;
}

.btn-primary:hover {
    background: #219a52;
    transform: translateY(-1px);
}

.btn-cancel {
    background: #95a5a6;
    color: white;
}

.btn-cancel:hover {
    background: #7f8c8d;
}

@media (max-width: 768px) {
    .lot-container {
        padding: 10px;
    }
    
    .lot-table {
        font-size: 10px;
    }
    
    .lot-table th,
    .lot-table td {
        padding: 8px 4px;
    }
    
    .form-input {
        width: 60px;
    }
}
</style>
';

print '<div class="lot-container">';
print '<form method="POST" action="'.$_SERVER['PHP_SELF'].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="confirm_save">';
print '<input type="hidden" name="fk_entrepot" value="'.$fk_entrepot.'">';
if ($id_lot > 0) print '<input type="hidden" name="id_lot" value="'.$id_lot.'">';

if (!empty($selected_ids)) {
    foreach ($selected_ids as $id)
        print '<input type="hidden" name="select_misenplat[]" value="'.$id.'">';
}

print '<table class="lot-table" id="tableLots">';
print '<tr class="liste_titre">
        <th>'.$langs->trans("Product").'</th>
        <th>'.$langs->trans("NbPlates").'</th>
        <th>'.$langs->trans("NbToPlate").'</th>
        <th>'.$langs->trans("TotalWeight").'</th>
        <th>'.$langs->trans("PlatePerCarton").'</th>
        <th>'.$langs->trans("NbCartons").'</th>
        <th>'.$langs->trans("Remaining").'</th>
      </tr>';

foreach ($lines as $line) {
    $poids_total = $line->poids_plat * $line->nombre_plat;
    print '<tr class="oddeven produit-ligne">';
    print '<td>'.$line->produit.'</td>';
    print '<td>'. (int)($line->nombre_plat - $line->nombre_plat_sortie) .'</td>';
    print '<td><input type="number" class="nombre_plat form-input" name="nombre_plat_'.$line->misenplat_id.'" value="'. (int)($line->nombre_plat - $line->nombre_plat_sortie) .'" min="0" max="'. (int)($line->nombre_plat - $line->nombre_plat_sortie) .'"></td>';
    //print '<td>'.price($poids_total).'</td>';
    print '<td class="">'.price(($line->nombre_plat - $line->nombre_plat_sortie) * $line->poids_plat).'</td>';
        
    print '<td><input type="number" class="plat_par_carton form-input" name="plat_par_carton_'.$line->misenplat_id.'" value="'.($line->plat_carton ?? 2).'" min="1"></td>';
    print '<td class="nb_cartons">0</td>';
    print '<td class="plat_restant">0</td>';
    print '</tr>';
}

// Ligne produit mixte
print '<tr class="liste_total" id="ligneMixte">
        <td>'.$langs->trans("MixedProduct").'</td>
        <td>
        <input type="number" id="nb_plats_mixte" name="nb_plats_mixte" readonly value="0" min="0" class="form-input">
        </td>
        <td colspan="2"></td>
        <td><input type="number" id="plat_par_carton_mixte" name="plat_par_carton_mixte" readonly value="1" min="1" class="form-input"></td>
        <td id="nb_cartons_mixte">0</td>
        <td></td>
      </tr>';
print '</table><br>';

print '<div class="comment-section">';
print '<strong>'.$langs->trans("LotComment").'</strong>';
print '<textarea name="commentaire" rows="3">'.($line->comment ?? '').'</textarea>';
print '</div>';

print '<div class="actions-section">';
print '<input type="submit" class="btn btn-primary" value="'.($id_lot ? $langs->trans("UpdateLot") : $langs->trans("CreateLot")).'">';
print '&nbsp;&nbsp;<a class="btn btn-cancel" href="tunnel.php">'.$langs->trans("Cancel").'</a>';
print '</div>';
print '</form>';
print '</div>';

llxFooter();
$db->close();
?>
<script>
/*document.querySelectorAll('.plat_par_carton').forEach(input => {
    const row = input.closest('tr');
    const totalPlats = parseInt(input.dataset.plat);

    function updateCartons() {
        const platParCarton = parseInt(input.value) || 1;
        const nbCartons = Math.floor(totalPlats / platParCarton);
        const restant = totalPlats % platParCarton;

        row.querySelector('.nb_cartons').textContent = nbCartons;
        row.querySelector('.plat_restant').textContent = restant;
    }

    input.addEventListener('input', updateCartons);
    updateCartons();
});*/
</script>
<script>
function recalculerTout() {
    let totalReste = 0;

    document.querySelectorAll('.produit-ligne').forEach(row => {
        const inputPlats = row.querySelector('.nombre_plat');
        const inputPlatParCarton = row.querySelector('.plat_par_carton');
        const nbCartonsCell = row.querySelector('.nb_cartons');
        const restantCell = row.querySelector('.plat_restant');

        const totalPlats = parseInt(inputPlats.value) || 0;
        const platParCarton = parseInt(inputPlatParCarton.value) || 1;

        const nbCartons = Math.floor(totalPlats / platParCarton);
        const restant = totalPlats % platParCarton;

        nbCartonsCell.textContent = nbCartons;
        restantCell.textContent = restant;

        totalReste += restant;
    });

    // Met à jour la ligne mixte
    document.getElementById('nb_plats_mixte').value = totalReste;
    document.getElementById('plat_par_carton_mixte').value = totalReste;

    const platParCartonMixte = parseInt(document.getElementById('plat_par_carton_mixte').value) || 1;
    const nbCartonsMixte = Math.floor(totalReste / platParCartonMixte);
    document.getElementById('nb_cartons_mixte').textContent = nbCartonsMixte;
}

// 🌀 Mettre à jour dès qu'on change un input
document.querySelectorAll('.nombre_plat, .plat_par_carton').forEach(input => {
    input.addEventListener('input', recalculerTout);
});
document.getElementById('plat_par_carton_mixte').addEventListener('input', recalculerTout);

// Calcul initial
recalculerTout();
</script>
