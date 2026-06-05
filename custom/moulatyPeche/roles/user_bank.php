<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user, $conf;

$langs->loadLangs(["main", "womapeche@womapeche"]);
$form = new Form($db);

$action = GETPOST('action', 'alpha');

// ============================================================================
// 🔹 ACTIONS
// ============================================================================
if ($action == 'add') {
    $fk_user = (int) GETPOST('fk_user');
    $fk_bank = (int) GETPOST('fk_bank');

    if ($fk_user <= 0 || $fk_bank <= 0) {
        setEventMessages($langs->trans("VeuillezSelectionnerUtilisateurBanque"), null, 'errors');
    } else {
        try {
            // Vérifier si la relation existe déjà
            $sql_check = "SELECT COUNT(*) as cnt 
                          FROM ".MAIN_DB_PREFIX."user_bank 
                          WHERE fk_user = ".$fk_user." AND fk_bank = ".$fk_bank." AND entity = ".$conf->entity;
            $res_check = $db->query($sql_check);
            $obj_check = $db->fetch_object($res_check);

            if ($obj_check && $obj_check->cnt > 0) {
                setEventMessages($langs->trans("RelationExisteDeja"), null, 'warnings');
            } else {
                // Insertion
                $sql = "INSERT INTO ".MAIN_DB_PREFIX."user_bank(fk_user, fk_bank, entity)
                        VALUES ($fk_user, $fk_bank, ".$conf->entity.")";
                $res = $db->query($sql);

                if ($res) setEventMessages($langs->trans("RelationAjouteeSucces"), null, 'mesgs');
                else setEventMessages($langs->trans("ErreurAjout")." : ".$db->lasterror(), null, 'errors');
            }
        } catch (Exception $e) {
            setEventMessages($langs->trans("Exception")." : ".$e->getMessage(), null, 'errors');
        }
    }
}

if ($action == 'delete' && GETPOST('id', 'int')) {
    $id = (int) GETPOST('id');
    $sql = "DELETE FROM ".MAIN_DB_PREFIX."user_bank WHERE rowid = ".$id;
    $res = $db->query($sql);
    if ($res) setEventMessages($langs->trans("RelationSupprimee"), null, 'mesgs');
    else setEventMessages($langs->trans("ErreurSuppression"), null, 'errors');
}

// ============================================================================
// 🔹 EN-TÊTE DE PAGE
// ============================================================================
llxHeader('', $langs->trans('GestionUtilisateursBanque'));

// Importer le CSS personnalisé
print '<link rel="stylesheet" href="../bon_style.css">';

print '<div class="selection-container">';

// Header avec style CSS
print '<div class="selection-header">';
print '<h1><i class="fa fa-university"></i> '.$langs->trans("GestionUtilisateursBanque").'</h1>';
print '</div>';

// ============================================================================
// 🔹 FORMULAIRE D'AJOUT
// ============================================================================
print '<div class="filter-section">';
print '<div class="filter-title">';
print '<i class="fa fa-plus-circle"></i> '.$langs->trans("AjouterRelation");
print '</div>';

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="add">';

print '<table class="filter-table">';
print '<tr class="liste_titre"><th colspan="3">'.$langs->trans("NouvelleRelation").'</th></tr>';

print '<tr>';
print '<td width="40%"><label class="selection-label"><i class="fa fa-user"></i> '.$langs->trans("Utilisateur").'</label></td>';
print '<td width="40%"><label class="selection-label"><i class="fa fa-university"></i> '.$langs->trans("Banque").'</label></td>';
print '<td width="20%" class="center"><label class="selection-label"><i class="fa fa-cogs"></i> '.$langs->trans("Action").'</label></td>';
print '</tr>';

print '<tr>';
print '<td>'.$form->select_dolusers('', 'fk_user', 1, null, 0, '', '', 0, 0, 0, '', '', [], '', 'filter-input').'</td>';

// Liste des banques
$sql_bank = "SELECT rowid, ref as label FROM ".MAIN_DB_PREFIX."bank_account WHERE entity = ".$conf->entity;
$res_bank = $db->query($sql_bank);

$banks = array();
while ($obj = $db->fetch_object($res_bank)) {
    $banks[$obj->rowid] = $obj->label;
}

print '<td>';
print $form->selectarray('fk_bank', $banks, '', 1, 0, 0, '', 0, 0, 0, '', 'filter-input');
print '</td>';

print '<td class="center">';
print '<button type="submit" class="selection-button selection-button-success">';
print '<i class="fa fa-plus"></i> '.$langs->trans("Ajouter");
print '</button>';
print '</td>';
print '</tr>';

print '</table>';
print '</form>';
print '</div>'; // .filter-section

// ============================================================================
// 🔹 LISTE DES RELATIONS EXISTANTES
// ============================================================================
$sql = "SELECT ub.rowid, u.login, u.lastname, u.firstname, b.label as bank_label
        FROM ".MAIN_DB_PREFIX."user_bank ub
        LEFT JOIN ".MAIN_DB_PREFIX."user u ON ub.fk_user = u.rowid
        LEFT JOIN ".MAIN_DB_PREFIX."bank_account b ON ub.fk_bank = b.rowid
        WHERE ub.entity = ".$conf->entity."
        ORDER BY b.label, u.login";

$resql = $db->query($sql);

print '<div class="selection-section">';
print '<div class="selection-title">';
print '<i class="fa fa-list"></i> '.$langs->trans("RelationsExistantes");
print '</div>';

if ($resql && $db->num_rows($resql) > 0) {
    print '<div class="selection-table-container">';
    print '<table class="selection-table">';
    print '<thead>';
    print '<tr>';
    print '<th><i class="fa fa-user"></i> '.$langs->trans("Utilisateur").'</th>';
    print '<th><i class="fa fa-university"></i> '.$langs->trans("Banque").'</th>';
    print '<th class="center"><i class="fa fa-cogs"></i> '.$langs->trans("Actions").'</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';

    while ($obj = $db->fetch_object($resql)) {
        print '<tr>';
        print '<td>';
        print '<div class="user-info">';
        print '<strong>'.dol_escape_htmltag($obj->firstname.' '.$obj->lastname).'</strong>';
        print '<br><small class="opacitymedium">('.dol_escape_htmltag($obj->login).')</small>';
        print '</div>';
        print '</td>';
        print '<td>';
        print '<div class="bank-info">';
        print '<strong>'.dol_escape_htmltag($obj->bank_label).'</strong>';
        print '</div>';
        print '</td>';
        print '<td class="center">';
        print '<a href="'.$_SERVER["PHP_SELF"].'?action=delete&id='.$obj->rowid.'&token='.newToken().'" ';
        print 'class="selection-button selection-button-danger" ';
        print 'onclick="return confirm(\''.$langs->trans("ConfirmerSuppressionRelation").'\')">';
        print '<i class="fa fa-trash"></i> '.$langs->trans("Supprimer");
        print '</a>';
        print '</td>';
        print '</tr>';
    }

    print '</tbody>';
    print '</table>';
    print '</div>';
} else {
    print '<div class="selection-empty">';
    print '<i class="fa fa-info-circle"></i>';
    print '<p>'.$langs->trans("AucuneRelationTrouvee").'</p>';
    print '</div>';
}
print '</div>'; // .selection-section
print '</div>'; // .selection-container

// ============================================================================
// 🔹 STYLES SUPPLÉMENTAIRES
// ============================================================================
?>
<style>
.user-info, .bank-info {
    padding: 5px 0;
}

.selection-button-danger {
    background: #e74c3c;
    color: white;
    padding: 8px 16px;
    font-size: 13px;
    text-decoration: none;
    display: inline-block;
}

.selection-button-danger:hover {
    background: #c0392b;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
    text-decoration: none;
    color: white;
}

.filter-table .liste_titre th {
    background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
}

.selection-table th {
    background: linear-gradient(135deg, #27ae60 0%, #219a52 100%);
}

.bank-info strong {
    color: #2c3e50;
}

.user-info strong {
    color: #2c3e50;
}
</style>

<script>
// Validation côté client pour le formulaire
document.querySelector('form').addEventListener('submit', function(e) {
    const userSelect = document.querySelector('select[name="fk_user"]');
    const bankSelect = document.querySelector('select[name="fk_bank"]');
    
    if (!userSelect.value || !bankSelect.value) {
        alert("<?php echo $langs->trans('VeuillezRemplirTousChamps'); ?>");
        e.preventDefault();
        return false;
    }
});

// Animation pour les nouvelles lignes ajoutées
if (window.history.replaceState && '<?php echo $action; ?>' === 'add') {
    setTimeout(() => {
        const newRow = document.querySelector('.selection-table tbody tr:last-child');
        if (newRow) {
            newRow.style.animation = 'highlight 2s ease-in-out';
        }
    }, 100);
}

// Ajout d'une animation highlight
const style = document.createElement('style');
style.textContent = `
    @keyframes highlight {
        0% { background-color: #e8f4fd; }
        100% { background-color: transparent; }
    }
`;
document.head.appendChild(style);
</script>

<?php
llxFooter();
$db->close();
?>