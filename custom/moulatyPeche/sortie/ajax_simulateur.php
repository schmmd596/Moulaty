<?php
/**
 * AJAX pour le simulateur de sortie
 * Version corrigée pour éviter l'erreur 403
 */

// Désactiver la vérification CSRF et menu pour AJAX
define('NOCSRFCHECK', 1);      // Désactive la vérification CSRF
define('NOTOKENRENEWAL', 1);   // Désactive le renouvellement de token
define('NOREQUIREMENU', 1);    // Pas besoin de menu
define('NOREQUIREHTML', 1);    // Pas besoin d'HTML
define('NOREQUIREAJAX', 1);    // Pas besoin d'AJAX

// Inclure main.inc.php
$res = 0;
if (! $res && file_exists("../../../main.inc.php")) $res = @include "../../../main.inc.php";
if (! $res && file_exists("../../../../main.inc.php")) $res = @include "../../../../main.inc.php";
if (! $res) die("Include of main fails");

global $db, $user, $langs, $conf;

// Désactiver les erreurs pour la réponse JSON
error_reporting(0);

// Vérifier que l'utilisateur est connecté et a les droits
if (!$user->id) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Non authentifié']);
    exit;
}

// Vérifier les droits stock
if (!$user->rights->stock->lire) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Droits insuffisants']);
    exit;
}

// Récupérer les paramètres
$action = GETPOST('action', 'alpha');
$fk_entrepot = GETPOST('fk_entrepot', 'int');
$search = GETPOST('search', 'alpha');
$fk_lot = GETPOST('fk_lot', 'int');

// Headers pour JSON
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

$response = ['success' => false, 'message' => '', 'html' => '', 'lots' => []];

try {
    switch($action) {
        case 'load_cartons':
            if ($fk_entrepot <= 0) {
                $response['message'] = 'Entrepôt non sélectionné';
                break;
            }
            
            // Vérifier l'accès à l'entrepôt
            $sql_check = "SELECT COUNT(*) as nb FROM ".MAIN_DB_PREFIX."user_entrepot 
                         WHERE fk_user = ".((int)$user->id)." 
                         AND fk_entrepot = ".((int)$fk_entrepot);
            $res_check = $db->query($sql_check);
            
            if ($res_check) {
                $obj_check = $db->fetch_object($res_check);
                if ($obj_check->nb == 0 && !$user->rights->stock->creer) {
                    $response['message'] = 'Accès refusé à cet entrepôt';
                    break;
                }
            }
            
            // Requête pour les cartons disponibles
            $sql = "SELECT 
                        c.rowid as carton_id,
                        c.poids,
                        c.prix_moyen,
                        c.frais,
                        c.ref_carton,
                        p.label as product_label,
                        p.ref as product_ref,
                        l.ref as lot_ref,
                        l.rowid as lot_id
                    FROM ".MAIN_DB_PREFIX."pech_carton c
                    INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
                    INNER JOIN ".MAIN_DB_PREFIX."pech_lot l ON l.rowid = ld.fk_lot
                    INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ld.fk_product
                    WHERE c.statut = 0 
                    AND l.fk_entrepot = ".((int)$fk_entrepot)."
                    ORDER BY l.ref, p.label";
            
            $resql = $db->query($sql);
            
            if (!$resql) {
                $response['message'] = 'Erreur SQL: ' . $db->lasterror();
                break;
            }
            
            $html = '';
            $lots = [];
            $cartons_count = 0;
            
            while ($obj = $db->fetch_object($resql)) {
                $cartons_count++;
                $valeur = $obj->prix_moyen + $obj->frais;
                
                // Ajouter lot si pas déjà dans la liste
                if (!isset($lots[$obj->lot_id])) {
                    $lots[$obj->lot_id] = [
                        'id' => $obj->lot_id,
                        'ref' => $obj->lot_ref
                    ];
                }
                
                // Échapper pour JavaScript
                $product_label_js = addslashes($obj->product_label);
                $lot_ref_js = addslashes($obj->lot_ref);
                
                $html .= '<div class="carton-item">';
                $html .= '<strong>' . $obj->product_label . '</strong><br>';
                $html .= '<small>';
                $html .= 'Lot: ' . $obj->lot_ref . ' | ';
                $html .= 'Poids: ' . price($obj->poids, 0, '', 1, 3) . ' kg | ';
                $html .= 'Valeur: ' . price($valeur, 0, '', 1, 2) . ' ' . $conf->currency;
                if ($obj->ref_carton) {
                    $html .= ' | Ref: ' . $obj->ref_carton;
                }
                $html .= '</small><br>';
                $html .= '<button type="button" class="butAction small" onclick="selectCarton(' . $obj->carton_id . ', \'' . $product_label_js . '\', \'' . $lot_ref_js . '\', ' . $obj->poids . ', ' . $valeur . ')">';
                $html .= 'Ajouter';
                $html .= '</button>';
                $html .= '</div><hr>';
            }
            
            if ($cartons_count == 0) {
                $html = '<div class="center">Aucun carton disponible</div>';
            }
            
            $response['success'] = true;
            $response['html'] = $html;
            $response['lots'] = array_values($lots);
            break;
            
        case 'search_cartons':
            if ($fk_entrepot <= 0) {
                $response['message'] = 'Entrepôt non sélectionné';
                break;
            }
            
            $sql = "SELECT 
                        c.rowid as carton_id,
                        c.poids,
                        c.prix_moyen,
                        c.frais,
                        c.ref_carton,
                        p.label as product_label,
                        p.ref as product_ref,
                        l.ref as lot_ref
                    FROM ".MAIN_DB_PREFIX."pech_carton c
                    INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
                    INNER JOIN ".MAIN_DB_PREFIX."pech_lot l ON l.rowid = ld.fk_lot
                    INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ld.fk_product
                    WHERE c.statut = 0 
                    AND l.fk_entrepot = " . ((int)$fk_entrepot);
            
            if (!empty($search)) {
                $sql .= " AND (p.label LIKE '%" . $db->escape($search) . "%' 
                         OR p.ref LIKE '%" . $db->escape($search) . "%')";
            }
            
            if ($fk_lot > 0) {
                $sql .= " AND l.rowid = " . ((int)$fk_lot);
            }
            
            $sql .= " ORDER BY l.ref, p.label";
            
            $resql = $db->query($sql);
            
            if (!$resql) {
                $response['message'] = 'Erreur SQL: ' . $db->lasterror();
                break;
            }
            
            $html = '';
            $count = 0;
            
            while ($obj = $db->fetch_object($resql)) {
                $count++;
                $valeur = $obj->prix_moyen + $obj->frais;
                
                $product_label_js = addslashes($obj->product_label);
                $lot_ref_js = addslashes($obj->lot_ref);
                
                $html .= '<div class="carton-item">';
                $html .= '<strong>' . $obj->product_label . '</strong><br>';
                $html .= '<small>';
                $html .= 'Lot: ' . $obj->lot_ref . ' | ';
                $html .= 'Poids: ' . price($obj->poids, 0, '', 1, 3) . ' kg | ';
                $html .= 'Valeur: ' . price($valeur, 0, '', 1, 2) . ' ' . $conf->currency;
                if ($obj->ref_carton) {
                    $html .= ' | Ref: ' . $obj->ref_carton;
                }
                $html .= '</small><br>';
                $html .= '<button type="button" class="butAction small" onclick="selectCarton(' . $obj->carton_id . ', \'' . $product_label_js . '\', \'' . $lot_ref_js . '\', ' . $obj->poids . ', ' . $valeur . ')">';
                $html .= 'Ajouter';
                $html .= '</button>';
                $html .= '</div><hr>';
            }
            
            if ($count == 0) {
                $html = '<div class="center">Aucun résultat</div>';
            }
            
            $response['success'] = true;
            $response['html'] = $html;
            break;
            
        case 'filter_cartons_by_lot':
            if ($fk_entrepot <= 0) {
                $response['message'] = 'Entrepôt non sélectionné';
                break;
            }
            
            $sql = "SELECT 
                        c.rowid as carton_id,
                        c.poids,
                        c.prix_moyen,
                        c.frais,
                        c.ref_carton,
                        p.label as product_label,
                        p.ref as product_ref,
                        l.ref as lot_ref
                    FROM ".MAIN_DB_PREFIX."pech_carton c
                    INNER JOIN ".MAIN_DB_PREFIX."pech_lotdet ld ON ld.rowid = c.fk_lotdet
                    INNER JOIN ".MAIN_DB_PREFIX."pech_lot l ON l.rowid = ld.fk_lot
                    INNER JOIN ".MAIN_DB_PREFIX."product p ON p.rowid = ld.fk_product
                    WHERE c.statut = 0 
                    AND l.fk_entrepot = " . ((int)$fk_entrepot);
            
            if ($fk_lot > 0) {
                $sql .= " AND l.rowid = " . ((int)$fk_lot);
            }
            
            $sql .= " ORDER BY p.label";
            
            $resql = $db->query($sql);
            
            if (!$resql) {
                $response['message'] = 'Erreur SQL: ' . $db->lasterror();
                break;
            }
            
            $html = '';
            $count = 0;
            
            while ($obj = $db->fetch_object($resql)) {
                $count++;
                $valeur = $obj->prix_moyen + $obj->frais;
                
                $product_label_js = addslashes($obj->product_label);
                $lot_ref_js = addslashes($obj->lot_ref);
                
                $html .= '<div class="carton-item">';
                $html .= '<strong>' . $obj->product_label . '</strong><br>';
                $html .= '<small>';
                $html .= 'Lot: ' . $obj->lot_ref . ' | ';
                $html .= 'Poids: ' . price($obj->poids, 0, '', 1, 3) . ' kg | ';
                $html .= 'Valeur: ' . price($valeur, 0, '', 1, 2) . ' ' . $conf->currency;
                if ($obj->ref_carton) {
                    $html .= ' | Ref: ' . $obj->ref_carton;
                }
                $html .= '</small><br>';
                $html .= '<button type="button" class="butAction small" onclick="selectCarton(' . $obj->carton_id . ', \'' . $product_label_js . '\', \'' . $lot_ref_js . '\', ' . $obj->poids . ', ' . $valeur . ')">';
                $html .= 'Ajouter';
                $html .= '</button>';
                $html .= '</div><hr>';
            }
            
            if ($count == 0) {
                $html = '<div class="center">Aucun carton dans ce lot</div>';
            }
            
            $response['success'] = true;
            $response['html'] = $html;
            break;
            
        default:
            $response['message'] = 'Action non reconnue: ' . $action;
    }
} catch (Exception $e) {
    $response['message'] = 'Exception: ' . $e->getMessage();
}

echo json_encode($response);

if (is_object($db)) {
    $db->close();
}
exit;