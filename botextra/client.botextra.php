<?php
define('WP_USE_THEMES', false);
require_once('./wp-blog-header.php');

class botExtraScript extends botExtraClient {
    var $uploadDir = '',
    $uploadUrl = '';

    function __construct(){
        $this->script       = 'Wordpress';
        $upDir              = wp_upload_dir();
        $this->uploadDir    = $upDir['path'];
        $this->uploadUrl    = $upDir['url'];
    }


    function getCat(){
        $taxonomies = get_taxonomies();
        foreach ($taxonomies as $k => $v) {
            if ($v == 'post_tag') {
                unset($taxonomies[$k]);
                continue;
            }
            $categories = get_terms( $v, array('hide_empty' => false));
            $categoryHierarchy = $return = array();
            bx_sort_terms_hierarchicaly($categories, $categoryHierarchy);
            bx_flat_array($categoryHierarchy,$return);
            $taxonomies[$k] = $return;

        }
        return $this->answer('OK','Kategoriler alindi',$taxonomies);


        /* 4.0.4 öncesi

        $catList = get_categories('hide_empty=0');
        foreach($catList as $cat) {
        $return[$cat->cat_ID] = $cat->name;
        }
        return $this->answer('OK','Kategoriler alindi',$return);
        */
    }

    function addToWebsite($content,$attachments = array()){
        #Check old post
        if ($oldPost = query_posts('post_type=any&post_status=any&meta_key=_botextra_url&meta_value='.$content['url'])) {
            $oldPost = current($oldPost);
            $postId  = $oldPost->ID;
            $this->clearOldPost($postId);
        }


        $postCats    = explode(',',$content['category_id']);
        foreach ($postCats as $k => $v) {
            if (strpos($v,'.') !== FALSE) {
                $parentCats = explode(".",$v);
                unset($postCats[$k]);
                foreach ($parentCats as $parentCat) {
                    $postCats[] = $parentCat;
                }
            }
        }


        $seoName      = $this->seoString($content['title']);
        $orginalImage = $content['image'];
        if ($content['save_image']) {

            if ($imageData = $this->checkIsImageAdded($content['image'])) {
                $content['image']         = $imageData['url'];
                $attachments['featured']  = $imageData['id'];
            } else {
                $content['image']         = $this->download($content['image'],$seoName);
                $attachments['featured']  = $this->addAttachment($content['image'],$content['title'],$orginalImage);
            }

            if (preg_match_all('/< *img[^>]*src *= *["\']?([^"\']*)/', stripcslashes($content['content']), $matches)) {

                foreach ($matches[1] as $image) {
                    if ($image == '{image}') continue;

                    if ($imageData = $this->checkIsImageAdded($image)) {
                        $newImg = $imageData['url'];
                    } else {
                        $newImg             = $this->download($image,uniqid($seoName.'-'));
                        $attachments[]      = $this->addAttachment($newImg,$content['title'],$image);
                    }

                    $content['content'] = str_replace($image, $newImg, stripcslashes($content['content']));
                }

            }
        }

        if ($content['use_image_at_content']){
            $content['content'] = '<img alt="'.$content['title'].'" src="'.$content['image'].'"> <br>'.$content['content'];
        }

        $findAndReplace = array('{image}' => $content['image']);
        if ($content['save_game'] && isset($content['extra']['gameSwf'])){
            $orginalSwf = $content['extra']['gameSwf'];
            $gameSwf = $this->download($content['extra']['gameSwf'],uniqid($seoName.'-'),'swf');

            $attachments['gameSwf']      = $this->addAttachment($gameSwf,$content['title'],$orginalSwf);
            $findAndReplace['{gameSwf}'] = $gameSwf;
            $findAndReplace[$content['extra']['gameSwf']] = $gameSwf;
            $content['extra']['gameSwf'] = $gameSwf;
        }

        $content['title']   = str_ireplace(array_keys($findAndReplace), array_values($findAndReplace), $content['title']);
        $content['content'] = str_ireplace(array_keys($findAndReplace), array_values($findAndReplace), $content['content']);

        kses_remove_filters();
        $post = array(
            'post_author'   => 1,
            'post_category' => $postCats, 
            'post_title'    => $content['title'], 
            'post_content'  => $content['content'],
            'tags_input'    => $content['tags'],
            'post_type'     => 'post',
            'post_status'   => 'publish'
        );  

        if (isset($content['extra']['specials'])){
            $wpData = $content['extra']['specials'];

            if (isset($wpData['post_author'])){
                $authors = explode(',', $wpData['post_author']);
                $post['post_author'] = $authors[array_rand($authors)];
            }

            if (isset($wpData['post_date']))
                $post['post_date'] = $wpData['post_date'];

            if (isset($wpData['post_type']))
                $post['post_type'] = $wpData['post_type'];

            if (isset($wpData['post_excerpt']))
                $post['post_excerpt'] = $wpData['post_excerpt'];

            if (isset($wpData['post_status']))
                $post['post_status'] = $wpData['post_status'];
        }

        if (isset($postId)) {
            $post['ID'] = $postId;
            wp_update_post($post);
            $successText = 'İçerik Güncellendi';
        } else {
            $postId = wp_insert_post($post);
            /*
            $postCats = array_map('trim',$postCats);
            $postCats = array_map('intval',$postCats);
            wp_set_object_terms($postId , explode(',',$content['tags']), 'product_tag', false);
            wp_set_object_terms($postId ,$postCats, 'product_cat', false);
            */
            
            $successText = 'İçerik Eklendi';
        }

        if (!$postId) return $this->answer("HATA","İçerik Eklenmedi",'','ERR010');

        #Aio Seo
        add_post_meta($postId, '_aioseop_title', $content['title']);
        add_post_meta($postId, '_aioseop_keywords', $content['tags']);
        add_post_meta($postId, '_aioseop_description', $content['desc']);

        #Yoast Seo
        add_post_meta($postId, '_yoast_wpseo_title', $content['title']);
        add_post_meta($postId, '_yoast_wpseo_focuskw', $content['title']);
        add_post_meta($postId, '_yoast_wpseo_metakeywords', $content['tags']);
        add_post_meta($postId, '_yoast_wpseo_metadesc', $content['desc']);

        #Added Url
        add_post_meta($postId, '_botextra_url', $content['url']);

        #Special Field
        if (isset($wpData)) {
            $wpData = $wpData['special_fields'];
            $fields = $wpData['field_name'];
            $values = $wpData['value'];
            # V4.0.0.1 recursive custom keys.


            $specialFieldsMerge = array_combine($fields,$values);

            #world's most annoying problem
            $finalArray = array();
            foreach ($specialFieldsMerge as $k => $v) {
                if (strpos($k,'->')) {
                    $tmpKeys = explode("->",$k);
                    $b = array();
                    $c =&$b;
                    foreach ($tmpKeys as $key) {
                        $c[$key] = array();
                        $c       =& $c[$key];
                    }
                    $c = $v;
                    $finalArray = array_merge_recursive($finalArray,$b);
                } else {
                    $finalArray[$k] = $v;
                }
            }


            foreach ($finalArray as $k => $v) {
                $v = str_ireplace(array_keys($findAndReplace), array_values($findAndReplace),$v);
                add_post_meta($postId, $k, $v);
                if (taxonomy_exists($k)) {
                    $taxinormy = get_taxonomy($k);

                    if (strpos($v,',')) {
                        $vArray = explode(',',$v); 
                    } else {
                        $vArray = array($v);
                    }
                    foreach($vArray as $v) {
                        $termId = term_exists( $v, $k );
                        if (!$termId) {
                            $termId = wp_insert_term($v,$k);
                            $termId = $termId->term_id;
                        }

                        if ($taxinormy->hierarchical) {
                            wp_set_post_terms( $postId, $termId, $k);
                        } else {
                            wp_set_post_terms( $postId, $vArray, $k);
                        }
                    }
                }
            }
        }

        #Attachments
        if ($attachments) {
            if (isset($attachments['featured'])) {
                set_post_thumbnail( $postId, $attachments['featured'] );
            }
            foreach ($attachments as $attachId) {
                wp_update_post(array('ID'=>$attachId,'post_parent'=>$postId));
            }
        }

        return $this->answer("OK",$successText,get_permalink($postId));
    }


    function createCategories($categories) {
        if (!$categories)
            return $this->answer("HATA","Kategori bulunamadı",'','ERR011');

        require_once('./wp-admin/includes/taxonomy.php');
        foreach ($categories as $catName) {
            wp_create_category($catName);
        }
        return $this->getCat();
    }

    function addAttachment($fileName='',$content='',$imageUrl=''){
        include_once( ABSPATH . 'wp-admin/includes/image.php' );
        $fileName       = str_replace($this->uploadUrl,$this->uploadDir.'/',$fileName);
        $wpFiletype     = wp_check_filetype(basename($fileName), null );
        $attachment     = array(
            'post_mime_type' => $wpFiletype['type'],
            'post_title'     => preg_replace('/\.[^.]+$/', '', basename($fileName)),
            'post_content'   => $content,
            'post_excerpt'   => $content,
            'post_status'    => 'inherit'
        );

        $attachId   = wp_insert_attachment( $attachment, $fileName );
        $attachData = wp_generate_attachment_metadata( $attachId, $fileName );
        wp_update_attachment_metadata( $attachId, $attachData );
        update_post_meta( $attachId, '_wp_attachment_image_alt', $content);
        if ($imageUrl) {
            add_post_meta($attachId, '_botextra_image_url', $imageUrl);
        }
        return $attachId;
    }

    function checkIsImageAdded($url){
        $attachment = query_posts('meta_key=_botextra_image_url&post_status=any&post_type=attachment&meta_value='.$url);
        if (!$attachment) return false;
        $attachment = current($attachment);
        $url = wp_get_attachment_url( $attachment->ID , false );
        return array('id'=>$attachment->ID,'url'=>$url);
    }

    function clearOldPost($postId){
        #Meta Delete
        foreach (get_post_meta($postId) as $key => $value)
            delete_post_meta($postId, $key);
        #Term silme eklenecek

        #Media delete
        $media = get_children( array('post_parent' => $postId,'post_type'   => 'attachment'));

        if(empty($media)) return;

        foreach( $media as $file ) {
            @wp_delete_attachment( $file->ID );
        }
    }

}

function bx_flat_array(&$cats,&$return,$prefixValue = '',$prefixKey = '') {
    foreach ($cats as $k => $v) {
        $ek = ($prefixValue) ? $prefixValue . ' -> ' : '';
        $ekKey = ($prefixKey) ? $prefixKey . '.' : '';
        $return[$ekKey .$k] = $ek . $v->name;

        if ($v->children) {
            bx_flat_array($v->children,$return,$ek . $v->name, $ekKey . $k);
        }
    }
}


function bx_sort_terms_hierarchicaly(&$cats, &$into, $parentId = 0) {
    $cats = (array)$cats;
    $into = (array)$into;
    foreach ($cats as $i => $cat) {
        if ($cat->parent == $parentId) {
            $into[$cat->term_id] = $cat;
            unset($cats[$i]);
        }
    }

    foreach ($into as $topCat) {
        $topCat->children = array();
        bx_sort_terms_hierarchicaly($cats, $topCat->children, $topCat->term_id);
    }
}
?>
