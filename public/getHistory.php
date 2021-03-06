<?php
//
// Description
// -----------
// This function will get the history of a field from the ciniki_wineproduction_history table.
// This allows the user to view what has happened to a data element, and if they
// choose, revert to a previous version.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant to get the details for.
// wineproduction_id:   The ID of the wineproduction order to get the history for.
// field:               The field to get the history for.
//
// Returns
// -------
//  <history>
//      <action date="2011/02/03 00:03:00" value="Value field set to" user_id="1" />
//      ...
//  </history>
//  <users>
//      <user id="1" name="users.display_name" />
//      ...
//  </users>
//
function ciniki_wineproduction_getHistory($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'), 
        'wineproduction_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Order'), 
        'field'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Field'), 
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];
    
    //
    // Check access to tnid as owner, or sys admin
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'wineproduction', 'private', 'checkAccess');
    $rc = ciniki_wineproduction_checkAccess($ciniki, $args['tnid'], 'ciniki.wineproduction.getHistory');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    if( $args['field'] == 'customer_id' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbGetModuleHistoryFkId');
        return ciniki_core_dbGetModuleHistoryFkId($ciniki, 'ciniki.wineproduction', 'ciniki_wineproduction_history', $args['tnid'], 'ciniki_wineproductions', $args['wineproduction_id'], $args['field'], 'ciniki_customers', 'id', "ciniki_customers.display_name");
    } elseif( $args['field'] == 'product_id' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbGetModuleHistoryFkId');
        return ciniki_core_dbGetModuleHistoryFkId($ciniki, 'ciniki.wineproduction', 'ciniki_wineproduction_history', $args['tnid'], 'ciniki_wineproductions', $args['wineproduction_id'], $args['field'], 'ciniki_wineproduction_products', 'id', "ciniki_wineproduction_products.name");
    } elseif( $args['field'] == 'order_date' 
        || $args['field'] == 'start_date' 
        || $args['field'] == 'racking_date' 
        || $args['field'] == 'rack_date' 
        || $args['field'] == 'filtering_date' 
        || $args['field'] == 'filter_date' 
        || $args['field'] == 'bottle_date' 
        ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbGetModuleHistoryReformat');
        return ciniki_core_dbGetModuleHistoryReformat($ciniki, 'ciniki.wineproduction', 'ciniki_wineproduction_history', $args['tnid'], 'ciniki_wineproductions', $args['wineproduction_id'], $args['field'], 'date');
    } elseif( $args['field'] == 'bottling_date' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbGetModuleHistoryReformat');
        return ciniki_core_dbGetModuleHistoryReformat($ciniki, 'ciniki.wineproduction', 'ciniki_wineproduction_history', $args['tnid'], 'ciniki_wineproductions', $args['wineproduction_id'], $args['field'], 'utcdatetime');
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbGetModuleHistory');
    return ciniki_core_dbGetModuleHistory($ciniki, 'ciniki.wineproduction', 'ciniki_wineproduction_history', $args['tnid'], 'ciniki_wineproductions', $args['wineproduction_id'], $args['field']);
}
?>
