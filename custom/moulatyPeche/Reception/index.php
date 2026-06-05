<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user;

$langs->load("main");
$langs->load("bills");
$langs->load("abricot@abricot");

llxHeader('', $langs->trans('ReceptionDashboard'));

// =========================
// STYLE PERSONNALISÉ AMÉLIORÉ
// =========================
print '
<style>
    .dashboard-container {
        background: #fff;
        padding: 30px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        margin-bottom: 30px;
        border: 1px solid #e8e8e8;
    }
    .dashboard-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 2px solid #e5e5e5;
        padding-bottom: 15px;
        margin-bottom: 25px;
    }
    .dashboard-header h2 {
        font-size: 24px;
        color: #2c3e50;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .dashboard-header h2 i {
        color: #0055a4;
        font-size: 26px;
    }
    .filter-form {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 25px;
        border: 1px solid #e3e3e3;
    }
    .filter-form label {
        font-weight: 600;
        color: #2c3e50;
        margin-right: 10px;
    }
    .filter-form select {
        padding: 8px 15px;
        border-radius: 6px;
        border: 1px solid #ccc;
        background: white;
        font-size: 14px;
        margin-right: 10px;
        min-width: 250px;
    }
    .filter-form .button {
        padding: 8px 20px;
        border-radius: 6px;
        border: none;
        background: linear-gradient(135deg, #0055a4, #0078d7);
        color: #fff;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
    }
    .filter-form .button:hover {
        background: linear-gradient(135deg, #003f7d, #0055a4);
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0,85,164,0.3);
    }
    table.liste {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
        background: white;
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 6px rgba(0,0,0,0.05);
    }
    table.liste th {
        background: linear-gradient(135deg, #0055a4, #0078d7);
        color: white;
        text-align: center;
        padding: 12px 8px;
        font-weight: 600;
        font-size: 14px;
    }
    table.liste td {
        padding: 12px 8px;
        border-bottom: 1px solid #eee;
        text-align: center;
        color: #333;
        font-size: 14px;
    }
    table.liste tr:nth-child(even) {
        background-color: #f8f9fa;
    }
    table.liste tr:hover {
        background-color: #e3f2fd;
        transform: scale(1.01);
        transition: all 0.2s ease;
    }
    .summary-box {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-top: 25px;
    }
    .summary-card {
        background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        border: 1px solid #e3e3e3;
        border-radius: 12px;
        padding: 25px 20px;
        text-align: center;
        box-shadow: 0 3px 10px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }
    .summary-card::before {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #0055a4, #0078d7);
    }
    .summary-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    .summary-card h4 {
        font-size: 15px;
        color: #555;
        margin-bottom: 12px;
        font-weight: 600;
    }
    .summary-card p {
        font-size: 24px;
        font-weight: 700;
        color: #0055a4;
        margin: 0;
    }
    h3.section-title {
        margin-top: 40px;
        font-size: 20px;
        font-weight: 700;
        color: #2c3e50;
        border-left: 5px solid #0055a4;
        padding-left: 15px;
        margin-bottom: 20px;
        background: linear-gradient(90deg, #f8f9fa, transparent);
        padding: 12px 15px;
        border-radius: 0 8px 8px 0;
    }
    .no-data {
        text-align: center;
        padding: 40px;
        color: #6c757d;
        font-style: italic;
        background: #f8f9fa;
        border-radius: 8px;
        margin: 20px 0;
    }
    .stats-icon {
        font-size: 24px;
        margin-bottom: 10px;
        display: block;
    }
    @media (max-width: 768px) {
        .dashboard-container {
            padding: 15px;
        }
        .summary-box {
            grid-template-columns: 1fr;
        }
        .filter-form select {
            min-width: 200px;
        }
    }
</style>
';

// =========================
// TITRE PRINCIPAL
// =========================
print '<div class="dashboard-container">';
print '<div class="dashboard-header">';
print '<h2><i class="fa fa-chart-bar"></i> '.$langs->trans('ReceptionDashboard').'</h2>';
print '</div>';

// =========================
// FILTRE PAR ENTREPÔT
// =========================
$form = new Form($db);
$entrepot_id = GETPOST('entrepot_id', 'int');

$entrepots = [0 => $langs->trans('AllWarehouses')];
$sql_ent = "SELECT rowid, ref as label FROM ".MAIN_DB_PREFIX."entrepot ORDER BY label ASC";
$res_ent = $db->query($sql_ent);
if ($res_ent) {
    while ($obj = $db->fetch_object($res_ent)) {
        $entrepots[$obj->rowid] = $obj->label;
    }
}

print '<div class="filter-form">';
print '<form method="GET" action="'.$_SERVER["PHP_SELF"].'">';
print '<label>'.$langs->trans('Warehouse').' :</label> ';
print $form->selectarray('entrepot_id', $entrepots, $entrepot_id, 0, 0, 0, '', 0, 0, 0, '', '', 1);
print ' <input type="submit" class="button" value="'.$langs->trans('Filter').'">';
print '</form>';
print '</div>';

// =========================
// STATISTIQUES GLOBALES
// =========================
$sql = "SELECT COUNT(r.rowid) AS nb_receptions, 
               SUM(r.montant) AS montant_total, 
               SUM(r.poids) AS poids_total
        FROM ".MAIN_DB_PREFIX."pech_reception r
        LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = r.fk_entrepot
        WHERE r.etat = 1";
if ($entrepot_id > 0) $sql .= " AND r.fk_entrepot = ".$entrepot_id;
$res = $db->query($sql);

if ($res && $db->num_rows($res) > 0) {
    $obj = $db->fetch_object($res);
    print '<div class="summary-box">';
    print '<div class="summary-card">';
    print '<span class="stats-icon">📦</span>';
    print '<h4>'.$langs->trans('NumberOfReceptions').'</h4>';
    print '<p>'.$obj->nb_receptions.'</p>';
    print '</div>';
    
    print '<div class="summary-card">';
    print '<span class="stats-icon">💰</span>';
    print '<h4>'.$langs->trans('TotalAmount').'</h4>';
    print '<p>'.number_format($obj->montant_total, 2, ',', ' ').' '.$langs->trans('Currency').'</p>';
    print '</div>';
    
    print '<div class="summary-card">';
    print '<span class="stats-icon">⚖️</span>';
    print '<h4>'.$langs->trans('TotalWeight').'</h4>';
    print '<p>'.number_format($obj->poids_total, 2, ',', ' ').' kg</p>';
    print '</div>';
    print '</div>';
} else {
    print '<div class="no-data">'.$langs->trans('NoDataFound').'</div>';
}

// =========================
// DÉTAIL PAR ENTREPÔT
// =========================
$sql2 = "SELECT e.ref AS entrepot, COUNT(r.rowid) AS nb_receptions,
                SUM(r.montant) AS montant_total, SUM(r.poids) AS poids_total
         FROM ".MAIN_DB_PREFIX."pech_reception r
         LEFT JOIN ".MAIN_DB_PREFIX."entrepot e ON e.rowid = r.fk_entrepot
         WHERE r.etat = 1";
if ($entrepot_id > 0) $sql2 .= " AND r.fk_entrepot = ".$entrepot_id;
$sql2 .= " GROUP BY e.ref ORDER BY e.ref ASC";

$res2 = $db->query($sql2);

print '<h3 class="section-title">'.$langs->trans('DetailByWarehouse').'</h3>';
print '<table class="liste">';
print '<tr>';
print '<th>'.$langs->trans('Warehouse').'</th>';
print '<th>'.$langs->trans('NumberOfReceptions').'</th>';
print '<th>'.$langs->trans('TotalAmount').' ('.$langs->trans('Currency').')</th>';
print '<th>'.$langs->trans('TotalWeight').' (kg)</th>';
print '</tr>';

if ($res2 && $db->num_rows($res2) > 0) {
    while ($obj = $db->fetch_object($res2)) {
        print '<tr>';
        print '<td><strong>'.$obj->entrepot.'</strong></td>';
        print '<td>'.$obj->nb_receptions.'</td>';
        print '<td>'.number_format($obj->montant_total, 2, ',', ' ').'</td>';
        print '<td>'.number_format($obj->poids_total, 2, ',', ' ').'</td>';
        print '</tr>';
    }
} else {
    print '<tr><td colspan="4" style="text-align:center;padding:30px;color:#6c757d;">'.$langs->trans('NoDataAvailable').'</td></tr>';
}
print '</table>';

print '</div>'; // end dashboard-container

llxFooter();
$db->close();
?>