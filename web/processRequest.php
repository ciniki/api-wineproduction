<?php
//
// Description
// -----------
// This function will generate the gallery page for the website
//
// Arguments
// ---------
// ciniki:
// settings:        The web settings structure, similar to ciniki variable but only web specific information.
//
// Returns
// -------
//
function ciniki_wineproduction_web_processRequest(&$ciniki, $settings, $tnid, $args) {

    if( !isset($ciniki['tenant']['modules']['ciniki.wineproduction']) ) {
        return array('stat'=>'404', 'err'=>array('code'=>'ciniki.wineproduction.126', 'msg'=>"I'm sorry, the page you requested does not exist."));
    }

    $page = array(
        'title'=>$args['page_title'],
        'breadcrumbs'=>$args['breadcrumbs'],
        'blocks'=>array(),
        'path'=>(isset($settings['page-wineproducts-path'])&&$settings['page-wineproducts-path']!=''?$settings['page-wineproducts-path']:'yes'),
        );

    if( $args['page_title'] == '' ) {
        $page['title'] = 'Products';
        $page['breadcrumbs'][] = array('name'=>$page['title'], 'url'=>$args['base_url']);
    }

    //
    // Check if a file was specified to be downloaded
    //
    $download_err = '';
    if( isset($args['uri_split']) ) {
        $num_uri = count($args['uri_split']);
    }
    if( isset($ciniki['tenant']['modules']['ciniki.wineproduction'])
        && isset($num_uri)
        && isset($args['uri_split'][$num_uri-3]) && $args['uri_split'][$num_uri-3] != ''
        && isset($args['uri_split'][$num_uri-2]) && $args['uri_split'][$num_uri-2] == 'download'
        && isset($args['uri_split'][$num_uri-1]) && $args['uri_split'][$num_uri-1] != '' ) {
        ciniki_core_loadMethod($ciniki, 'ciniki', 'wineproduction', 'web', 'fileDownload');
        $rc = ciniki_wineproduction_web_fileDownload($ciniki, $ciniki['request']['tnid'], 
            $ciniki['request']['uri_split'][$num_uri-3], $ciniki['request']['uri_split'][$num_uri-1]);
        if( $rc['stat'] == 'ok' ) {
            header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
            header("Last-Modified: " . gmdate("D,d M YH:i:s") . " GMT");
            header('Cache-Control: no-cache, must-revalidate');
            header('Pragma: no-cache');
            $file = $rc['file'];
            if( $file['extension'] == 'pdf' ) {
                header('Content-Type: application/pdf');
            }
//          header('Content-Disposition: attachment;filename="' . $file['filename'] . '"');
            header('Content-Length: ' . strlen($file['binary_content']));
            header('Cache-Control: max-age=0');

            print $file['binary_content'];
            exit;
        }
        
        //
        // If there was an error locating the files, display generic error
        //
        return array('stat'=>'404', 'err'=>array('code'=>'ciniki.wineproduction.127', 'msg'=>'The file you requested does not exist.'));
    }

    //
    // Check for image format
    //
    $thumbnail_format = 'square-cropped';
    $thumbnail_padding_color = '#ffffff';
    if( isset($settings['page-wineproducts-thumbnail-format']) && $settings['page-wineproducts-thumbnail-format'] == 'square-padded' ) {
        $thumbnail_format = $settings['page-wineproducts-thumbnail-format'];
        if( isset($settings['page-wineproducts-thumbnail-padding-color']) && $settings['page-wineproducts-thumbnail-padding-color'] != '' ) {
            $thumbnail_padding_color = $settings['page-wineproducts-thumbnail-padding-color'];
        } 
    }

/*    //
    // Load the product type definitions
    //
    $strsql = "SELECT id, name_s, name_p, object_def "
        . "FROM ciniki_product_types "
        . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
        . "ORDER BY id "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.wineproduction', array(
        array('container'=>'types', 'fname'=>'id',
            'fields'=>array('id', 'name_s', 'name_p', 'object_def')),
        ));
    $types = isset($rc['types'])?$rc['types']:array();
    $object_defs = array();
    // Prep the object defs
    foreach($types as $type_id => $type) {
        $object_defs[$type_id] = unserialize($type['object_def']);
    } */
    $tag_types = array(
        '11' => array(
            'id' => 11,
            'sname' => 'Type',
            'pname' => 'Types',
            ),
        '12' => array(
            'id' => 12,
            'names' => 'Varity',
            'pname' => 'Varietals',
            ),
        '13' => array(
            'id' => 13,
            'names' => 'Oak',
            'pname' => 'Oak',
            ),
        '14' => array(
            'id' => 14,
            'names' => 'Body',
            'pname' => 'Body',
            ),
        '15' => array(
            'id' => 15,
            'names' => 'Sweetness',
            'pname' => 'Sweetness',
            ),
        );

    //
    // Store the content created by the page
    //
    $page_content = '';

    //
    // FIXME: Check if anything has changed, and if not load from cache
    //
    
    if( $page['title'] == '' ) {
        $page_title = "Products";
    }
    $tags = array();
    $ciniki['response']['head']['og']['url'] = $ciniki['request']['domain_base_url'] . '/products';

    #
    # URLs
    # /products
    # /products/product
    # /products/category
    # /products/category/product
    # /products/category/subcategory
    # /products/category/subcategory/product
    #
    $base_url = $args['base_url'];
    $uri_split = $args['uri_split'];

    $display = '';
    $category_display = 'default';
    if( isset($settings['page-wineproducts-categories-format']) 
        && $settings['page-wineproducts-categories-format'] == 'list' 
        ) {
        $category_display = 'cilist';
    } elseif( isset($settings['page-wineproducts-categories-format']) 
        && $settings['page-wineproducts-categories-format'] == 'tradingcards' 
        ) {
        $category_display = 'tradingcards';
    }
//    $subcategory_display = 'default';
//    $product_display = 'default';
    while(isset($uri_split[0]) ) {
        $permalink = array_shift($uri_split);

        if( !isset($category) ) {
            //
            // Check if permalink is a category
            //
            $strsql = "SELECT DISTINCT tag_name "
                . "FROM ciniki_wineproduction_product_tags "
                . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "AND permalink = '" . ciniki_core_dbQuote($ciniki, $permalink) . "' "
                . "AND tag_type = 10 "
                . "LIMIT 1 " // Only grab the first one
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.wineproduction', 'category');
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( isset($rc['category']) ) {
                $category_permalink = $permalink;
                $page['title'] = $rc['category']['tag_name'];
                $display = 'category';

                //
                // Get any details about the category from settings
                //
                $strsql = "SELECT id, name, sequence, "
                    . "tag_type, display, "
                    . "primary_image_id, synopsis, description "
                    . "FROM ciniki_wineproduction_product_tagdetails "
                    . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                    . "AND tag_type = 10 "
                    . "AND permalink = '" . ciniki_core_dbQuote($ciniki, $category_permalink) . "' "
//                    . "AND subcategory = '' "
                    . "";
                $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.wineproduction', 'category');
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
                if( isset($rc['category']) ) {  
                    $category = $rc['category'];
                    if( $category['name'] != '' ) {
                        $page['title'] = $category['name'];
                    }
                    if( $category['display'] != '' && $category['display'] != 'default' ) {
                        $category_display = $category['display'];
                    }
                } else {
                    $category = array(
                        'id'=>0,
                        'name'=>$page['title'],
                        'sequence'=>1,
                        'tag_type'=>0,
                        'primary_image_id'=>0,
                        'synopsis'=>'',
                        'description'=>'',
                        );
                }
                $base_url .= '/' . $permalink;
                $category['base_url'] = $base_url;
                $category['permalink'] = $permalink;
                $page['breadcrumbs'][] = array('name'=>$page['title'], 'url'=>$base_url);
                continue;   // Skip to next piece of URI
            }
        }

        //
        // Check if permalink is a subcategory (if category is specified)
        //
        if( isset($category) && !isset($subcategory) ) {
            // Add breadcrumbs, set page_title
            $strsql = "SELECT DISTINCT tag_name "
                . "FROM ciniki_wineproduction_product_tags "
                . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . "AND permalink = '" . ciniki_core_dbQuote($ciniki, $permalink) . "' ";
//            if( isset($category['tag_type']) && $category['tag_type'] > 0 ) {
//                $strsql .= "AND tag_type = '" . ciniki_core_dbQuote($ciniki, $category['tag_type']) . "' ";
//            } else {
                $strsql .= "AND tag_type > 10 AND tag_type < 30 ";
//            }
            $strsql .= "LIMIT 1 " // Only grab the first one
                . "";
            $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.wineproduction', 'subcategory');
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            if( isset($rc['subcategory']) ) {
                $subcategory_permalink = $permalink;
                $page['title'] = $rc['subcategory']['tag_name'];
                $display = 'subcategoryproducts';

                //
                // Get any details about the category from settings
                //
                $strsql = "SELECT id, name, sequence, "
                    . "tag_type, display, "
                    . "primary_image_id, synopsis, description "
                    . "FROM ciniki_wineproduction_product_tagdetails "
                    . "WHERE tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                    . "AND tag_type IN (11,12,13,14,15) "
                    . "AND permalink = '" . ciniki_core_dbQuote($ciniki, $subcategory_permalink) . "' "
                    . "";
                $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.wineproduction', 'subcategory');
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
                if( isset($rc['subcategory']) ) {  
                    $subcategory = $rc['subcategory'];
                    if( $subcategory['name'] != '' ) {
                        $page['title'] = $subcategory['name'];
                    }
                } else {
                    $subcategory = array(
                        'id'=>0,
                        'name'=>$page['title'],
                        'sequence'=>1,
                        'tag_type'=>0,
                        'primary_image_id'=>0,
                        'synopsis'=>'',
                        'description'=>'',
                        );
                }
                $base_url .= '/' . $permalink;
                $subcategory['base_url'] = $base_url;
                $subcategory['permalink'] = $permalink;
                $page['breadcrumbs'][] = array('name'=>$page['title'], 'url'=>$base_url);
                continue;   // Skip to next piece of URI
            }
        }

        //
        // Check if permalink is a product
        //
        $display = 'product';
        $product_permalink = $permalink;
    
        if( isset($uri_split[1]) && $uri_split[0] == 'gallery' && $uri_split[1] != '' ) {
            $display = 'productpic';
            array_shift($uri_split);
            $image_permalink = array_shift($uri_split);
        }
    }

    //
    // Check what should be displayed if no uri specified
    //
    if( $display == '' ) {
        $display = 'categories';
    }

    //
    // Check for display of a category
    //
    if( $display == 'category' ) {
        //
        // Display category information
        //
        if( $category_display == 'default' || $category_display == 'cilist' ) {
            if( isset($category['primary_image_id']) && $category['primary_image_id'] > 0 ) {
                $page['blocks'][] = array('type'=>'asideimage', 'title'=>$category['name'], 'image_id'=>$category['primary_image_id']);
            }
            if( isset($category['description']) && $category['description'] != '' ) {
                $page['blocks'][] = array('type'=>'content', 'content'=>$category['description']);
            }
        }

        //
        // Check if there are subcategories or products to display
        //
        $strsql = "SELECT t2.tag_type, t2.tag_name AS name, "
            . "t2.permalink, "
            . "IF(IFNULL(ciniki_wineproduction_product_tagdetails.name, '')='',t2.tag_name, ciniki_wineproduction_product_tagdetails.name) AS cat_name, "
            . "IFNULL(ciniki_wineproduction_product_tagdetails.primary_image_id, 0) AS image_id, "
            . "IFNULL(ciniki_wineproduction_product_tagdetails.synopsis, '') AS synopsis, "
            . "IFNULL(MAX(ciniki_wineproduction_products.primary_image_id), 0) AS product_image_id, "
            . "COUNT(ciniki_wineproduction_products.id) AS num_products "
            . "FROM ciniki_wineproduction_product_tags AS t1 "
            . "INNER JOIN ciniki_wineproduction_product_tags AS t2 ON ("
                . "t1.product_id = t2.product_id "
                . "AND t2.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' ";
//         if( isset($category['tag_type']) && $category['tag_type'] > 0 ) {
//            $strsql .= "AND t2.tag_type = '" . ciniki_core_dbQuote($ciniki, $category['tag_type']) . "' ";
//         } else {
            $strsql .= "AND t2.tag_type > 10 AND t2.tag_type < 30 ";
//         }
         $strsql .= ") "
            . "LEFT JOIN ciniki_wineproduction_product_tagdetails ON ("
                . "t2.tag_type = ciniki_wineproduction_product_tagdetails.tag_type "
                . "AND t2.permalink = ciniki_wineproduction_product_tagdetails.permalink "
                . "AND ciniki_wineproduction_product_tagdetails.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "LEFT JOIN ciniki_wineproduction_products ON ("
                . "t2.product_id = ciniki_wineproduction_products.id "
                . "AND ciniki_wineproduction_products.status < 60 "
                . "AND (ciniki_wineproduction_products.flags&0x01) > 0 "
                . "AND ciniki_wineproduction_products.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE t1.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND t1.permalink = '" . ciniki_core_dbQuote($ciniki, $category_permalink) . "' "
            . "AND t1.tag_type = 10 "
            . "GROUP BY t2.tag_type, t2.tag_name "
            . "ORDER BY t2.tag_type, IFNULL(ciniki_wineproduction_product_tagdetails.sequence, 999), t2.tag_name "
            . "";
        ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryIDTree');
        $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.products', array(
//            array('container'=>'product_types', 'fname'=>'ptype', 'name'=>'product_type',
//                'fields'=>array('id'=>'ptype')),
            array('container'=>'types', 'fname'=>'tag_type', 'name'=>'type',
                'fields'=>array('tag_type', 'name')),
            array('container'=>'categories', 'fname'=>'name', 'name'=>'category',
                'fields'=>array('name'=>'cat_name', 'cat_name', 'permalink', 'image_id', 'synopsis', 
                    'product_image_id', 'num_products')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        //$page['blocks'][] = array('type'=>'content', 'html'=>'<pre>' . print_r($rc, true) . '</pre>');

        if( isset($rc['types']) && count($rc['types']) > 0 ) {
            foreach($rc['types'] as $type) {
                if( isset($type['categories']) && count($type['categories']) > 0 ) {
                    $subcategories = $type['categories'];
                    foreach($subcategories as $cid => $cat) {
                        if( $cat['image_id'] == 0 ) {
                            $subcategories[$cid]['image_id'] = $cat['product_image_id'];
                        }
                    }
                    $page['blocks'][] = array('type'=>'tagimages', 'size'=>'small', 
                        'title'=>(isset($tag_types[$type['tag_type']]) ? $tag_types[$type['tag_type']]['pname'] : ''), 
                        'base_url'=>$base_url, 'tags'=>$subcategories,
                        'thumbnail_format'=>$thumbnail_format, 'thumbnail_padding_color'=>$thumbnail_padding_color);
                    $display = '';
                    
                }
            }
        } else {
            $display = 'products';
        }

/*            $ptypes = $rc['types'];
            if( isset($category['tag_type']) && $category['tag_type'] > 0 ) {
                //
                // Ignore product types, just build list of categories
                //
                $categories = array();
                foreach($ptypes as $tag_type) {
                    if( isset($tag_type['categories']) ) {
                        $categories = array_merge($categories, $tag_type['categories']);
                    }
                }
                $page['blocks'][] = array('type'=>'tagimages', 'title'=>'', 'base_url'=>$base_url, 'tags'=>$categories,
                    'thumbnail_format'=>$thumbnail_format, 'thumbnail_padding_color'=>$thumbnail_padding_color);
                $display = '';
            } else {
                //
                // Go through the product types looking for names
                //
                $subcat_types = array();
                foreach($ptypes as $tid => $type) {
                    if( isset($tag_types[$type['tag_type']]) ) {
                        $sub_cat_name = $tag_types[$type['tag_type']]['pname'];
                    } else {
                        $sub_cat_name = '';
                    }
                    if( !isset($subcat_types[$sub_cat_name]) ) {
                        $subcat_types[$sub_cat_name] = array('name'=>$sub_cat_name, 'categories'=>$type['categories']);
                    } else {
                        foreach($type['categories'] as $new_id => $new_cat) {
                            // Check for existing category name
                            $found = 'no';
                            foreach($subcat_types[$sub_cat_name]['categories'] as $old_id => $old_cat) {
                                if( $old_cat['name'] == $new_cat['name'] ) {
                                    $subcat_types[$sub_cat_name]['categories'][$old_id]['num_products'] += $new_cat['num_products'];
                                    $found = 'yes';
                                    break;
                                }
                            }
                            if( $found == 'no' ) {
                                $subcat_types[$sub_cat_name]['categories'][] = $type['categories'][$new_id];
                            }
                        }
                    }
                }

                if( count($subcat_types) > 0 ) {
                    //
                    // Figure out the thumbnail size    
                    //
                    if( isset($settings['page-wineproducts-subcategories-size']) 
                        && $settings['page-wineproducts-subcategories-size'] != '' 
                        && $settings['page-wineproducts-subcategories-size'] != 'auto' 
                        ) {
                        $size = $settings['page-wineproducts-subcategories-size'];
                    } else {
                        $size = 'large';
                        foreach($subcat_types as $tid => $type) {
                            if( count($type['categories']) > 12 ) {
                                $size = 'small';
                            } elseif( count($type['categories']) > 6 ) {
                                $size = 'medium';
                            }
                        }
                    }
                    //
                    // Get highlight images
                    //
                    $strsql = "SELECT t2.permalink AS subcat, ciniki_wineproduction_products.primary_image_id "
                        . "FROM ciniki_wineproduction_product_tags AS t1, ciniki_wineproduction_products, ciniki_wineproduction_product_tags AS t2 "
                        . "WHERE t1.tag_type = 10 "
                        . "AND t1.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                        . "AND t1.product_id = ciniki_wineproduction_products.id "
                        . "AND t1.permalink = '" . ciniki_core_dbQuote($ciniki, $category_permalink) . "' "
                        . "AND ciniki_wineproduction_products.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                        . "AND ciniki_wineproduction_products.primary_image_id > 0 "
                        . "AND ciniki_wineproduction_products.start_date < UTC_TIMESTAMP() "
                        . "AND (ciniki_wineproduction_products.end_date = '0000-00-00 00:00:00' "
                            . "OR ciniki_wineproduction_products.end_date > UTC_TIMESTAMP()"
                            . ") "
                        . "AND ciniki_wineproduction_products.status < 60 "
                        . "AND (ciniki_wineproduction_products.flags&0x01) > 0 "
                        . "AND ciniki_wineproduction_products.id = t2.product_id "
                        . "AND t2.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                        . "AND t2.tag_type > 10 "
                        . "AND t2.tag_type < 30 "
                        . "ORDER BY t1.permalink, t2.permalink, ciniki_wineproduction_products.date_added "
                        . "";
                    $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.products', array(
                        array('container'=>'images', 'fname'=>'subcat', 'fields'=>array('primary_image_id')),
                        ));
                    if( $rc['stat'] != 'ok' ) {
                        return $rc;
                    }
//                    print "<pre>" . print_r($rc['images'], true) . "</pre>";
                    if( isset($rc['images']) ) {
                        $images = $rc['images'];
                        foreach($subcat_types as $tid => $type) {
                            foreach($type['categories'] as $cid => $cat) {
                                if( $cat['image_id'] == 0 && isset($images[$cat['permalink']]['primary_image_id']) ) {
                                    $subcat_types[$tid]['categories'][$cid]['image_id'] = $images[$cat['permalink']]['primary_image_id'];
                                }
                            }
                        }
                    }
                    //
                    // Output the product types
                    //
                    foreach($subcat_types as $type) {
//                        print "<pre>" . print_r($type, true) . "</pre>";
                        $page['blocks'][] = array('type'=>'tagimages', 'size'=>'small', 'base_url'=>$base_url, 'title'=>$type['name'], 'tags'=>$type['categories'],
                            'thumbnail_format'=>$thumbnail_format, 'thumbnail_padding_color'=>$thumbnail_padding_color);
                    }
                }

                //
                // Look for any products that are not sub-categorized
                //
                $display = 'categoryproducts';
            } 
        } else {
            $display = 'products';
        }
*/
        //
        // Don't look for a product list for the category if a specific category tag_type has been defined
        //
/*        if( isset($category['tag_type']) && $category['tag_type'] > 0 ) {
            $display = '';
        } */
    }

    //
    // Display the list of categories
    //
    if( $display == 'categories' ) {

        //
        // Check if slider is specified
        //
        if( isset($settings['page-wineproducts-slider-id']) && $settings['page-wineproducts-slider-id'] > 0 ) {
            $page['blocks'][] = array('type'=>'slider', 'slider-id'=>$settings['page-wineproducts-slider-id']);
        }

        //
        // Get the images for categories
        //
        $strsql = "SELECT t1.permalink, MAX(ciniki_wineproduction_products.primary_image_id) AS image_id "
            . "FROM ciniki_wineproduction_product_tags AS t1, ciniki_wineproduction_products "
            . "WHERE t1.tag_type = 10 "
            . "AND t1.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND t1.product_id = ciniki_wineproduction_products.id "
            . "AND ciniki_wineproduction_products.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND ciniki_wineproduction_products.primary_image_id > 0 "
            . "AND ciniki_wineproduction_products.start_date < UTC_TIMESTAMP() "
            . "AND (ciniki_wineproduction_products.end_date = '0000-00-00 00:00:00' "
                . "OR ciniki_wineproduction_products.end_date > UTC_TIMESTAMP()"
                . ") "
            . "AND ciniki_wineproduction_products.status < 60 "
            . "AND (ciniki_wineproduction_products.flags&0x01) > 0 "
            . "GROUP BY t1.permalink "
            . "ORDER BY t1.permalink, ciniki_wineproduction_products.date_added "
            . "";
        $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.products', array(
            array('container'=>'images', 'fname'=>'permalink', 'fields'=>array('image_id')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['images']) ) {
            $highlight_images = $rc['images'];
        }

        //
        // Get the list of categories
        //
        $strsql = "SELECT ciniki_wineproduction_product_tags.tag_name AS name, "
            . "IF(IFNULL(ciniki_wineproduction_product_tagdetails.name, '')='', ciniki_wineproduction_product_tags.tag_name, ciniki_wineproduction_product_tagdetails.name) AS cat_name, "
            . "IFNULL(ciniki_wineproduction_product_tagdetails.primary_image_id, 0) AS primary_image_id, "
            . "IFNULL(ciniki_wineproduction_product_tagdetails.synopsis, '') AS synopsis, "
            . "ciniki_wineproduction_product_tags.permalink, "
            . "'yes' AS is_details, "
            . "COUNT(ciniki_wineproduction_products.id) AS num_products "
            . "FROM ciniki_wineproduction_product_tags "
            . "INNER JOIN ciniki_wineproduction_products ON ("
                . "ciniki_wineproduction_product_tags.product_id = ciniki_wineproduction_products.id "
                . "AND ciniki_wineproduction_products.start_date < UTC_TIMESTAMP() "
                . "AND (ciniki_wineproduction_products.end_date = '0000-00-00 00:00:00' OR ciniki_wineproduction_products.end_date > UTC_TIMESTAMP()) "
                . "AND ciniki_wineproduction_products.status < 60 "
                . "AND (ciniki_wineproduction_products.flags&0x01) > 0 "
                . "AND ciniki_wineproduction_products.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "LEFT JOIN ciniki_wineproduction_product_tagdetails ON ("
                . "ciniki_wineproduction_product_tags.tag_type = ciniki_wineproduction_product_tagdetails.tag_type "
                . "AND ciniki_wineproduction_product_tags.permalink = ciniki_wineproduction_product_tagdetails.permalink "
                . "AND ciniki_wineproduction_product_tagdetails.tag_type = 10 "
                . "AND ciniki_wineproduction_product_tagdetails.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
                . ") "
            . "WHERE ciniki_wineproduction_product_tags.tnid = '" . ciniki_core_dbQuote($ciniki, $tnid) . "' "
            . "AND ciniki_wineproduction_product_tags.tag_type = 10 "
            . "AND ciniki_wineproduction_product_tags.tag_name <> '' "
            . "GROUP BY ciniki_wineproduction_product_tags.tag_name "
            . "ORDER BY IFNULL(ciniki_wineproduction_product_tagdetails.sequence, 99), ciniki_wineproduction_product_tags.tag_name "
            . "";
        $rc = ciniki_core_dbHashQueryIDTree($ciniki, $strsql, 'ciniki.products', array(
            array('container'=>'categories', 'fname'=>'name', 
                'fields'=>array('name', 'cat_name', 'title'=>'cat_name', 'permalink', 'image_id'=>'primary_image_id', 'num_products', 'synopsis', 'is_details')),
            ));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['categories']) && isset($highlight_images) ) {
            foreach($rc['categories'] as $cid => $cat) {
                if( $cat['image_id'] == 0 && isset($highlight_images[$cat['permalink']]['image_id']) ) {
                    $rc['categories'][$cid]['image_id'] = $highlight_images[$cat['permalink']]['image_id'];
                }
            }
        }
        if( !isset($rc['categories']) ) {
            $page['blocks'][] = array('type'=>'content', 'content'=>"I'm sorry, but we currently don't have any products available.");
        } elseif( $category_display == 'tradingcards' ) {
            $page['blocks'][] = array('type'=>'tradingcards', 
                'title'=>'', 
                'base_url'=>$base_url, 
                'cards'=>$rc['categories'],
                'thumbnail_format'=>$thumbnail_format, 
                'thumbnail_padding_color'=>$thumbnail_padding_color,
                );
        } elseif( $category_display == 'cilist' ) {
            $page['blocks'][] = array('type'=>'imagelist',
                'title'=>'',
                'base_url'=>$base_url,
                'list'=>$rc['categories'],
                'thumbnail_format'=>$thumbnail_format,
                'thumbnail_padding_color'=>$thumbnail_padding_color,
                );
        } else {
            $page['blocks'][] = array('type'=>'tagimages',
                'base_url'=>$base_url,
                'tags'=>$rc['categories'],
                'thumbnail_format'=>$thumbnail_format,
                'thumbnail_padding_color'=>$thumbnail_padding_color,
                );
        }
    }

    //
    // Display the list of products
    //
    elseif( $display == 'products' || $display == 'categoryproducts' || $display == 'subcategoryproducts' ) {
        //
        
        //
        // Check for any products that are not in a sub category
        //
        if( isset($category) && isset($subcategory) ) {
            //
            // Get the list of subcategory products
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'wineproduction', 'web', 'processRequestSubCategoryProducts');
            $rc = ciniki_wineproduction_web_processRequestSubCategoryProducts($ciniki, $settings, $tnid, $category, $subcategory);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $products = $rc['products']; 
        } elseif( isset($category) ) {
            //
            // Get the list of category products
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'wineproduction', 'web', 'processRequestCategoryProducts');
            $rc = ciniki_wineproduction_web_processRequestCategoryProducts($ciniki, $settings, $tnid, $category, null);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $products = $rc['products']; 
        } elseif( $display == 'products' ) {
            $page['blocks'][] = array('type'=>'content', 'content'=>"We're sorry but we don't have any products available yet");
        }

        //
        // Check if only a single product, open the product page
        //
        if( count($products) == 1 ) {
            $display = 'product';
            $product = array_shift($products);
            $product_permalink = $product['permalink'];
        }
        
        //
        // Sort the products
        //
/*        uasort($products, function($a, $b) {
            if( $a['sequence'] == $b['sequence'] ) {
                return strnatcmp($a['title'], $b['title']);
            } 
            return ($a['sequence'] < $b['sequence'])?-1:1;
        }); */

        //
        // Decide how to display the information
        //
        if( $display == 'subcategoryproducts' ) {
            if( isset($subcategory['primary_image_id']) && $subcategory['primary_image_id'] > 0 ) {
                $page['blocks'][] = array('type'=>'image', 'section'=>'primary-image', 'primary'=>'yes', 'image_id'=>$subcategory['primary_image_id'],
                    'title'=>$subcategory['name'], 'caption'=>'');
            }
            if( isset($subcategory['description']) && $subcategory['description'] != '' ) {
                $page['blocks'][] = array('type'=>'content', 'section'=>'content', 'content'=>$subcategory['description']);
            } elseif( isset($subcategory['synopsis']) && $subcategory['synopsis'] != '' ) {
                $page['blocks'][] = array('type'=>'content', 'section'=>'content', 'content'=>$subcategory['synopsis']);
            }
            //
            // Get the list of products for this subcategory
            //
            ciniki_core_loadMethod($ciniki, 'ciniki', 'wineproduction', 'web', 'processRequestProductsDetails');
            $rc = ciniki_wineproduction_web_processRequestProductsDetails($ciniki, $settings, $tnid, $products, 
                array('image'=>'yes'));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $page['blocks'][] = array('type'=>'imagelist', 'section'=>'imageproductlist', 'prices'=>'yes', 'codes'=>'yes', 'title'=>'', 'base_url'=>$base_url, 'list'=>$rc['products'],
                'noimage'=>'yes', 'thumbnail_format'=>$thumbnail_format, 'thumbnail_padding_color'=>$thumbnail_padding_color);
        } elseif( $display == 'categoryproducts' ) {
            // FIXME: Add query for category products
        } elseif( $display != 'product' ) {
            if( isset($settings['page-wineproducts-list-format']) && $settings['page-wineproducts-list-format'] == 'tradingcards' ) {
                $page['blocks'][] = array('type'=>'tradingcards', 
                    'section'=>'imageproductlist', 
                    'prices'=>'yes', 
                    'title'=>'', 
                    'base_url'=>$base_url, 
                    'cards'=>$rc['products'],
                    'noimage'=>'yes', 
                    'thumbnail_format'=>$thumbnail_format, 
                    'thumbnail_padding_color'=>$thumbnail_padding_color,
                    );
            } else {
                $page['blocks'][] = array('type'=>'imagelist', 
                    'section'=>'imageproductlist', 
                    'prices'=>'yes', 
                    'title'=>'', 
                    'base_url'=>$base_url, 
                    'list'=>$rc['products'],
                    'noimage'=>'yes', 
                    'thumbnail_format'=>$thumbnail_format, 
                    'thumbnail_padding_color'=>$thumbnail_padding_color,
                    );
            }
        }
    }

    //
    // Display a product
    //
    if( $display == 'product' || $display == 'productpic' ) {
        //
        // Get the product information
        //
        ciniki_core_loadMethod($ciniki, 'ciniki', 'wineproduction', 'web', 'productDetails');
        $rc = ciniki_wineproduction_web_productDetails($ciniki, $settings, $ciniki['request']['tnid'], 
            array('product_permalink'=>$product_permalink));
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        $product = $rc['product'];
        $page['title'] = $product['name'];
        $page['breadcrumbs'][] = array('name'=>$product['name'], 'url'=>$base_url . '/' . $product['permalink']);
        
//      $ciniki['response']['head']['links'][] = array('rel'=>'canonical', 
//        'href'=>$ciniki['request']['domain_base_url'] . '/products/product/' . $product_permalink
//         );
//        $ciniki['response']['head']['og']['url'] .= '/' . $product_permalink;
        $ciniki['response']['head']['og']['url'] = $ciniki['request']['domain_base_url'] . $base_url . '/' . $product_permalink;
        $ciniki['response']['head']['og']['description'] = strip_tags($product['synopsis']);

        //
        // Check if image requested
        //
        $product_base_url = $base_url . '/' . $product['permalink'];
        if( $display == 'productpic' ) {
            if( !isset($product['images']) || count($product['images']) < 1 ) {
                $page['blocks'][] = array('type'=>'message', 'content'=>"I'm sorry, but we can't seem to find the image you requested.");
            } else {
                ciniki_core_loadMethod($ciniki, 'ciniki', 'web', 'private', 'galleryFindNextPrev');
                $rc = ciniki_web_galleryFindNextPrev($ciniki, $product['images'], $image_permalink);
                if( $rc['stat'] != 'ok' ) {
                    return $rc;
                }
                if( $rc['img'] == NULL ) {
                    $page['blocks'][] = array('type'=>'message', 'content'=>"I'm sorry, but we can't seem to find the image you requested.");
                } else {
                    $page['breadcrumbs'][] = array('name'=>$rc['img']['title'], 'url'=>$product_base_url . '/gallery/' . $image_permalink);
                    if( $rc['img']['title'] != '' ) {
                        $page['title'] .= ' - ' . $rc['img']['title'];
                    }
                    $block = array('type'=>'galleryimage', 'primary'=>'yes', 'image'=>$rc['img']);
                    if( $rc['prev'] != null ) {
                        $block['prev'] = array('url'=>$product_base_url . '/gallery/' . $rc['prev']['permalink'], 'image_id'=>$rc['prev']['image_id']);
                    }
                    if( $rc['next'] != null ) {
                        $block['next'] = array('url'=>$product_base_url . '/gallery/' . $rc['next']['permalink'], 'image_id'=>$rc['next']['image_id']);
                    }
                    $page['blocks'][] = $block;
                }
            }

/*                $image_permalink = $ciniki['request']['uri_split'][5];
            $ciniki['response']['head']['links']['canonical']['href'] .= '/gallery/' . $image_permalink;
            ciniki_core_loadMethod($ciniki, 'ciniki', 'web', 'private', 'processGalleryImage');
            $rc = ciniki_web_processGalleryImage($ciniki, $settings, $ciniki['request']['tnid'],
                array('item'=>$product,
                    'gallery_url'=>$ciniki['request']['base_url'] . "/products/category/$category_permalink/product/$product_permalink/gallery",
                    'article_title'=>$article_title .= " - <a href='" . $ciniki['request']['base_url'] 
                        . "/products/category/$category_permalink/product/$product_permalink'>" . $product['name'] . "</a>",
                    'image_permalink'=>$image_permalink,
                ));
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
            $page['blocks'][] = array('type'=>'content', 'html'=>$rc['content']); */
        } 
       
        //
        // Display the product
        //
        else {
            if( isset($product['image_id']) && $product['image_id'] > 0 ) {
                $page['blocks'][] = array('type'=>'image', 
                    'section'=>'primary-image', 
                    'primary'=>'yes', 
                    'image_id'=>$product['image_id'],
                    'title'=>$product['name'], 
                    'caption'=>'',
                    );
            }
            if( isset($product['description']) && $product['description'] != '' ) {
                $page['blocks'][] = array('type'=>'content', 'section'=>'content', 'content'=>$product['description']);
            } elseif( isset($product['synopsis']) && $product['synopsis'] != '' ) {
                $page['blocks'][] = array('type'=>'content', 'section'=>'content', 'content'=>$product['synopsis']);
            }
            
            if( isset($product['prices']) && count($product['prices']) > 0 ) {
                $page['blocks'][] = array('type'=>'prices', 'section'=>'prices', 'prices'=>$product['prices']);
            }
            if( isset($product['files']) && count($product['files']) > 0 ) {
                $page['blocks'][] = array('type'=>'files', 'section'=>'files', 'base_url'=>$product_base_url . '/download', 'files'=>$product['files']);
            }
            
            // FIXME: Add similar products
            // FIXME: Add recipes
            // Add share buttons
            if( !isset($settings['page-wineproducts-share-buttons']) || $settings['page-wineproducts-share-buttons'] == 'yes' ) {
                $page['blocks'][] = array('type'=>'sharebuttons', 'section'=>'share', 'pagetitle'=>$product['name'], 'tags'=>array());
            }
            if( isset($product['images']) && count($product['images']) > 0 ) {
                $page['blocks'][] = array('type'=>'gallery', 'section'=>'gallery', 'title'=>'Additional Images', 'base_url'=>$product_base_url . '/gallery', 
                    'images'=>$product['images'], 'thumbnail_format'=>$thumbnail_format, 'thumbnail_padding_color'=>$thumbnail_padding_color);
            }
        }
    }

    return array('stat'=>'ok', 'page'=>$page);
}
?>
