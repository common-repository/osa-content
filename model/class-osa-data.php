<?php

abstract Class OSA_Data {
  
  private static function _init() {
    //set up debug to debug.log
    $error_path = ini_get( 'error_log' );
    $debug_log = ini_get( 'WP_DEBUG_LOG');
    if ( empty( $error_path ) && ( $debug_log ) === TRUE ) {
        ini_set( "error_log", content_url() . '/debug.log' );
    }
    require_once plugin_dir_path( __FILE__ ) . 'class-osa-collection.php';
    require_once plugin_dir_path( __FILE__ ) . 'class-osa-series.php';
    require_once plugin_dir_path( __FILE__ ) . 'class-osa-unit.php';
    require_once plugin_dir_path( __FILE__ ) . 'class-osa-document.php';
  }
  
  public static function fromJson($type = false, $json = false) {
    if (!$type || !$json) {
      throw new Exception("Type or json data invalid!");
    }
    OSA_Data::_init();
    
    switch ($type): 
      case 'collections':
        return new OSA_Collection($json);
      case 'series':
        return new OSA_Series($json);
      case 'units':
        return new OSA_Unit($json);
      case 'documents':
        return new OSA_Document($json);
      default:
        throw new Exception("Data type not recognized: " . $type);
    endswitch;
  }
  
  public function __construct($json = false) {
    if ($json) {
      OSA_Data::_init();
      $this->setData($json, true);
    }
  }

  public function setData($data) {
    foreach ($data AS $key => $value) {
        $this->{$key} = $value;
    }
  }
  
  public function get_post_type() {
    return OSA_Data::_get_post_type($this->level);
  }
  
  public function save($post_status = 'publish', $comment_status = 'closed') {
    $post_parent_id = $this->_get_post_parent_id();
    
    $post_params = array(
      'post_date' => ( isset( $this->createdAt ) ? $this->createdAt :null ) ,
      'post_modified' => ( isset( $this->modifiedAt ) ? $this->modifiedAt : null ),
      'post_status' => $post_status,
      'post_title' => $this->title,
      'post_name' => sanitize_title( $this->signature ),
      'post_type' => 'osa_' . $this->level,
      'coment_status' => $comment_status,
      'post_parent' => $post_parent_id
    );
    
    //update post if exists
    $wp_post_id = $this->_get_saved_post_id(); 
    if ($wp_post_id > 0) {
      $post_params['ID'] = $wp_post_id;
      wp_update_post($post_params);
    } else {
      $wp_post_id = wp_insert_post($post_params, true);
    }   
        
    //TODO: handle indices as tags
    
    $this->_save_meta($wp_post_id);
    
    return $wp_post_id;
  }
  
  private function _save_meta($wp_post_id) {
    foreach ($this as $key => $value) {
      if (isset($this->{$key})) {
        update_post_meta($wp_post_id, 'osa_' . $key, $value);
      }
    }
  }
  
  private function _get_post_parent_id() {
    if ($this->level == 'series' || $this->level == 'unit') {
      $parent_id = $this->parentId;
      $parent_collection = OSA_Data::find_post_id_by_osa_id($parent_id, 'collection');
      if ( $parent_collection > 0 ) {
        return $parent_collection;
      }      
      return OSA_Data::find_post_id_by_osa_id($parent_id, 'series');
    } elseif ($this->level == 'document') {
      $parent_id = $this->parentId;
      return OSA_Data::find_post_id_by_osa_id($parent_id, 'unit');
    }
    
    return 0;
  }
  
  private function _get_saved_post_id() {  
    return OSA_Data::find_post_id_by_osa_id($this->id, $this->level);
  }
  
  private static function _get_post_type($level) {
    return 'osa_' . $level;
  }
  
  public static function find_post_id_by_osa_id($osa_id, $type) {
    $query = new WP_Query(array(
        'post_type' => OSA_Data::_get_post_type($type),
        'meta_key' => 'osa_id',
        'meta_value' => $osa_id,
    ));
    
    if ($query->have_posts()) {
      $id = $query->get_posts()[0]->ID;
      return $id;
    } else {
      return 0;
    }
  }
  
  public static function get_post_meta_by_post_id($post_id, $meta_key) {
      return get_post_meta($post_id, $meta_key, true);
  }
}