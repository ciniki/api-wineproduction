<?php
//
// Description
// ===========
// This method will return all the information about an supplier.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the supplier is attached to.
// supplier_id:          The ID of the supplier to get the details for.
//
// Returns
// -------
//
function ciniki_wineproduction_supplierGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'supplier_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Supplier'),
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
    $rc = ciniki_wineproduction_checkAccess($ciniki, $args['tnid'], 'ciniki.wineproduction.supplierGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Return default for new Supplier
    //
    if( $args['supplier_id'] == 0 ) {
        $supplier = array('id'=>0,
            'name'=>'',
            'supplier_tnid'=>'',
            'po_name_address'=>'',
            'po_email'=>'',
        );
    }

    //
    // Get the details for an existing Supplier
    //
    else {
        $strsql = "SELECT ciniki_wineproduction_suppliers.id, "
            . "ciniki_wineproduction_suppliers.name, "
            . "ciniki_wineproduction_suppliers.supplier_tnid, "
            . "ciniki_wineproduction_suppliers.po_name_address, "
            . "ciniki_wineproduction_suppliers.po_email "
            . "FROM ciniki_wineproduction_suppliers "
            . "WHERE ciniki_wineproduction_suppliers.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_wineproduction_suppliers.id = '" . ciniki_core_dbQuote($ciniki, $args['supplier_id']) . "' "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.wineproduction', array(
            array('container'=>'suppliers', 'fname'=>'id', 
                'fields'=>array('name', 'supplier_tnid', 'po_name_address', 'po_email'),
                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.wineproduction.131', 'msg'=>'Supplier not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['suppliers'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.wineproduction.132', 'msg'=>'Unable to find Supplier'));
        }
        $supplier = $rc['suppliers'][0];
    }

    return array('stat'=>'ok', 'supplier'=>$supplier);
}
?>
