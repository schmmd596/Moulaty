<?php
/**
 * Fichier : edit_lot.php
 * Modification d'un lot existant
 */

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
$id_lot = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');

if ($id_lot <= 0) {
    setEventMessages("⚠️ Lot non spécifié.", null, 'errors');
    header("Location: list_lot.php");
    exit;
}

// Vérifier si l'utilisateur peut modifier
if (empty($user->admin)) {
    accessforbidden('Accès réservé.');
}

// ===============================
// RÉCUPÉRATION DU LOT EXISTANT
// ===============================
$sqlLot = "SELECT l.*, e.ref as entrepot_ref, e.rowid as entrepot_id
           FROM ".MAIN_DB_PREFIX."pech_lot l
           LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = l.fk_entrepot
           WHERE l.rowid = ".(int)$id_lot;
           
$resLot = $db->query($sqlLot);
if (!$resLot || $db->num_rows($resLot) == 0) {
    setEventMessages("❌ Lot introuvable.", null, 'errors');
    header("Location: list_lot.php");
    exit;
}

$lot = $db->fetch_object($resLot);
$fk_entrepot = $lot->entrepot_id;

// Vérifier si le lot peut être modifié
if ($lot->statut == 1) { // Lot validé
    setEventMessages("⚠️ Impossible de modifier un lot validé.", null, 'warnings');
    header("Location: detail_lot.php?id=".$id_lot);
    exit;
}

// ===============================
// RÉCUPÉRATION DES LIGNES EXISTANTES
// ===============================
$sqlLines = "SELECT ld.*, p.ref as produit_ref, p.label as produit_label,
                    ld.mode_misenplat, ld.fk_misenplats
             FROM ".MAIN_DB_PREFIX."pech_lotdet ld
             LEFT JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ld.fk_product
             WHERE ld.fk_lot = ".(int)$id_lot
             ." ORDER BY ld.rowid";
             
$resLines = $db->query($sqlLines);
$lines = [];
$misenplat_ids_all = [];

while ($obj = $db->fetch_object($resLines)) {
    $lines[] = $obj;
    
    // Collecter tous les IDs de misenplat
    if ($obj->mode_misenplat == 'single' && $obj->fk_misenplat) {
        $misenplat_ids_all[] = $obj->fk_misenplat;
    } else if ($obj->mode_misenplat == 'multiple' && !empty($obj->fk_misenplats)) {
        $ids = explode(',', $obj->fk_misenplats);
        $misenplat_ids_all = array_merge($misenplat_ids_all, $ids);
    }
}

// Récupérer les produits mixtes
$produits_mixte = [];
$sqlMixte = "SELECT lm.* FROM ".MAIN_DB_PREFIX."pech_lotdet_mixte_misenplat lm
             INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = lm.fk_lotdet_mixte
             WHERE ld.fk_lot = ".(int)$id_lot;
$resMixte = $db->query($sqlMixte);
if ($resMixte) {
    while ($mix = $db->fetch_object($resMixte)) {
        $produits_mixte[$mix->fk_lotdet_mixte][] = $mix;
    }
}

// ===============================
// REGROUPEMENT PAR PRODUIT (comme dans la création)
// ===============================
$produits_groupes = [];

if (!empty($misenplat_ids_all)) {
    // Récupérer les mises en plat avec leurs informations
    $misenplat_ids_all = array_unique(array_map('intval', $misenplat_ids_all));
    
    $sqlMisenplat = "SELECT 
                        m.rowid AS misenplat_id,
                        m.fk_congelateur,
                        m.nombre_plat,
                        m.nombre_plat_sortie,
                        m.poids_plat,
                        p.rowid AS produit_id,
                        p.label AS produit,
                        p.ref AS produit_ref,
                        s.nom AS fournisseur,
                        b.ref AS bon_ref
                    FROM ".MAIN_DB_PREFIX."pech_misenplat AS m
                    INNER JOIN ".MAIN_DB_PREFIX."product AS p ON p.rowid = m.fk_product
                    LEFT JOIN ".MAIN_DB_PREFIX."societe AS s ON s.rowid = m.fk_congelateur
                    LEFT JOIN ".MAIN_DB_PREFIX."pech_bon_misenplat AS b ON b.rowid = m.fk_bon_misenplat
                    WHERE m.rowid IN (".implode(',', $misenplat_ids_all).")
                    AND m.nombre_plat > m.nombre_plat_sortie
                    ORDER BY p.label, m.rowid";
    
    $resMisenplat = $db->query($sqlMisenplat);
    if ($resMisenplat) {
        while ($obj = $db->fetch_object($resMisenplat)) {
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
                    'bons' => [],
                    'lotdet_id' => null,
                    'plats_utilises_actuels' => 0,
                    'plat_carton_actuel' => 2
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
        
        // Calculer le poids moyen pour chaque produit
        foreach ($produits_groupes as $produit_id => $produit) {
            if ($produits_groupes[$produit_id]['total_plats_disponibles'] > 0) {
                $produits_groupes[$produit_id]['poids_moyen'] = 
                    $produits_groupes[$produit_id]['poids_total'] / $produits_groupes[$produit_id]['total_plats_disponibles'];
            } else {
                $produits_groupes[$produit_id]['poids_moyen'] = 0;
            }
            
            // Trouver la ligne correspondante dans les lignes du lot
            foreach ($lines as $line) {
                if ($line->fk_product == $produit_id && $line->mode_misenplat != 'mixte') {
                    $produits_groupes[$produit_id]['lotdet_id'] = $line->rowid;
                    $produits_groupes[$produit_id]['plats_utilises_actuels'] = $line->plat_carton * $line->nb_carton;
                    $produits_groupes[$produit_id]['plat_carton_actuel'] = $line->plat_carton;
                    break;
                }
            }
        }
    }
}

// ===============================
// AFFICHAGE FORMULAIRE
// ===============================
llxHeader('', $langs->trans("ModifyLot"));

// Titre
$title = $langs->trans("ModifyLot") . " : " . $lot->ref;
print load_fiche_titre($title, '', 'fa-edit');

// CSS (identique à votre fichier de création)
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

.alert {
    padding: 12px;
    border-radius: 4px;
    margin: 10px 0;
}

.alert-warning {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    color: #856404;
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
</style>
';

print '<div class="lot-container">';
print '<form method="POST" action="traitement_update.php">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';
print '<input type="hidden" name="id" value="'.$id_lot.'">';
print '<input type="hidden" name="fk_entrepot" value="'.$fk_entrepot.'">';

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

// 🔥 AFFICHER LES PRODUITS (comme dans la création)
if (isset($produits_groupes) && count($produits_groupes) > 0) {
    $total_restant_mixte = 0;
    
    foreach ($produits_groupes as $produit_id => $produit) {
        $poids_total_format = price($produit['poids_total'], 2);
        $poids_moyen_format = price($produit['poids_moyen'], 3);
        
        // Calculer les valeurs actuelles
        $plats_actuels = $produit['plats_utilises_actuels'];
        $plat_carton_actuel = $produit['plat_carton_actuel'];
        $nb_cartons_actuels = ($plat_carton_actuel > 0) ? floor($plats_actuels / $plat_carton_actuel) : 0;
        $restant_actuel = ($plat_carton_actuel > 0) ? $plats_actuels % $plat_carton_actuel : 0;
        
        $total_restant_mixte += $restant_actuel;
        
        print '<tr class="oddeven produit-ligne" data-produit-id="'.$produit_id.'">';
        
        // Colonne 1 : Produit avec détails
        print '<td>';
        print '<div class="product-header">';
        print '<div class="product-name">'.$produit['produit_label'].' ('.$produit['produit_ref'].')</div>';
        
        print '<div class="product-details">';
        print '<span><i class="fa fa-database"></i> '.$produit['nb_sources'].' '.$langs->trans("sources").'</span>';
        if (!empty($produit['fournisseur'])) {
            print '<span><i class="fa fa-truck"></i> '.$produit['fournisseur'].'</span>';
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
        
        // Colonne 3 : Plats à utiliser (pré-remplis avec les valeurs actuelles)
        print '<td class="text-center">';
        print '<input type="number" class="nombre_plat form-input" 
               name="nombre_plat['.$produit_id.']" 
               value="'.$plats_actuels.'" 
               min="0" max="'.$produit['total_plats_disponibles'].'">';
        print '</td>';
        
        // Colonne 4 : Plats par carton (pré-remplis)
        print '<td class="text-center">';
        print '<input type="number" class="plat_par_carton form-input" 
               name="plat_par_carton['.$produit_id.']" 
               value="'.$plat_carton_actuel.'" min="1">';
        print '</td>';
        
        // Colonne 5 : Nombre de cartons (calculé)
        print '<td class="numeric-cell nb_cartons">'.$nb_cartons_actuels.'</td>';
        
        // Colonne 6 : Restant (calculé)
        print '<td class="numeric-cell plat_restant">'.$restant_actuel.'</td>';
        
        // Colonne 7 : Poids total
        print '<td class="numeric-cell poids_total">'.$poids_total_format.'</td>';
        
        print '</tr>';
        
        // 🔥 CHAMP CACHÉ avec la liste des misenplat pour ce produit
        $misenplat_ids = [];
        foreach ($produit['misenplats'] as $mp) {
            $misenplat_ids[] = $mp['id'];
        }
        print '<input type="hidden" name="misenplat_ids['.$produit_id.']" value="'.implode(',', $misenplat_ids).'">';
        if ($produit['lotdet_id']) {
            print '<input type="hidden" name="lotdet_id['.$produit_id.']" value="'.$produit['lotdet_id'].'">';
        }
    }
} else {
    print '<tr><td colspan="7" class="text-center">';
    print '<div class="alert alert-warning">';
    print '<i class="fa fa-exclamation-triangle"></i> ';
    print $langs->trans("NoProductsToDisplay");
    print '</div>';
    print '</td></tr>';
}

// Ligne produit mixte (calculée à partir des restes)
$plat_par_carton_mixte_actuel = 1;
$nb_cartons_mixte_actuel = 0;
$restant_mixte_actuel = $total_restant_mixte;

// Chercher si une ligne mixte existe déjà
foreach ($lines as $line) {
    if (isset($produits_mixte[$line->rowid])) {
        $plat_par_carton_mixte_actuel = $line->plat_carton;
        $nb_cartons_mixte_actuel = $line->nb_carton;
        $restant_mixte_actuel = $total_restant_mixte % ($plat_par_carton_mixte_actuel > 0 ? $plat_par_carton_mixte_actuel : 1);
        break;
    }
}

print '<tr class="liste_total" id="ligneMixte">
        <td><strong><i class="fa fa-random"></i> '.$langs->trans("MixedProduct").'</strong></td>
        <td class="numeric-cell"><input type="number" id="nb_plats_mixte_dispo" readonly value="'.$total_restant_mixte.'" class="form-input"></td>
        <td class="text-center"><input type="number" id="nb_plats_mixte" name="nb_plats_mixte" readonly value="'.$total_restant_mixte.'" min="0" class="form-input"></td>
        <td class="text-center"><input type="number" id="plat_par_carton_mixte" name="plat_par_carton_mixte" value="'.$plat_par_carton_mixte_actuel.'" min="1" class="form-input"></td>
        <td class="numeric-cell" id="nb_cartons_mixte">'.$nb_cartons_mixte_actuel.'</td>
        <td class="numeric-cell" id="restant_mixte">'.$restant_mixte_actuel.'</td>
        <td class="numeric-cell" id="poids_mixte">0.00</td>
      </tr>';

print '</table><br>';

// Section commentaire
print '<div class="comment-section">';
print '<strong><i class="fa fa-comment"></i> '.$langs->trans("LotComment").'</strong>';
print '<textarea name="commentaire" rows="3" placeholder="'.$langs->trans("AddCommentHere").'">'
      .dol_htmlentities($lot->commentaire).'</textarea>';
print '</div>';

// Boutons d'action
print '<div class="actions-section">';
print '<input type="submit" class="btn btn-primary" value="'.$langs->trans("UpdateLot").'">';
print '&nbsp;&nbsp;<a class="btn btn-cancel" href="detail_lot.php?id='.$id_lot.'">'.$langs->trans("Cancel").'</a>';
print '</div>';

print '</form>';
print '</div>';

// JavaScript pour les calculs dynamiques (identique à la création)
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

// ===============================
// TRAITEMENT DE LA MISE À JOUR
// ===============================
if ($action == 'update' && !empty($_POST)) {
    include_once 'traitement_update.php'; // Nous créerons ce fichier après
}

llxFooter();
$db->close();