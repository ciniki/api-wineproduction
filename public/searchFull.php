<?php
//
// Description
// -----------
// This method will search all wineproduction orders that are in 
// any status, bottled or not.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:     The ID of the tenant to search the orders of.
// search_str:      The string to search the orders for.
// limit:           (optional) The limit of results to return.
// finished:        (optional) If specified 'no' only returned unbottled orders.
// 
// Returns
// -------
//
function ciniki_wineproduction_searchFull($ciniki) {
    //  
    // Find all the required and optional arguments
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'search_str'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Search'), 
        'limit'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Limit'), 
        'finished'=>array('required'=>'no', 'default'=>'yes', 'blank'=>'yes', 'name'=>'Finished Flag'), 
        )); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   
    $args = $rc['args'];
    
    //  
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //  
    ciniki_core_loadMethod($ciniki, 'ciniki', 'wineproduction', 'private', 'checkAccess');
    $rc = ciniki_wineproduction_checkAccess($ciniki, $args['tnid'], 'ciniki.wineproduction.searchFull'); 
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }   

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki);
    
    $strsql = "SELECT ciniki_wineproductions.id, ciniki_customers.display_name AS customer_name, invoice_number, "
        . "ciniki_wineproduction_products.name AS wine_name, "
        . "ciniki_wineproduction_products.wine_type, "
        . "ciniki_wineproduction_products.kit_length, "
        . "ciniki_wineproductions.status, "
        . "rack_colour, "
        . "filter_colour, "
        . "DATE_FORMAT(ciniki_wineproductions.order_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') as order_date, "
        . "DATE_FORMAT(ciniki_wineproductions.start_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') as start_date, "
        . "DATE_FORMAT(ciniki_wineproductions.racking_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') as racking_date, "
        . "DATE_FORMAT(ciniki_wineproductions.rack_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') as rack_date, "
        . "sg_reading, "
        . "DATE_FORMAT(ciniki_wineproductions.filtering_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') as filtering_date, "
        . "DATE_FORMAT(ciniki_wineproductions.filter_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') as filter_date, "
        . "DATE_FORMAT(ciniki_wineproductions.bottling_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') as bottling_date, "
        . "DATE_FORMAT(ciniki_wineproductions.bottle_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') as bottle_date "
        . "FROM ciniki_wineproductions "
        . "LEFT JOIN ciniki_customers ON ("
            . "ciniki_wineproductions.customer_id = ciniki_customers.id "
            . "AND ciniki_customers.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "LEFT JOIN ciniki_wineproduction_products ON ("
            . "ciniki_wineproductions.product_id = ciniki_wineproduction_products.id "
            . "AND ciniki_wineproduction_products.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE ciniki_wineproductions.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' ";
    if( is_numeric($args['search_str']) ) {
        $strsql .= "AND invoice_number LIKE '%" . ciniki_core_dbQuote($ciniki, $args['search_str']) . "%' "
            . "";
    } else {
        $strsql .= "AND ( ciniki_customers.first LIKE '%" . ciniki_core_dbQuote($ciniki, $args['search_str']) . "%' "
            . "OR ciniki_customers.last LIKE '%" . ciniki_core_dbQuote($ciniki, $args['search_str']) . "%' "
            . "OR ciniki_customers.company LIKE '%" . ciniki_core_dbQuote($ciniki, $args['search_str']) . "%' "
            . "OR ciniki_wineproduction_products.name LIKE '%" . ciniki_core_dbQuote($ciniki, $args['search_str']) . "%' ) "
            . "";
    }

    if( isset($args['finished']) && $args['finished'] == 'no' ) {
        $strsql . "AND ciniki_wineproductions.status < 60";
    }

    $strsql .= "ORDER BY ciniki_wineproductions.invoice_number DESC ";
    if( isset($args['limit']) && is_numeric($args['limit']) && $args['limit'] > 0 ) {
        $strsql .= "LIMIT " . ciniki_core_dbQuote($ciniki, $args['limit']) . " ";   // is_numeric verified
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbRspQuery');
    return ciniki_core_dbRspQuery($ciniki, $strsql, 'ciniki.wineproduction', 'orders', 'order', array('stat'=>'ok', 'orders'=>array()));
}
?>
