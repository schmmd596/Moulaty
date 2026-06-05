<?php
require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';

// Récupérer l'id de la réception
$reception_id = GETPOST('id', 'int');
if(!$reception_id) {
    print '<div class="error">Réception non spécifiée.</div>';
    exit;
}

// Récupérer les informations de la réception
$sql = "SELECT r.*, s.nom as fournisseur
        FROM ".MAIN_DB_PREFIX."pech_reception r
        LEFT JOIN ".MAIN_DB_PREFIX."societe s ON r.fk_fournisseur = s.rowid
        WHERE r.rowid = ".$reception_id;
$resql = $db->query($sql);
if(!$resql || $db->num_rows($resql) == 0) {
    print '<div class="error">Réception introuvable.</div>';
    exit;
}

$reception = $db->fetch_object($resql);

llxHeader('', 'Modifier les poids nets');

// Styles améliorés avec design moderne
print '<style>
    .container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 20px;
        font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    }
    
    .header-card {
        background: linear-gradient(135deg, #667eea 0%, #3c67f3ff 100%);
        color: white;
        padding: 25px;
        border-radius: 12px;
        margin-bottom: 30px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    }
    
    .header-card h2 {
        margin: 0 0 15px 0;
        font-size: 28px;
        font-weight: 600;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-top: 20px;
    }
    
    .info-item {
        background: rgba(255,255,255,0.1);
        padding: 12px 15px;
        border-radius: 8px;
        backdrop-filter: blur(10px);
    }
    
    .info-item strong {
        display: block;
        font-size: 12px;
        opacity: 0.9;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .info-item span {
        font-size: 16px;
        font-weight: 500;
    }
    
    .table-container {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 20px rgba(0,0,0,0.08);
        overflow: hidden;
        margin-bottom: 25px;
    }
    
    table.reception-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
    }
    
    table.reception-table th {
        background: linear-gradient(135deg, #f5f7fa 0%, #629bf8ff 100%);
        padding: 16px 12px;
        text-align: left;
        font-weight: 600;
        color: #2d3748;
        border-bottom: 2px solid #e2e8f0;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.5px;
    }
    
    table.reception-table td {
        padding: 14px 12px;
        border-bottom: 1px solid #edf2f7;
        vertical-align: top;
    }
    
    table.reception-table tr:hover {
        background-color: #f8fafc;
        transition: background-color 0.2s ease;
    }
    
    table.reception-table tr:nth-child(even) {
        background-color: #fafbfc;
    }
    
    table.reception-table tr:nth-child(even):hover {
        background-color: #f1f5f9;
    }
    
    .poids-input {
        width: 120px;
        padding: 8px 12px;
        border: 2px solid #e2e8f0;
        border-radius: 6px;
        font-size: 14px;
        text-align: right;
        transition: all 0.3s ease;
        background: white;
    }
    
    .poids-input:focus {
        outline: none;
        border-color: #4299e1;
        box-shadow: 0 0 0 3px rgba(66, 153, 225, 0.1);
    }
    
    .poids-input:disabled {
        background-color: #f7fafc;
        color: #a0aec0;
        cursor: not-allowed;
    }
    
    .action-buttons {
        display: flex;
        gap: 12px;
        margin-top: 25px;
        padding: 20px;
        background: #f8fafc;
        border-radius: 8px;
        border: 1px solid #e2e8f0;
    }
    
    .btn {
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    
    .btn-primary {
        background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
        color: white;
    }
    
    .btn-primary:hover {
        background: linear-gradient(135deg, #3182ce 0%, #2c5aa0 100%);
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(66, 153, 225, 0.3);
    }
    
    .btn-secondary {
        background: #718096;
        color: white;
    }
    
    .btn-secondary:hover {
        background: #4a5568;
        transform: translateY(-1px);
    }
    
    .status-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .status-locked {
        background: #fed7d7;
        color: #c53030;
    }
    
    .status-readonly {
        background: #fef5e7;
        color: #dd6b20;
    }
    
    .status-editable {
        background: #c6f6d5;
        color: #276749;
    }
    
    .info-text {
        font-size: 11px;
        color: #718096;
        font-style: italic;
        margin-top: 4px;
        display: block;
    }
    
    .error {
        background: #fed7d7;
        color: #c53030;
        padding: 16px;
        border-radius: 8px;
        border-left: 4px solid #c53030;
        margin: 20px 0;
    }
    
    .success {
        background: #c6f6d5;
        color: #276749;
        padding: 16px;
        border-radius: 8px;
        border-left: 4px solid #38a169;
        margin: 20px 0;
    }
    
    .product-info {
        font-weight: 500;
        color: #2d3748;
    }
    
    .product-ref {
        color: #718096;
        font-size: 12px;
    }
    
    .mode-badge {
        display: inline-block;
        padding: 4px 8px;
        background: #e2e8f0;
        color: #4a5568;
        border-radius: 4px;
        font-size: 11px;
        font-weight: 500;
    }
</style>';

print '<div class="container">';

// En-tête avec informations de la réception
print '<div class="header-card">';
print '<h2>📦 Modifier les poids nets</h2>';
print '<div class="info-grid">';
print '<div class="info-item">';
print '<strong>Référence</strong>';
print '<span>'.$reception->ref.'</span>';
print '</div>';
print '<div class="info-item">';
print '<strong>Fournisseur</strong>';
print '<span>'.($reception->fournisseur ?: 'Non spécifié').'</span>';
print '</div>';
print '<div class="info-item">';
print '<strong>Date de création</strong>';
print '<span>'.dol_print_date($reception->date_creation, 'dayhour').'</span>';
print '</div>';
print '<div class="info-item">';
print '<strong>Poids total</strong>';
print '<span>'.($reception->poids ? price($reception->poids).' kg' : 'Non spécifié').'</span>';
print '</div>';
print '</div>';
print '</div>';

// Récupérer les lignes de réception avec informations produit
$sql_lines = "SELECT d.*, p.ref as product_ref, p.label as product_label
              FROM ".MAIN_DB_PREFIX."pech_receptiondet d
              LEFT JOIN ".MAIN_DB_PREFIX."product p ON d.fk_product = p.rowid
              WHERE d.fk_reception = ".$reception_id;
$res_lines = $db->query($sql_lines);

if($res_lines && $db->num_rows($res_lines) > 0) {
    print '<div class="table-container">';
    print '<form method="POST" action="poid_net_edit_process.php?id='.$reception_id.'">';
    print '<input type="hidden" name="token" value="'.newToken().'">';
    
    print '<table class="reception-table">';
    print '<thead>';
    print '<tr>';
    print '<th width="30%">Produit</th>';
    print '<th width="15%">Mode de réception</th>';
    print '<th width="15%">Poids Net Actuel</th>';
    print '<th width="25%">Nouveau Poids Net</th>';
    print '<th width="15%">Statut</th>';
    print '</tr>';
    print '</thead>';
    print '<tbody>';
    
    while($line = $db->fetch_object($res_lines)) {
        print '<tr>';
        
        // Colonne Produit
        print '<td>';
        print '<div class="product-info">'.$line->product_label.'</div>';
        print '<div class="product-ref">Ref: '.$line->product_ref.'</div>';
        print '</td>';
        
        // Colonne Mode de réception
        $mode_text = '';
        switch($line->reception_mode) {
            case 1: $mode_text = '🚗 Voiture '.$line->nb_voiture; break;
            case 2: $mode_text = '⚖️ Poids Brut '.$line->poids_brut; break;
            case 3: $mode_text = '🎯 Poids Net'; break;
            default: $mode_text = '❓ Inconnu';
        }
        print '<td><span class="mode-badge">'.$mode_text.'</span></td>';
        
        // Colonne Poids Net Actuel
        print '<td><strong>'.price($line->poids_net).' kg</strong></td>';
        
        // Colonne Modification
        print '<td>';
        
        // 🔒 Si ligne déjà plater, on bloque la modification
        if ($line->poids_plater > 0) {
            print '<input type="number" step="0.01" class="poids-input" value="'.$line->poids_net.'" disabled>';
            print '<span class="info-text">❌ Déjà utilisé en mise en plat</span>';
            $status_class = 'status-locked';
            $status_text = 'Bloqué';
        }
        // Si mode = Poids Net (3), pas de modif possible non plus
        elseif ($line->reception_mode == 3) {
            print '<span class="info-text">Mode "Poids Net" - modification non autorisée</span>';
            $status_class = 'status-readonly';
            $status_text = 'Lecture seule';
        }
        // Sinon modification autorisée
        else {
            print '<input type="number" step="0.01" class="poids-input" min="0" name="poids_net['.$line->rowid.']" value="'.$line->poids_net.'" required>';
            print '<span class="info-text">✅ Saisie autorisée</span>';
            $status_class = 'status-editable';
            $status_text = 'Modifiable';
        }
        
        print '</td>';
        
        // Colonne Statut
        print '<td><span class="status-badge '.$status_class.'">'.$status_text.'</span></td>';
        
        print '</tr>';
    }
    
    print '</tbody>';
    print '</table>';
    
    // Boutons d'action
    print '<div class="action-buttons">';
    print '<button type="submit" class="btn btn-primary" onclick="return confirm(\'Êtes-vous sûr de vouloir mettre à jour les poids nets ?\');">';
    print '💾 Mettre à jour les poids nets';
    print '</button>';
    print '<a class="btn btn-secondary" href="./detail_rec.php?id='.$reception_id.'">';
    print '↩️ Retour au détail';
    print '</a>';
    print '</div>';
    
    print '</form>';
    print '</div>';
} else {
    print '<div class="error">Aucune ligne trouvée pour cette réception.</div>';
}

print '</div>'; // Fermeture du container

llxFooter();
$db->close();
?>