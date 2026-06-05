<?php
/**
 * Facturation Sortie - Interface de création de factures
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';

// ============================================================================
// 🔹 INCLUSION DES FONCTIONS DE DEVISE
// ============================================================================
require_once '../functions.php';

global $db, $langs, $user;

// ============================================================================
// 🔹 INITIALISATION ET CHARGEMENT
// ============================================================================
$langs->loadLangs(['abricot@abricot', 'main', 'womapeche@womapeche']);
$form = new Form($db);

// ============================================================================
// 🔹 RÉCUPÉRATION DE L'ID DE SORTIE
// ============================================================================
$id_sortie = GETPOST('id_sortie', 'int');
if ($id_sortie <= 0) accessforbidden($langs->trans("IdentifiantSortieInvalide"));

// ============================================================================
// 🔹 RÉCUPÉRATION DES INFORMATIONS DE SORTIE
// ============================================================================
$sqlSortie = "SELECT s.*, u.firstname, u.lastname, s.fk_entrepot_source
              FROM ".MAIN_DB_PREFIX."pech_sortie AS s
              LEFT JOIN ".MAIN_DB_PREFIX."user AS u ON u.rowid = s.fk_user
              WHERE s.rowid = ".((int)$id_sortie);
$resSortie = $db->query($sqlSortie);

if (!$resSortie || $db->num_rows($resSortie) == 0) exit($langs->trans("SortieIntrouvable"));

$sortie = $db->fetch_object($resSortie);

// ============================================================================
// 🔹 RÉCUPÉRATION DE LA DEVISE DE L'ENTREPÔT SOURCE
// ============================================================================
$id_devise_entrepot = getDeviseEntrepot($db, $sortie->fk_entrepot_source);
$code_devise_entrepot = getCodeDeviseFromId($db, $id_devise_entrepot);
$symbole_devise = $code_devise_entrepot;

// ============================================================================
// 🔹 RÉCUPÉRATION DES PRODUITS LIÉS À LA SORTIE
// ============================================================================
$sqlProd = "SELECT p.rowid, pr.ref, pr.label, p.nb_carton, p.poids_total
            FROM ".MAIN_DB_PREFIX."pech_sortiedetprod AS p
            LEFT JOIN ".MAIN_DB_PREFIX."product AS pr ON pr.rowid = p.fk_product
            WHERE p.fk_sortie = ".(int)$id_sortie;
$resProd = $db->query($sqlProd);

// ============================================================================
// 🔹 CALCUL DU NOMBRE RÉEL DE CARTONS
// ============================================================================
$sqlNbCarton = "SELECT COUNT(c.rowid) AS nb_carton
                FROM ".MAIN_DB_PREFIX."pech_sortiedetcarton c
                INNER JOIN ".MAIN_DB_PREFIX."pech_sortiedetprod sd 
                    ON sd.rowid = c.fk_sortiedetprod
                WHERE sd.fk_sortie = ".(int)$id_sortie;

$resNb = $db->query($sqlNbCarton);
$objNb = $db->fetch_object($resNb);
$nb_carton_reel = $objNb ? (int)$objNb->nb_carton : 0;

// ============================================================================
// 🔹 RÉCUPÉRATION DU FOURNISSEUR LIÉ À L'ENTREPÔT
// ============================================================================
$sqlFournisseur = "SELECT s.rowid AS id_fournisseur, s.nom AS nom_fournisseur
                   FROM ".MAIN_DB_PREFIX."societe AS s
                   LEFT JOIN ".MAIN_DB_PREFIX."societe_extrafields AS sf 
                       ON sf.fk_object = s.rowid
                   WHERE sf.entrepot = ".((int)$sortie->fk_entrepot_source);
$resFournisseur = $db->query($sqlFournisseur);

if (!$resFournisseur) exit($langs->trans("ErreurRecuperationFournisseur"));
if ($db->num_rows($resFournisseur) == 0) exit($langs->trans("AucunFournisseurTrouve"));

$fournisseur = $db->fetch_object($resFournisseur);

// ============================================================================
// 🔹 RÉCUPÉRATION DES SERVICES DISPONIBLES
// ============================================================================
$sqlServices = "SELECT rowid, ref, label, price
                FROM ".MAIN_DB_PREFIX."product 
                WHERE fk_product_type = 1"; // 1 = service
$resServices = $db->query($sqlServices);
$services = [];
while ($obj = $db->fetch_object($resServices)) $services[] = $obj;

// ============================================================================
// 🔹 EN-TÊTE DE PAGE
// ============================================================================
llxHeader('', $langs->trans('FacturationSortie'));
print '<link rel="stylesheet" href="../fact_style.css">';

print '<div class="facturation-container">';

// Header avec style CSS
print '<div class="facturation-header">';
print '<h1><i class="fa fa-file-invoice-dollar"></i> '.$langs->trans("FacturationSortie").' '.dol_escape_htmltag($sortie->ref).'</h1>';
print '<div class="devise-info">';
print '<span class="badge badge-info">';
print '<i class="fa fa-money-bill-wave"></i> ';
print $langs->trans("Devise").': '.$symbole_devise.' ('.$code_devise_entrepot.')';
print '</span>';
print '</div>';
print '</div>';

// ============================================================================
// 🔹 FORMULAIRE PRINCIPAL
// ============================================================================
print '<form method="POST" action="facturation_process_m.php?id_sortie='.$id_sortie.'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="fk_entrepot" value="'.$sortie->fk_entrepot_source.'">';
print '<input type="hidden" name="code_devise_entrepot" value="'.$code_devise_entrepot.'">';
print '<input type="hidden" name="id_devise_entrepot" value="'.$id_devise_entrepot.'">';

// ============================================================================
// 🔹 INFORMATIONS DE LA SORTIE
// ============================================================================
print '<div class="facturation-info-section">';
print '<div class="facturation-title">';
print '<i class="fa fa-info-circle"></i> '.$langs->trans("InformationsSortie");
print '</div>';

print '<div class="facturation-info-grid">';
print '<div class="info-item">';
print '<label>'.$langs->trans("Reference").'</label>';
print '<div class="info-value">'.dol_escape_htmltag($sortie->ref).'</div>';
print '</div>';

print '<div class="info-item">';
print '<label>'.$langs->trans("Fournisseur").'</label>';
print '<div class="info-value">'.dol_escape_htmltag($fournisseur->nom_fournisseur).'</div>';
print '<input type="hidden" name="fk_fournisseur" value="'.$fournisseur->id_fournisseur.'">';
print '</div>';

print '<div class="info-item">';
print '<label>'.$langs->trans("Utilisateur").'</label>';
print '<div class="info-value">'.dol_escape_htmltag($sortie->firstname.' '.$sortie->lastname).'</div>';
print '</div>';

if ($sortie->commentaire) {
    print '<div class="info-item full-width">';
    print '<label>'.$langs->trans("Commentaire").'</label>';
    print '<div class="info-value">'.dol_escape_htmltag($sortie->commentaire).'</div>';
    print '</div>';
}

print '<div class="info-item">';
print '<label>'.$langs->trans("NbCartons").'</label>';
print '<div class="info-value highlight">'.price($nb_carton_reel).' '.$langs->trans("Cartons").'</div>';
print '</div>';

print '<div class="info-item">';
print '<label>'.$langs->trans("PoidsTotal").'</label>';
print '<div class="info-value highlight">'.price($sortie->poids_total).' kg</div>';
print '</div>';

print '<div class="info-item">';
print '<label>'.$langs->trans("DeviseEntrepot").'</label>';
print '<div class="info-value highlight">'.$symbole_devise.' ('.$code_devise_entrepot.')</div>';
print '</div>';
print '</div>'; // .facturation-info-grid
print '</div>'; // .facturation-info-section

// ============================================================================
// 🔹 SECTION SERVICES
// ============================================================================
print '<div class="facturation-services-section">';
print '<div class="facturation-title">';
print '<i class="fa fa-cogs"></i> '.$langs->trans("Services");
print '<span class="devise-note">('.$langs->trans("PrixEn").' '.$symbole_devise.')</span>';
print '</div>';

print '<div class="facturation-table-container">';
print '<table class="facturation-table" id="table_services">';
print '<thead>';
print '<tr>';
print '<th><i class="fa fa-cube"></i> '.$langs->trans("Service").'</th>';
print '<th class="center"><i class="fa fa-balance-scale"></i> '.$langs->trans("QuantiteKg").'</th>';
print '<th class="center"><i class="fa fa-tag"></i> '.$langs->trans("PrixUnitaire").' ('.$symbole_devise.')</th>';
print '<th class="center"><i class="fa fa-calculator"></i> '.$langs->trans("Total").' ('.$symbole_devise.')</th>';
print '<th class="center"><i class="fa fa-gears"></i> '.$langs->trans("Actions").'</th>';
print '</tr>';
print '</thead>';
print '<tbody>';

// Service stockage fixe
$sqlStockage = "SELECT rowid, ref, label, price
                FROM ".MAIN_DB_PREFIX."product
                WHERE label = 'stockage' AND fk_product_type = 1
                LIMIT 1";
$resStockage = $db->query($sqlStockage);

print '<tr>';
print '<td>';
if ($resStockage && $db->num_rows($resStockage) > 0) {
    $stockage = $db->fetch_object($resStockage);
    print '<input type="hidden" name="service_id[]" value="'.$stockage->rowid.'">';
    print '<div class="service-name">';
    print '<i class="fa fa-warehouse"></i> ';
    print '<strong>'.dol_escape_htmltag($langs->trans("ServiceStockage")).'</strong>';
    print '</div>';
} else {
    print '<span class="error-text">'.$langs->trans("ServiceStockageIntrouvable").'</span>';
}
print '</td>';
print '<td class="center"><input type="number" step="0.01" name="service_qte[]" value="'.number_format($nb_carton_reel,2,'.','').'" class="facturation-input qte-input" min="0"></td>';
print '<td class="center"><input type="number" step="0.01" name="service_pu[]" value="'.($stockage->price ?? 0).'" class="facturation-input pu-input" min="0"></td>';
print '<td class="center"><input type="text" name="service_total[]" readonly class="facturation-input total-input"></td>';
print '<td class="center"><button type="button" class="facturation-btn danger" onclick="removeRow(this)"><i class="fa fa-trash"></i></button></td>';
print '</tr>';

print '</tbody>';
print '</table>';
print '</div>'; // .facturation-table-container

// Bouton ajouter service
print '<div class="facturation-actions">';
print '<button type="button" class="facturation-btn primary" onclick="addServiceRow()">';
print '<i class="fa fa-plus"></i> '.$langs->trans("AjouterService");
print '</button>';
print '</div>';
print '</div>'; // .facturation-services-section

// ============================================================================
// 🔹 TOTAL GÉNÉRAL
// ============================================================================
print '<div class="facturation-total-section">';
print '<div class="total-grid">';
print '<div class="total-label">'.$langs->trans("TotalGeneral").' ('.$symbole_devise.')</div>';
print '<div class="total-value">';
print '<input type="text" id="grand_total" value="0.00" readonly class="grand-total-input">';
print '</div>';
print '</div>';
print '</div>';

// ============================================================================
// 🔹 BOUTON DE VALIDATION
// ============================================================================
print '<div class="facturation-submit-section">';
print '<button type="submit" class="facturation-btn success submit-btn">';
print '<i class="fa fa-file-invoice"></i> '.$langs->trans("CreerFacture");
print '</button>';
print '</div>';

print '</form>';
print '</div>'; // .facturation-container

// ============================================================================
// 🔹 STYLES CSS DÉDIÉS À LA FACTURATION
// ============================================================================
?>


<script>
// ============================================================================
// 🔹 FONCTIONS JAVASCRIPT
// ============================================================================

// Options des services (PHP -> JS)
const serviceOptionsHTML = `<?php
$options = '';
foreach ($services as $s) {
    if (strcasecmp($s->label, 'Conjelation') !== 0) {
        $options .= '<option value="'.$s->rowid.'" data-price="'.$s->price.'">'.dol_escape_htmltag($s->label).'</option>';
    }
}
echo $options;
?>`;

const deviseSymbole = '<?php echo $symbole_devise; ?>';
const deviseCode = '<?php echo $code_devise_entrepot; ?>';

/**
 * Recalcule le total d'une ligne
 */
function recalcRow(row, prefix) {
    let qte = parseFloat(row.querySelector(`input[name="${prefix}qte[]"]`)?.value) || 0;
    let pu  = parseFloat(row.querySelector(`input[name="${prefix}pu[]"]`)?.value) || 0;
    let total = qte * pu;
    row.querySelector(`input[name="${prefix}total[]"]`).value = total.toFixed(2);
    recalcGrandTotal();
}

/**
 * Recalcule le total général
 */
function recalcGrandTotal() {
    let total = 0;
    document.querySelectorAll('input[name="service_total[]"]').forEach(inp => {
        total += parseFloat(inp.value) || 0;
    });
    document.getElementById('grand_total').value = total.toFixed(2) + ' ' + deviseSymbole;
}

/**
 * Supprime une ligne
 */
function removeRow(btn) {
    const row = btn.closest('tr');
    if (document.querySelectorAll('#table_services tbody tr').length > 1) {
        row.remove();
        recalcGrandTotal();
    } else {
        alert("<?php echo $langs->trans('MinimumUneLigneRequired'); ?>");
    }
}

/**
 * Met à jour tous les écouteurs d'événements
 */
function updateAllListeners() {
    document.querySelectorAll('.service_select').forEach(sel => {
        sel.onchange = e => {
            const price = parseFloat(e.target.selectedOptions[0].dataset.price) || 0;
            const row = e.target.closest('tr');
            row.querySelector('input[name="service_pu[]"]').value = price.toFixed(2);
            recalcRow(row, 'service_');
        };
    });

    document.querySelectorAll('input[name="service_qte[]"], input[name="service_pu[]"]').forEach(inp => {
        inp.oninput = () => {
            if (parseFloat(inp.value) < 0 || isNaN(parseFloat(inp.value))) inp.value = 0;
            const row = inp.closest('tr');
            recalcRow(row, 'service_');
        };
    });
}

/**
 * Ajoute une nouvelle ligne de service
 */
function addServiceRow() {
    const table = document.getElementById('table_services').getElementsByTagName('tbody')[0];
    const row = table.insertRow(-1);

    row.innerHTML = `
        <td>
            <select name="service_id[]" class="service_select facturation-input" style="width:100%;">
                <option value=""><?php echo $langs->trans('SelectionnerService'); ?></option>
                ${serviceOptionsHTML}
            </select>
        </td>
        <td class="center"><input type="number" step="0.01" name="service_qte[]" value="0" class="facturation-input qte-input" min="0"></td>
        <td class="center"><input type="number" step="0.01" name="service_pu[]" value="0" class="facturation-input pu-input" min="0"></td>
        <td class="center"><input type="text" name="service_total[]" readonly class="facturation-input total-input"></td>
        <td class="center"><button type="button" class="facturation-btn danger" onclick="removeRow(this)"><i class="fa fa-trash"></i></button></td>
    `;
    
    updateAllListeners();
}

/**
 * Validation avant soumission
 */
document.querySelector('form').addEventListener('submit', function(e) {
    const grandTotal = parseFloat(document.getElementById('grand_total').value) || 0;
    if (grandTotal <= 0) {
        alert("<?php echo $langs->trans('TotalDoitEtrePositif'); ?>");
        e.preventDefault();
        return false;
    }
    
    const confirmed = confirm("<?php echo $langs->trans('ConfirmerCreationFacture'); ?>");
    if (!confirmed) {
        e.preventDefault();
        return false;
    }
});

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    updateAllListeners();
    recalcGrandTotal();
    
    // Initialiser les calculs pour la ligne existante
    const firstRow = document.querySelector('#table_services tbody tr');
    if (firstRow) {
        recalcRow(firstRow, 'service_');
    }
});
</script>

<?php
llxFooter();
$db->close();
?>