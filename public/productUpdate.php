<?php
//
// Description
// ===========
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_wineproduction_productUpdate(&$ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'product_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Product'),
        'name'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Name'),
        'permalink'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Permalink'),
        'ptype'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Type'),
        'flags'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Options'),
        'status'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Status'),
        'start_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'datetimetoutc', 'name'=>'Start Date'),
        'end_date'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'datetimetoutc', 'name'=>'End Date'),
        'supplier_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Supplier'),
        'supplier_item_number'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Supplier Item Number'),
        'package_qty'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Package Quantity'),
        'wine_type'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Wine Type'),
        'kit_length'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Kit Length'),
        'list_price'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'List Price'),
        'list_discount_percent'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'List Discount'),
        'cost'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Cost'),
        'kit_price_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Kit Price'),
        'processing_price_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Processing Price'),
        'unit_amount'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Unit Amount'),
        'unit_discount_amount'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Discount Amount'),
        'unit_discount_percentage'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Discount Percent'),
        'taxtype_id'=>array('required'=>'no', 'blank'=>'no', 'name'=>'Taxes'),
        'inventory_current_num'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Inventory'),
        'primary_image_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Primary Image'),
        'synopsis'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Synopsis'),
        'description'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Synopsis'),
        'tags10'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'list', 'delimiter'=>'::', 'name'=>'Categories'),
        'tags11'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'list', 'delimiter'=>'::', 'name'=>'Sub Categories'),
        'tags12'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'list', 'delimiter'=>'::', 'name'=>'Varietals'),
        'tags13'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'list', 'delimiter'=>'::', 'name'=>'Oak'),
        'tags14'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'list', 'delimiter'=>'::', 'name'=>'Body'),
        'tags15'=>array('required'=>'no', 'blank'=>'yes', 'type'=>'list', 'delimiter'=>'::', 'name'=>'Sweetness'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];
    if( isset($args['list_price']) ) {
        $args['list_price'] = preg_replace("/[^0-9\.]/", "", $args['list_price']);
    }
    if( isset($args['list_discount_percent']) ) {
        $args['list_discount_percent'] = preg_replace("/[^0-9\.]/", "", $args['list_discount_percent']);
    }
    if( isset($args['cost']) ) {
        $args['cost'] = preg_replace("/[^0-9\.]/", "", $args['cost']);
    }
    if( isset($args['unit_amount']) ) {
        $args['unit_amount'] = preg_replace("/[^0-9\.]/", "", $args['unit_amount']);
    }
    if( isset($args['unit_discount_amount']) ) {
        $args['unit_discount_amount'] = preg_replace("/[^0-9\.]/", "", $args['unit_discount_amount']);
    }
    if( isset($args['unit_discount_percentage']) ) {
        $args['unit_discount_percentage'] = preg_replace("/[^0-9\.]/", "", $args['unit_discount_percentage']);
    }

    //
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'wineproduction', 'private', 'checkAccess');
    $rc = ciniki_wineproduction_checkAccess($ciniki, $args['tnid'], 'ciniki.wineproduction.productUpdate');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    if( isset($args['name']) ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'makePermalink');
        $args['permalink'] = ciniki_core_makePermalink($ciniki, $args['name']);
        //
        // Make sure the permalink is unique
        //
        $strsql = "SELECT id, name, permalink "
            . "FROM ciniki_wineproduction_products "
            . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND permalink = '" . ciniki_core_dbQuote($ciniki, $args['permalink']) . "' "
            . "AND id <> '" . ciniki_core_dbQuote($ciniki, $args['product_id']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.wineproduction', 'item');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( $rc['num_rows'] > 0 ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.wineproduction.125', 'msg'=>'You already have an product with this name, please choose another.'));
        }
    }

    //
    // Start transaction
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionStart');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionRollback');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbTransactionCommit');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbAddModuleHistory');
    $rc = ciniki_core_dbTransactionStart($ciniki, 'ciniki.wineproduction');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the Product in the database
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
    $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.wineproduction.product', $args['product_id'], $args, 0x04);
    if( $rc['stat'] != 'ok' ) {
        ciniki_core_dbTransactionRollback($ciniki, 'ciniki.wineproduction');
        return $rc;
    }

    for($i = 10; $i <= 15; $i++) {
        if( isset($args['tags' . $i]) ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'tagsUpdate');
            $rc = ciniki_core_tagsUpdate($ciniki, 'ciniki.wineproduction', 'producttag', $args['tnid'],
                'ciniki_wineproduction_product_tags', 'ciniki_wineproduction_history',
                'product_id', $args['product_id'], $i, $args['tags' . $i]);
            if( $rc['stat'] != 'ok' ) {
                ciniki_core_dbTransactionRollback($ciniki, 'ciniki.products');
                return $rc;
            }
        }
    }
    
    //
    // Update the pricing
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'wineproduction', 'private', 'productPricingUpdate');
    $rc = ciniki_wineproduction_productPricingUpdate($ciniki, $args['tnid'], array('product_id'=>$args['product_id']));
    if( $rc['stat'] != 'ok' ) {
        return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.wineproduction.249', 'msg'=>'Unable to update pricing', 'err'=>$rc['err']));
    }

    //
    // Commit the transaction
    //
    $rc = ciniki_core_dbTransactionCommit($ciniki, 'ciniki.wineproduction');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Update the last_change date in the tenant modules
    // Ignore the result, as we don't want to stop user updates if this fails.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'updateModuleChangeDate');
    ciniki_tenants_updateModuleChangeDate($ciniki, $args['tnid'], 'ciniki', 'wineproduction');

    //
    // Update the web index if enabled
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'hookExec');
    ciniki_core_hookExec($ciniki, $args['tnid'], 'ciniki', 'web', 'indexObject', array('object'=>'ciniki.wineproduction.product', 'object_id'=>$args['product_id']));

    return array('stat'=>'ok');
}
?>
