<?php
//
// Description
// -----------
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_wineproduction_sync_objects($ciniki, &$sync, $tnid, $args) {
    ciniki_core_loadMethod($ciniki, 'ciniki', 'wineproduction', 'private', 'objects');
    return ciniki_wineproduction_objects($ciniki);
}
?>
