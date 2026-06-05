<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';

dol_include_once('/usinepeche/class/reception.class.php'); // À adapter au nom réel du module
dol_include_once('/usinepeche/lib/usinepeche_reception.lib.php');

$langs->loadLangs(['companies', 'usinepeche@usinepeche']);

$form = new Form($db);
$formC = new FormCompany($db);
$token = newToken();

$mode = GETPOST('mode', 'int'); // 1=Voiture, 2=Poids Brut, 3=Poids Net
$action = GETPOST('action', 'alpha');
$id = GETPOST('id', 'int');

// =======================================================
// ÉTAPE 1 : Sélection du mode si non défini
// =======================================================
if (!$mode) {
    llxHeader('', $langs->trans('ChoisirModeReception'));
    print load_fiche_titre($langs->trans('ChoisirModeReception'));

    echo '<form method="GET" action="card.php">';
    echo '<table class="border" width="50%">';
    echo '<tr><td class="fieldrequired">'.$langs->trans("ModeReception").'</td><td>';
    echo '<select name="mode" class="flat" required>';
    echo '<option value="">-- '.$langs->trans("SelectMode").' --</option>';
    echo '<option value="1">'.$langs->trans("Réception par voiture").'</option>';
    echo '<option value="2">'.$langs->trans("Réception par poids brut").'</option>';
    echo '<option value="3">'.$langs->trans("Réception par poids net").'</option>';
    echo '</select>';
    echo '</td></tr>';
    echo '</table><br>';
    echo '<input type="submit" class="button" value="'.$langs->trans("Suivant").'">';
    echo '</form>';

    llxFooter();
    $db->close();
    exit;
}

// =======================================================
// ÉTAPE 2 : Affichage du formulaire complet selon le mode
// =======================================================

// Récupération des produits
$sql = "SELECT rowid, ref, label FROM ".MAIN_DB_PREFIX."product WHERE tosell=1 ORDER BY ref ASC";
$resql = $db->query($sql);
$products = [];
if ($resql) while ($obj = $db->fetch_object($resql)) $products[$obj->rowid] = $obj->ref.' - '.$obj->label;
$productsJson = json_encode($products);

// Mode libellé
$modeLabel = [
    1 => 'Réception par voiture',
    2 => 'Réception par poids brut',
    3 => 'Réception par poids net'
][$mode] ?? 'Inconnu';

// Titre
llxHeader('', $langs->trans('Reception'));
print load_fiche_titre($langs->trans($id ? 'Modifier la réception' : 'Nouvelle réception').' - '.$modeLabel);

print '<form method="POST" action="create_rec.php">';
print '<input type="hidden" name="token" value="'.$token.'">';
print '<input type="hidden" name="action" value="'.($id ? 'update' : 'create').'">';
print '<input type="hidden" name="mode" value="'.$mode.'">';
if($id) print '<input type="hidden" name="id" value="'.$id.'">';

// ====== En-tête ======
print '<table class="border centpercent">';
print '<tr><td class="fieldrequired">'.$langs->trans("Fournisseur").'</td><td>';
print $formC->select_company('', 'fk_soc', 's.fournisseur = 1', 0, 1);
print '</td></tr>';

print '<tr><td>'.$langs->trans("Calibre").'</td><td><input type="text" name="calibre" style="width:200px;"></td></tr>';
print '<tr><td>'.$langs->trans("Commentaire").'</td><td><textarea name="comment" style="width:400px;height:60px;"></textarea></td></tr>';
print '</table><br>';

// ====== Lignes ======
// ====== Lignes ======
print '<table class="noborder" id="linesTable" width="100%">';
print '<tr class="liste_titre">';
print '<th>'.$langs->trans('Produit').'</th>';

if($mode == 1) {
    print '<th>'.$langs->trans('Nb Voitures').'</th>';
    print '<th>'.$langs->trans('Prix/Voiture').'</th>';
} elseif($mode == 2) {
    print '<th>'.$langs->trans('Poids Brut (kg)').'</th>';
    print '<th>'.$langs->trans('PU Brut').'</th>';
} elseif($mode == 3) {
    print '<th>'.$langs->trans('PU Poids Net').'</th>';
}

// ✅ Champs communs pour tous les modes
print '<th>'.$langs->trans('Poids Accepté (kg)').'</th>';
print '<th>'.$langs->trans('Poids Rejeté (kg)').'</th>';
print '<th>'.$langs->trans('PU Accepté').'</th>';
print '<th>'.$langs->trans('PU Rejeté').'</th>';

print '<th class="right">'.$langs->trans('Total Ligne').'</th>';
print '<th>&nbsp;</th></tr>';

// ====== Ligne vide par défaut ======
print '<tr class="productRow"><td>';
print $form->selectarray('fk_product[]', $products, '', 1, 0, '', 0, ['style'=>'width:100%']);
print '</td>';

if($mode == 1) {
    print '<td><input type="number" name="nb_voiture[]" min="0" step="1" style="width:80px"></td>';
    print '<td><input type="number" name="prix_voiture[]" min="0" step="0.01" style="width:80px"></td>';
} elseif($mode == 2) {
    print '<td><input type="number" name="poids_brut[]" min="0" step="0.01" style="width:80px"></td>';
    print '<td><input type="number" name="pu_brut[]" min="0" step="0.01" style="width:80px"></td>';
} elseif($mode == 3) {
    print '<td><input type="number" name="pu_poidsnet[]" min="0" step="0.01" style="width:80px"></td>';
}

// ✅ Toujours présents
print '<td><input type="number" name="poids_accepte[]" min="0" step="0.01" style="width:80px"></td>';
print '<td><input type="number" name="poids_rejete[]" min="0" step="0.01" style="width:80px" value="0"></td>';
print '<td><input type="number" name="pu_accepte[]" min="0" step="0.01" style="width:80px"></td>';
print '<td><input type="number" name="pu_rejete[]" min="0" step="0.01" style="width:80px" value="0"></td>';

print '<td class="right total_line">0.00</td>';
print '<td><button type="button" onclick="removeRow(this)" style="background:none;border:none;color:red;">🗑️</button></td>';
print '</tr>';

print '<tr><td colspan="12" class="right"><b>Total : <span id="totalReception">0.00</span> '.$conf->currency.'</b></td></tr>';
print '</table>';

// Ajouter ligne
print '<div style="margin-top:10px;"><button type="button" onclick="addProductRow()" class="button">➕ '.$langs->trans('Ajouter ligne').'</button></div>';

// Soumettre
print '<div style="text-align:center; margin-top:10px;">
        <input type="submit" class="button" value="'.($id ? $langs->trans('Modifier') : $langs->trans('Créer')).'">
      </div>';

print '</form>';
llxFooter();
$db->close();
?>

<script>
var products = <?php echo $productsJson; ?>;
var mode = <?php echo (int)$mode; ?>;

function addProductRow(){
    var table = document.getElementById('linesTable');
    var row = table.insertRow(table.rows.length - 1);
    row.className = 'productRow';
    var html = `<td><select name="fk_product[]" style="width:100%">`+
        Object.entries(products).map(([k,v])=>`<option value="${k}">${v}</option>`).join('')+
        `</select></td>`;

    if(mode === 1){
        html += `<td><input type='number' name='nb_voiture[]' min='0' step='1' style='width:80px'></td>
                 <td><input type='number' name='prix_voiture[]' min='0' step='0.01' style='width:80px'></td>`;
    } else if(mode === 2){
        html += `<td><input type='number' name='poids_brut[]' min='0' step='0.01' style='width:80px'></td>
                 <td><input type='number' name='pu_brut[]' min='0' step='0.01' style='width:80px'></td>`;
    } else if(mode === 3){
        html += `<td><input type='number' name='pu_poidsnet[]' min='0' step='0.01' style='width:80px'></td>`;
    }

    // ✅ Toujours affichés
    html += `<td><input type='number' name='poids_accepte[]' min='0' step='0.01' style='width:80px'></td>
             <td><input type='number' name='poids_rejete[]' min='0' step='0.01' style='width:80px' value='0'></td>
             <td><input type='number' name='pu_accepte[]' min='0' step='0.01' style='width:80px'></td>
             <td><input type='number' name='pu_rejete[]' min='0' step='0.01' style='width:80px' value='0'></td>
             <td class='right total_line'>0.00</td>
             <td><button type='button' onclick='removeRow(this)' style='background:none;border:none;color:red;'>🗑️</button></td>`;

    row.innerHTML = html;
}

function removeRow(btn){
    btn.closest('tr').remove();
}
</script>