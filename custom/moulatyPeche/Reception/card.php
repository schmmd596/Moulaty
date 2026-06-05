<?php
/**
 * Formulaire de réception - Fichier principal
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';
include_once '../user_entrepot_access.php';

global $db, $langs, $conf, $user;

$langs->load("moulatyPeche");
$form = new Form($db);

// =====================
// PARAMÈTRES
// =====================
$id = GETPOST('id', 'int');
$action = GETPOST('action', 'alpha');

// =====================
// CHARGER RÉCEPTION (si édition)
// =====================
$reception = null;
$lines = array();

if (!empty($id)) {
    $sql = "SELECT * FROM ".MAIN_DB_PREFIX."pech_reception WHERE rowid = ".((int)$id);
    $resql = $db->query($sql);
    if ($resql && $db->num_rows($resql) > 0) {
        $reception = $db->fetch_object($resql);

        $sqldet = "SELECT * FROM ".MAIN_DB_PREFIX."pech_receptiondet WHERE fk_reception = ".((int)$id);
        $resdet = $db->query($sqldet);
        if ($resdet) {
            while ($obj = $db->fetch_object($resdet)) {
                $lines[] = $obj;
            }
        }
    }
}

// =====================
// EN-TÊTE
// =====================
llxHeader('', $langs->trans("FicheReception"));

// Inclusion du CSS
print '<link rel="stylesheet" href="reception_style2.css">';

print '<div class="reception-container">';
print '<div class="reception-header" style="background: #c4afd8ff">';
print load_fiche_titre($langs->trans($id ? "ModifierReception" : "NouvelleReception"), '', '');
print '</div>';

// =====================
// FORMULAIRE PRINCIPAL
// =====================
print '<form method="POST" action="traitement_reception.php" class="reception-form">';
print '<input type="hidden" name="token" value="'.newToken().'">';
if ($id) {
    print '<input type="hidden" name="id" value="'.$id.'">';
}

// --- Section Informations principales ---
print '<div class="form-section">';
print '<h3 class="section-title">📋 '.$langs->trans("InformationsPrincipales").'</h3>';
print '<table class="form-table">';

// --- Référence ---
print '<tr><td class="titlefieldcreate">📄 '.$langs->trans("Reference").'</td>';

$resql2 = $db->query("SELECT ref FROM ".MAIN_DB_PREFIX."pech_reception ORDER BY rowid DESC LIMIT 1");
$ref1 = '';
if ($resql2 && $db->num_rows($resql2) > 0) {
    $obj2 = $db->fetch_object($resql2);
    $ref1 = $obj2->ref;
}
$nextNumber = (!empty($ref1) && preg_match('/REC-(\d+)/', $ref1, $matches)) ? ((int)$matches[1] + 1) : 1;
$ref1 = 'REC-' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
$ref = $reception ? $reception->ref : $ref1;

print '<td><input type="text" name="ref" class="form-input" value="'.$ref.'" readonly></td></tr>';

// --- Fournisseur ---
print '<tr><td class="titlefieldcreate"><i class="fa fa-user"></i> '.$langs->trans("Fournisseur").'</td><td>';
print $form->select_company($reception ? $reception->fk_fournisseur : '', 'fk_fournisseur', 's.fournisseur=1', 0, 1, 0, '', 0, 'class="custom-select"');
print '</td></tr>';

// --- Congélateur ---
print '<tr><td class="titlefieldcreate">❄️ '.$langs->trans("Congelateur").'</td><td>';

$labelCategorie = 'Frigo';
$sqlcat = "SELECT rowid FROM ".MAIN_DB_PREFIX."categorie WHERE label = '".$db->escape($labelCategorie)."' AND type = 1";
$resqlcat = $db->query($sqlcat);
$idCategorie = 0;
if ($resqlcat && $objcat = $db->fetch_object($resqlcat)) {
    $idCategorie = $objcat->rowid;
}

$options = array();
if ($idCategorie > 0) {
    $sql = "SELECT s.rowid, s.nom
            FROM ".MAIN_DB_PREFIX."societe AS s
            INNER JOIN ".MAIN_DB_PREFIX."categorie_fournisseur AS cfl 
                ON s.rowid = cfl.fk_soc
            WHERE cfl.fk_categorie = ".((int) $idCategorie)."
            ORDER BY s.nom ASC";
    $resql = $db->query($sql);
    if ($resql) {
        while ($obj = $db->fetch_object($resql)) {
            $options[$obj->rowid] = $obj->nom;
        }
    }
}

print $form->selectarray('fk_congelateur', $options, $reception ? $reception->fk_congelateur : '', 1, 0, 0, '', 0);
print '</td></tr>';

// --- Entrepôt ---
$entrepot_ref = '';
if (!empty($reception) && !empty($reception->fk_entrepot)) {
    $entrepot = new Entrepot($db);
    if ($entrepot->fetch($reception->fk_entrepot) > 0) {
        $entrepot_ref = $entrepot->ref;
    }
}
print '<tr><td class="titlefieldcreate"><i class="fa fa-warehouse"></i> '.$langs->trans("Entrepot").'</td><td>';
print '<input type="text" id="entrepot_ref" name="entrepot_ref" class="form-input" readonly value="'.$entrepot_ref.'" required>';
print '<input type="hidden" id="entrepot_id" name="fk_entrepot" value="'.($reception ? $reception->fk_entrepot : '').'">';
print '</td></tr>';

// Vérification des accès utilisateur
if ($reception) {
    check_user_entrepot_access($reception->fk_entrepot);
}

// --- Date ---
print '<tr><td class="titlefieldcreate">📅 '.$langs->trans("DateCreation").'</td>';
//print '<td><input type="datetime-local" name="date_creation" class="form-input" value="'.($reception ? date('Y-m-d\TH:i', $db->jdate($reception->date_creation)) : date('Y-m-d\TH:i')).'" required></td></tr>';
print '<td><input type="datetime-local" name="date_creation" class="form-input" value="'.($reception ? date('Y-m-d\TH:i', strtotime($reception->date_creation)) : '').'" required></td></tr>';

// --- Commentaire ---
print '<tr><td class="titlefieldcreate">💬 '.$langs->trans("Commentaire").'</td>';
print '<td><textarea name="comment" class="form-input" rows="3">'.($reception ? dol_escape_htmltag($reception->comment) : '').'</textarea></td></tr>';

// --- Poids ---
print '<tr><td class="titlefieldcreate">⚖️ '.$langs->trans("PoidsTotal").' (kg)</td>';
print '<td><input type="text" id="poids_total" name="poids_total" class="form-input" value="'.($reception ? $reception->poids : '0.00').'" readonly></td></tr>';

// --- Montant ---
print '<tr><td class="titlefieldcreate">💰 '.$langs->trans("MontantTotal").' ('.$conf->currency.')</td>';
print '<td><input type="text" id="montant_total" name="montant_total" class="form-input" value="'.($reception ? number_format($reception->montant, 2, ".", "") : '0.00').'" readonly></td></tr>';

print '</table>';
print '</div>'; // .form-section

// =====================
// TABLEAU PRODUITS
// =====================
$sqlp = "SELECT p.rowid, p.label FROM ".MAIN_DB_PREFIX."product p
INNER JOIN ".MAIN_DB_PREFIX."categorie_product cp ON cp.fk_product = p.rowid
INNER JOIN ".MAIN_DB_PREFIX."categorie c ON c.rowid = cp.fk_categorie
WHERE c.label = 'POISSON' ORDER BY p.label ASC";
$resqlp = $db->query($sqlp);
$products = array();

if ($resqlp) {
    while ($obj = $db->fetch_object($resqlp)) {
        $products[$obj->rowid] = $obj->label;
    }
}

print '<div class="form-section">';
print '<h3 class="section-title"><i class="fa fa-fish"></i> '.$langs->trans("ProduitsRecus").'</h3>';

print '<table class="products-table" id="linesTable">';
print '<thead><tr>';

print '<th class="col-product"><i class="fa fa-fish"></i> '.$langs->trans("Produit").'</th>';
print '<th class="col-mode"><i class="fas fa-cogs"></i> '.$langs->trans("Mode").'</th>';
print '<th class="col-caliber"><i class="fas fa-ruler-combined"></i> '.$langs->trans("Calibre").'</th>';
print '<th class="col-vehicle"><i class="fas fa-truck"></i> '.$langs->trans("NbVoiture").'</th>';
print '<th class="col-price"><i class="fas fa-money-bill-wave"></i> '.$langs->trans("PUVoiture").'</th>';
print '<th class="col-weight"><i class="fas fa-weight-hanging"></i> '.$langs->trans("PoidsBrut").'</th>';
print '<th class="col-price"><i class="fas fa-money-bill-wave"></i> '.$langs->trans("PUBrut").'</th>';
print '<th class="col-weight"><i class="fas fa-weight"></i> '.$langs->trans("PoidsNet").'</th>';
print '<th class="col-price"><i class="fas fa-money-bill-wave"></i> '.$langs->trans("PUNET").'</th>';
print '<th class="col-total"><i class="fas fa-calculator"></i> '.$langs->trans("TotalLigne").'</th>';
print '<th class="col-actions"><i class="fas fa-ellipsis-h"></i></th>';

print '</tr></thead>';

$modes = array(1 => 'Voiture', 2 => 'Poids Brut', 3 => 'Poids Net');

// ---------------------------
// LIGNES EXISTANTES
// ---------------------------
if (!empty($lines)) {
    foreach ($lines as $line) {
        print '<tr class="productRow">';
        // Produit
        print '<td class="col-product">'.$form->selectarray('fk_product[]', $products, $line->fk_product, 1, 0, 0, '', 0).'</td>';
        // Mode
        print '<td class="col-mode">'.$form->selectarray('reception_mode[]', $modes, $line->reception_mode, 1, 0, 0, '', 0).'</td>';
        // Calibre
        print '<td class="col-caliber"><input type="text" name="calibre[]" class="form-input" value="'.dol_escape_htmltag($line->calibre).'" required></td>';
        // Voiture
        print '<td class="col-vehicle"><input type="number" step="0.01" min = "0" name="nb_voiture[]" class="form-input calc" value="'.$line->nb_voiture.'"></td>';
        print '<td class="col-price"><input type="number" step="0.01" min = "0" name="prix_voiture[]" class="form-input calc" value="'.$line->prix_voiture.'"></td>';
        // Poids Brut
        print '<td class="col-weight"><input type="number" step="0.01" min = "0" name="poids_brut[]" class="form-input calc" value="'.$line->poids_brut.'"></td>';
        print '<td class="col-price"><input type="number" step="0.01" min = "0" name="pu_brut[]" class="form-input calc" value="'.$line->pu_brut.'"></td>';
        // Poids Net
        print '<td class="col-weight"><input type="number" step="0.01" min = "0" name="poids_net[]" class="form-input calc" value="'.$line->poids_net.'" required></td>';
        print '<td class="col-price"><input type="number" step="0.01" min = "0" name="pu_poids_net[]" class="form-input calc" value="'.$line->pu_poids_net.'"></td>';
        // Total Ligne
        print '<td class="col-total total-line">'.price($line->total_line).'</td>';
        print '<td class="col-actions"><button type="button" onclick="removeRow(this)" class="butActionDelete">✖</button></td>';
        print '</tr>';
    }
}

print '</table>';

print '<button type="button" class="button button-success" onclick="addProductRow()">➕ '.$langs->trans("AjouterLigne").'</button>';
print '</div>'; // .form-section

// =====================
// BOUTONS D'ACTION
// =====================
print '<div class="form-actions">';
print '<input type="submit" class="button button-primary" value="'.($id ? $langs->trans("Modifier") : $langs->trans("Creer")).'">';
if ($id) {
    print '<a class="button button-secondary" href="'.dol_buildpath('detail.php', 1).'?id='.$id.'">'.$langs->trans("Annuler").'</a>';
}
print '</div>';

print '</form>';
print '</div>'; // .reception-container

// =====================
// SCRIPTS JAVASCRIPT
// =====================
?>
<script type="text/javascript">
// Gestion de l'entrepôt
$(document).ready(function() {
    function updateEntrepot(fournisseur_id) {
        if(fournisseur_id && fournisseur_id != "") {
            $.getJSON("get_entrepot_by_fournisseur.php", { id: fournisseur_id }, function(data) {
                if(data.success) {
                    $("#entrepot_ref").val(data.entrepot_label);
                    $("#entrepot_id").val(data.entrepot_id);
                } else {
                    $("#entrepot_ref").val("");
                    $("#entrepot_id").val("");
                }
            });
        } else {
            $("#entrepot_ref").val("");
            $("#entrepot_id").val("");
        }
    }

    $("#fk_congelateur").on("change", function() {
        updateEntrepot($(this).val());
    });

    var initialFournisseur = $("#fk_congelateur").val();
    if(initialFournisseur && initialFournisseur != "") {
        updateEntrepot(initialFournisseur);
    }
});

// Variables globales
var productsList = <?php echo json_encode($products); ?>;
var modesList = {1:'Voiture',2:'Poids Brut',3:'Poids Net'};

// Ajouter une ligne
function addProductRow() {
    const table = document.getElementById("linesTable");
    const row = table.insertRow(-1);
    row.className = "productRow";

    let productSelect = '<select class="custom-select" name="fk_product[]" required>';
    for (let id in productsList) {
        productSelect += `<option value="${id}">${productsList[id]}</option>`;
    }
    productSelect += '</select>';

    let modeSelect = '<select class="custom-select" name="reception_mode[]" required>';
    for (let id in modesList) {
        modeSelect += `<option value="${id}">${modesList[id]}</option>`;
    }
    modeSelect += '</select>';

    row.innerHTML = `
        <td class="col-product">${productSelect}</td>
        <td class="col-mode">${modeSelect}</td>
        <td class="col-caliber"><input type="text" name="calibre[]" class="form-input" required></td>
        <td class="col-vehicle"><input type="number" step="0.01" min = "0" name="nb_voiture[]" class="form-input calc" value="0"></td>
        <td class="col-price"><input type="number" step="0.01" min = "0" name="prix_voiture[]" class="form-input calc" value="0"></td>
        <td class="col-weight"><input type="number" step="0.01" min = "0" name="poids_brut[]" class="form-input calc" value="0"></td>
        <td class="col-price"><input type="number" step="0.01" min = "0" name="pu_brut[]" class="form-input calc" value="0"></td>
        <td class="col-weight"><input type="number" step="0.01" min = "0" name="poids_net[]" class="form-input calc" value="0" required></td>
        <td class="col-price"><input type="number" step="0.01" min = "0" name="pu_poids_net[]" class="form-input calc" value="0"></td>
        <td class="col-total total-line">0.00</td>
        <td class="col-actions"><button type="button" onclick="removeRow(this)" class="butActionDelete">✖</button></td>
    `;
    
    setRequiredFields(row);
    bindCalculations();
}

function setRequiredFields(row) {
    const modeValue = parseInt(row.querySelector("[name='reception_mode[]']").value);
    const nbVoiture = row.querySelector("[name='nb_voiture[]']");
    const puVoiture = row.querySelector("[name='prix_voiture[]']");
    const poidsBrut = row.querySelector("[name='poids_brut[]']");
    const puBrut = row.querySelector("[name='pu_brut[]']");
    const puNet = row.querySelector("[name='pu_poids_net[]']");

    [nbVoiture, puVoiture, poidsBrut, puBrut, puNet].forEach(el => el.removeAttribute('required'));

    if(modeValue === 1) {
        nbVoiture.required = true;
        puVoiture.required = true;
    } else if(modeValue === 2) {
        poidsBrut.required = true;
        puBrut.required = true;
    } else if(modeValue === 3) {
        puNet.required = true;
    }

    row.querySelector("[name='fk_product[]']").required = true;
    row.querySelector("[name='reception_mode[]']").required = true;
    row.querySelector("[name='calibre[]']").required = true;
    row.querySelector("[name='poids_net[]']").required = true;
}

$(document).on('change', "[name='reception_mode[]']", function(){
    const row = $(this).closest("tr")[0];
    setRequiredFields(row);
});

function removeRow(btn) {
    btn.closest("tr").remove();
    updateTotals();
}

function bindCalculations() {
    document.querySelectorAll(".calc").forEach(input => {
        input.oninput = function() {
            const row = this.closest("tr");
            calculateRowTotal(row);
            updateTotals();
        };
    });
}

function calculateRowTotal(row) {
    const mode = parseInt(row.querySelector("[name='reception_mode[]']").value) || 3;

    const nbVoiture = parseFloat(row.querySelector("[name='nb_voiture[]']").value) || 0;
    const prixVoiture = parseFloat(row.querySelector("[name='prix_voiture[]']").value) || 0;
    const poidsBrut = parseFloat(row.querySelector("[name='poids_brut[]']").value) || 0;
    const puBrut = parseFloat(row.querySelector("[name='pu_brut[]']").value) || 0;
    const poidsNet = parseFloat(row.querySelector("[name='poids_net[]']").value) || 0;
    const puNet = parseFloat(row.querySelector("[name='pu_poids_net[]']").value) || 0;

    let totalLigne = 0;

    if(mode === 1) {
        totalLigne = nbVoiture * prixVoiture;
    } else if(mode === 2) {
        totalLigne = poidsBrut * puBrut;
    } else if(mode === 3) {
        totalLigne = poidsNet * puNet;
    }

    row.querySelector(".total-line").innerText = totalLigne.toFixed(2);
}

function updateTotals() {
    let totalMontant = 0;
    let totalPoids = 0;

    document.querySelectorAll(".productRow").forEach(row => {
        calculateRowTotal(row);
        const totalLigne = parseFloat(row.querySelector(".total-line").innerText) || 0;
        totalMontant += totalLigne;

        const poidsNet = parseFloat(row.querySelector("[name='poids_net[]']").value) || 0;
        totalPoids += poidsNet;
    });

    document.getElementById("montant_total").value = totalMontant.toFixed(2);
    document.getElementById("poids_total").value = totalPoids.toFixed(2);
}

// Initialiser
bindCalculations();
updateTotals();
</script>

<?php
llxFooter();
$db->close();
?>