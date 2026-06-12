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

require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
$ref_entrepot ='';
$entrepot = new Entrepot($db);
if ($entrepot->fetch((int) $fk_entrepot) > 0) {
    $ref_entrepot = $entrepot->ref;
}
// ===============================
// RÉCUPÉRATION ET REGROUPEMENT DES DONNÉES
// ===============================
if ($id_lot > 0) {
    // 🔁 MODIFICATION D'UN LOT EXISTANT
    $sql = "SELECT 
                l.ref AS lot_ref,
                l.commentaire AS comment,
                d.rowid AS lotdet_id,
                d.fk_product AS produit_id,
                d.plat_carton,
                d.nb_carton,
                d.poids_carton,
                d.commentaire AS det_comment,
                p.label AS produit
            FROM ".MAIN_DB_PREFIX."pech_lot AS l
            LEFT JOIN ".MAIN_DB_PREFIX."pech_lotdet AS d ON d.fk_lot = l.rowid
            LEFT JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = d.fk_product
            WHERE l.rowid = ".(int)$id_lot;
            
    $resql = $db->query($sql);
    $lines = [];
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $lines[] = $obj;
        }
    }
} else {
    // 🆕 CRÉATION À PARTIR DES MISES EN PLAT
    if (empty($selected_ids)) {
        setEventMessages("⚠️ Aucune mise en plat sélectionnée.", null, 'warnings');
        header("Location: tunnel.php");
        exit;
    }

    // Récupérer toutes les mises en plat sélectionnées
    $sql = "SELECT 
                m.rowid AS misenplat_id,
                m.fk_congelateur,
                m.nombre_plat,
                m.nombre_plat_sortie,
                m.poids_plat,
                p.rowid AS produit_id,
                p.label AS produit,
                p.ref AS produit_ref,
                s.nom AS fournisseur,
                b.ref AS bon_ref,
                b.fk_entrepot
            FROM ".MAIN_DB_PREFIX."pech_misenplat AS m
            INNER JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = m.fk_product
            LEFT JOIN ".MAIN_DB_PREFIX."societe AS s ON s.rowid = m.fk_congelateur
            LEFT JOIN ".MAIN_DB_PREFIX."pech_bon_misenplat AS b ON b.rowid = m.fk_bon_misenplat
            WHERE m.rowid IN (".implode(',', array_map('intval', $selected_ids)).")
            AND m.nombre_plat > m.nombre_plat_sortie
            ORDER BY p.label, m.rowid";
    
    $resql = $db->query($sql);
    if (!$resql) {
        dol_print_error($db, $sql);
        exit;
    }
    
    // ===============================
    // 🔥 REGROUPEMENT PAR PRODUIT
    // ===============================
    $produits_groupes = [];
    $total_plats_mixte = 0;
    
    // ===============================
    // 🔥 REGROUPEMENT PAR PRODUIT
    // ===============================
    $produits_groupes = [];
    $fk_entrepot_from_misenplat = 0;
    
    while ($obj = $db->fetch_object($resql)) {
        if (empty($fk_entrepot_from_misenplat) && !empty($obj->fk_entrepot)) {
            $fk_entrepot_from_misenplat = (int)$obj->fk_entrepot;
        }
        $produit_id = $obj->produit_id;
    $plats_disponibles = $obj->nombre_plat - $obj->nombre_plat_sortie;
    
    // Si c'est la première fois qu'on voit ce produit
    if (!isset($produits_groupes[$produit_id])) {
        $produits_groupes[$produit_id] = [
            'produit_id' => $obj->produit_id,
            'produit_label' => $obj->produit,
            'produit_ref' => $obj->produit_ref,
            'fournisseur' => $obj->fournisseur,
            'misenplats' => [],
            'total_plats' => 0,
            'total_plats_disponibles' => 0,
            'poids_total' => 0,
            'poids_moyen' => 0,
            'nb_sources' => 0,
            'bons' => []
        ];
    }
    
    // Ajouter cette misenplat à la liste
    $produits_groupes[$produit_id]['misenplats'][] = [
        'id' => $obj->misenplat_id,
        'nombre_plat' => $obj->nombre_plat,
        'nombre_plat_sortie' => $obj->nombre_plat_sortie,
        'plats_disponibles' => $plats_disponibles,
        'poids_plat' => $obj->poids_plat,
        'bon_ref' => $obj->bon_ref
    ];
    
    // Ajouter le bon à la liste (sans doublons)
    if ($obj->bon_ref && !in_array($obj->bon_ref, $produits_groupes[$produit_id]['bons'])) {
        $produits_groupes[$produit_id]['bons'][] = $obj->bon_ref;
    }
    
    // Mettre à jour les totaux
    $produits_groupes[$produit_id]['total_plats'] += $obj->nombre_plat;
    $produits_groupes[$produit_id]['total_plats_disponibles'] += $plats_disponibles;
    $produits_groupes[$produit_id]['poids_total'] += ($obj->poids_plat * $plats_disponibles);
    $produits_groupes[$produit_id]['nb_sources'] = count($produits_groupes[$produit_id]['misenplats']);
}

// Calculer le poids moyen pour chaque produit - SANS & (sans référence)
foreach ($produits_groupes as $produit_id => $produit) {
    if ($produits_groupes[$produit_id]['total_plats_disponibles'] > 0) {
        $produits_groupes[$produit_id]['poids_moyen'] = 
            $produits_groupes[$produit_id]['poids_total'] / $produits_groupes[$produit_id]['total_plats_disponibles'];
    } else {
        $produits_groupes[$produit_id]['poids_moyen'] = 0;
    }
}

if (empty($fk_entrepot)) {
    $fk_entrepot = $fk_entrepot_from_misenplat;
    if ($entrepot->fetch((int) $fk_entrepot) > 0) {
        $ref_entrepot = $entrepot->ref;
    }
}

}


// ===============================
// AFFICHAGE FORMULAIRE
// ===============================
llxHeader('', ($id_lot ? $langs->trans("ModifyLot") : $langs->trans("CreateNewLot")));

// Titre
if ($id_lot > 0) {
    $title = $langs->trans("ModifyLot") . " " . ($lines[0]->lot_ref ?? '');
} else {
    $title = $langs->trans("CreateNewLot");
}
print load_fiche_titre($title);

// CSS
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
    font-size: 13px;
}

.lot-table th {
    background: linear-gradient(135deg, #dbeaf5ff, #d9dcddff);
    color: black;
    padding: 12px 8px;
    font-weight: bold;
    text-align: center;
    border: none;
    font-size: 14px;
}

.lot-table td {
    padding: 10px 8px;
    border-bottom: 1px solid #e9ecef;
    vertical-align: top;
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
    font-size: 12px;
    text-align: center;
    width: 80px;
}

.form-input:focus {
    border-color: #3498db;
    outline: none;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
}

.product-header {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.product-name {
    font-weight: bold;
    margin-bottom: 3px;
}

.product-details {
    font-size: 11px;
    color: #666;
}

.product-details span {
    display: inline-block;
    margin-right: 8px;
    padding: 2px 6px;
    background: #f1f1f1;
    border-radius: 3px;
    margin-bottom: 2px;
}

.product-bons {
    font-size: 10px;
    color: #888;
    margin-top: 3px;
    font-style: italic;
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
    font-size: 13px;
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

.text-center {
    text-align: center;
}

.text-right {
    text-align: right;
}

.numeric-cell {
    text-align: center;
    font-family: monospace;
    font-weight: 600;
}

@media (max-width: 768px) {
    .lot-container {
        padding: 10px;
    }
    
    .lot-table {
        font-size: 11px;
    }
    
    .lot-table th,
    .lot-table td {
        padding: 8px 4px;
    }
    
    .form-input {
        width: 60px;
        font-size: 11px;
    }
}
    .warehouse-info {
    background: #e8f4fd;
    padding: 15px;
    border-radius: 6px;
    margin-bottom: 20px;
    border-left: 4px solid #007bff;
}

.warehouse-info strong {
    color: #0056b3;
}
</style>
';

print '<div class="lot-container">';
print '<form method="POST" action="traitement.php">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="save">';
print '<input type="hidden" name="fk_entrepot" value="'.$fk_entrepot.'">';
if ($id_lot > 0) print '<input type="hidden" name="id_lot" value="'.$id_lot.'">';

// Passer les IDs sélectionnés
if (!empty($selected_ids)) {
    foreach ($selected_ids as $id) {
        print '<input type="hidden" name="select_misenplat[]" value="'.$id.'">';
    }
}

// Affichage de l'entrepôt
print '<div class="warehouse-info">';
print '<strong><i class="fa fa-warehouse"></i> '.$langs->trans("Warehouse").' :</strong> '.dol_escape_htmltag($ref_entrepot);
print '</div>';
$date_creation_val = ''; // initialement vide

print '<div class="form-group" style="margin-bottom:20px;">';
print '<label for="date_creation" style="display:block; margin-bottom:6px; font-weight:600; color:#2c3e50;">';
print '<i class="fa fa-calendar-alt"></i> '.$langs->trans("CreationDate").' :';
print '</label>';
print '<div style="position:relative; display:inline-block;">';
print '<input type="datetime-local" id="date_creation" name="date_creation" value="" required ';
print 'style="padding:10px 12px 10px 40px; border:2px solid #e74c3c; border-radius:6px; ';
print 'font-size:14px; width:250px; transition:all 0.3s ease; background:#fff; ';
print 'color:#333; box-shadow:0 2px 5px rgba(0,0,0,0.05);" ';
print 'oninput="if(this.value){this.style.borderColor=\'#27ae60\'; this.style.boxShadow=\'0 2px 8px rgba(39,174,96,0.2)\';} else {this.style.borderColor=\'#e74c3c\'; this.style.boxShadow=\'0 2px 5px rgba(0,0,0,0.05)\';}">';
print '<i class="fa fa-clock" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#7f8c8d;"></i>';
print '</div>';
print '</div>';

print '<table class="lot-table" id="tableLots">';
print '<tr class="liste_titre">
        <th style="width: 25%;">'.$langs->trans("Product").'</th>
        <th style="width: 10%;" class="text-center">'.$langs->trans("Available").'</th>
        <th style="width: 10%;" class="text-center">'.$langs->trans("ToUse").'</th>
        <th style="width: 12%;" class="text-center">'.$langs->trans("PerCarton").'</th>
        <th style="width: 10%;" class="text-center">'.$langs->trans("Cartons").'</th>
        <th style="width: 10%;" class="text-center">'.$langs->trans("Remaining").'</th>
        <th style="width: 13%;" class="text-center">'.$langs->trans("Weight").' (kg)</th>
      </tr>';

if ($id_lot > 0) {
    // MODIFICATION : afficher les lignes existantes du lot
    foreach ($lines as $line) {
        print '<tr class="oddeven produit-ligne">';
        print '<td>'.$line->produit.'</td>';
        print '<td class="numeric-cell"><input type="number" class="plats_disponibles form-input" value="0" readonly></td>';
        print '<td class="text-center"><input type="number" class="nombre_plat form-input" name="nombre_plat_'.$line->lotdet_id.'" 
               value="0" min="0"></td>';
        print '<td class="text-center"><input type="number" class="plat_par_carton form-input" name="plat_par_carton_'.$line->lotdet_id.'" 
               value="'.($line->plat_carton ?? 2).'" min="1"></td>';
        print '<td class="numeric-cell nb_cartons">0</td>';
        print '<td class="numeric-cell plat_restant">0</td>';
        print '<td class="numeric-cell poids_total">0.00</td>';
        print '</tr>';
    }
} else {
    // 🔥 CRÉATION : AFFICHER UNE LIGNE PAR PRODUIT REGROUPÉ
    if (isset($produits_groupes) && count($produits_groupes) > 0) {
        foreach ($produits_groupes as $produit_id => $produit) {
            $poids_total_format = price($produit['poids_total'], 2);
            $poids_moyen_format = price($produit['poids_moyen'], 3);
            
            print '<tr class="oddeven produit-ligne" data-produit-id="'.$produit_id.'">';
            
            // Colonne 1 : Produit avec détails
            print '<td>';
            print '<div class="product-header">';
            print '<div class="product-name">'.$produit['produit_label'].' ('.$produit['produit_ref'].')</div>';
            
            print '<div class="product-details">';
            print '<span><i class="fa fa-database"></i> '.$produit['nb_sources'].' '.$langs->trans("sources").'</span>';
            if (!empty($produit['fournisseur'])) {
                //print '<span><i class="fa fa-truck"></i> '.$produit['fournisseur'].'</span>';
            }
            print '<span><i class="fa fa-balance-scale"></i> '.$poids_moyen_format.' kg/plat</span>';
            print '</div>';
            
            if (!empty($produit['bons'])) {
                print '<div class="product-bons">';
                print '<i class="fa fa-file-text"></i> Bons: '.implode(', ', $produit['bons']);
                print '</div>';
            }
            print '</div>';
            print '</td>';
            
            // Colonne 2 : Plats disponibles
            print '<td class="numeric-cell">';
            print '<strong class="plats_disponibles">'.$produit['total_plats_disponibles'].'</strong>';
            print '</td>';
            
            // Colonne 3 : Plats à utiliser
            print '<td class="text-center">';
            print '<input type="number" class="nombre_plat form-input" 
                   name="nombre_plat['.$produit_id.']" 
                   value="'.$produit['total_plats_disponibles'].'" 
                   min="0" max="'.$produit['total_plats_disponibles'].'">';
            print '</td>';
            
            // Colonne 4 : Plats par carton
            print '<td class="text-center">';
            print '<input type="number" class="plat_par_carton form-input" 
                   name="plat_par_carton['.$produit_id.']" 
                   value="2" min="1">';
            print '</td>';
            
            // Colonne 5 : Nombre de cartons (calculé)
            print '<td class="numeric-cell nb_cartons">0</td>';
            
            // Colonne 6 : Restant (calculé)
            print '<td class="numeric-cell plat_restant">0</td>';
            
            // Colonne 7 : Poids total
            print '<td class="numeric-cell poids_total">'.$poids_total_format.'</td>';
            
            print '</tr>';
            
            // 🔥 CHAMP CACHÉ avec la liste des misenplat pour ce produit
            $misenplat_ids = [];
            foreach ($produit['misenplats'] as $mp) {
                $misenplat_ids[] = $mp['id'];
            }
            print '<input type="hidden" name="misenplat_ids['.$produit_id.']" value="'.implode(',', $misenplat_ids).'">';
        }
    } else {
        print '<tr><td colspan="7" class="text-center">';
        print '<div class="alert alert-warning">';
        print '<i class="fa fa-exclamation-triangle"></i> ';
        print $langs->trans("NoProductsToDisplay");
        print '</div>';
        print '</td></tr>';
    }
}

// Ligne produit mixte
print '<tr class="liste_total" id="ligneMixte">
        <td><strong><i class="fa fa-random"></i> AF_MIX</strong></td>
        <td class="numeric-cell"><input type="number" id="nb_plats_mixte_dispo" readonly value="0" class="form-input"></td>
        <td class="text-center"><input type="number" id="nb_plats_mixte" name="nb_plats_mixte" readonly value="0" min="0" class="form-input"></td>
        <td class="text-center"><input type="number" id="plat_par_carton_mixte" name="plat_par_carton_mixte" value="1" min="1" class="form-input"></td>
        <td class="numeric-cell" id="nb_cartons_mixte">0</td>
        <td class="numeric-cell" id="restant_mixte">0</td>
        <td class="numeric-cell" id="poids_mixte">0.00</td>
      </tr>';

print '</table><br>';

// Section commentaire
print '<div class="comment-section">';
print '<strong><i class="fa fa-comment"></i> '.$langs->trans("LotComment").'</strong>';
print '<textarea name="commentaire" rows="3" placeholder="'.$langs->trans("AddCommentHere").'"></textarea>';
print '</div>';

// Boutons d'action
print '<div class="actions-section">';
print '<input type="submit" class="btn btn-primary" value="'.($id_lot ? $langs->trans("UpdateLot") : $langs->trans("CreateLot")).'">';
print '&nbsp;&nbsp;<a class="btn btn-cancel" href="tunnel.php">'.$langs->trans("Cancel").'</a>';
print '</div>';

print '</form>';
print '</div>';

// JavaScript pour les calculs dynamiques
print '
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Fonction pour recalculer une ligne produit
    function recalculerLigne(row) {
        const platsDisponibles = parseInt(row.querySelector(".plats_disponibles").textContent) || 0;
        const inputPlats = row.querySelector(".nombre_plat");
        const inputCarton = row.querySelector(".plat_par_carton");
        const nbCartonsCell = row.querySelector(".nb_cartons");
        const restantCell = row.querySelector(".plat_restant");
        
        // Limiter la valeur d\'entrée
        let platsUtilises = parseInt(inputPlats.value) || 0;
        if (platsUtilises > platsDisponibles) {
            platsUtilises = platsDisponibles;
            inputPlats.value = platsDisponibles;
        }
        
        const platsParCarton = parseInt(inputCarton.value) || 1;
        
        if (platsParCarton > 0 && platsUtilises > 0) {
            const nbCartons = Math.floor(platsUtilises / platsParCarton);
            const restant = platsUtilises % platsParCarton;
            
            nbCartonsCell.textContent = nbCartons;
            restantCell.textContent = restant;
            
            return {
                restant: restant,
                platsUtilises: platsUtilises
            };
        } else {
            nbCartonsCell.textContent = "0";
            restantCell.textContent = "0";
            return { restant: 0, platsUtilises: 0 };
        }
    }
    
    // Fonction pour recalculer le total mixte
    function recalculerTotalMixte() {
        let totalRestant = 0;
        
        document.querySelectorAll(".produit-ligne").forEach(row => {
            const result = recalculerLigne(row);
            totalRestant += result.restant;
        });
        
        // Mettre à jour la ligne mixte
        document.getElementById("nb_plats_mixte_dispo").value = totalRestant;
        document.getElementById("nb_plats_mixte").value = totalRestant;
        
        const platsParCartonMixte = parseInt(document.getElementById("plat_par_carton_mixte").value) || 1;
        
        if (platsParCartonMixte > 0 && totalRestant > 0) {
            const nbCartonsMixte = Math.floor(totalRestant / platsParCartonMixte);
            const restantMixte = totalRestant % platsParCartonMixte;
            
            document.getElementById("nb_cartons_mixte").textContent = nbCartonsMixte;
            document.getElementById("restant_mixte").textContent = restantMixte;
        } else {
            document.getElementById("nb_cartons_mixte").textContent = "0";
            document.getElementById("restant_mixte").textContent = "0";
        }
        
        return totalRestant;
    }
    
    // Initialiser les événements
    document.querySelectorAll(".nombre_plat, .plat_par_carton").forEach(input => {
        input.addEventListener("input", recalculerTotalMixte);
    });
    
    document.getElementById("plat_par_carton_mixte").addEventListener("input", recalculerTotalMixte);
    
    // Calcul initial
    recalculerTotalMixte();
    
    // Validation du formulaire
    document.querySelector("form").addEventListener("submit", function(e) {
        let isValid = true;
        const errors = [];
        
        document.querySelectorAll(".produit-ligne").forEach(row => {
            const inputPlats = row.querySelector(".nombre_plat");
            const inputCarton = row.querySelector(".plat_par_carton");
            const platsDisponibles = parseInt(row.querySelector(".plats_disponibles").textContent) || 0;
            const platsUtilises = parseInt(inputPlats.value) || 0;
            const cartonValue = parseInt(inputCarton.value) || 1;
            
            if (platsUtilises > platsDisponibles) {
                errors.push("× Vous ne pouvez pas utiliser plus de plats que disponibles");
                isValid = false;
                row.style.backgroundColor = "#ffebee";
            } else if (platsUtilises > 0 && cartonValue <= 0) {
                errors.push("× Le nombre de plats par carton doit être supérieur à 0");
                isValid = false;
                inputCarton.style.borderColor = "#f44336";
            }
        });
        
        if (!isValid) {
            e.preventDefault();
            alert("Veuillez corriger les erreurs suivantes :\\n\\n" + errors.join("\\n"));
        }
    });
});
</script>
';

llxFooter();
$db->close();