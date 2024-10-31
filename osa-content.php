<?php

/**
 * Plugin Name: OSA Content
 * Plugin URI:  https://osa.archiwa.org/wtyczka-wordpress
 * Description: Publikuj na stronie WordPress zawartość Twojego archiwum pobraną z osa.archiwa.org. UWAGA! deinstalacja wtyczki oznacza usunięcie metadanych załadowanych z OSA. Pliki załączników załadowanych z OSA należy usunąć ręcznie.
 * Version:     0.5
 * Author:      Fundacja Ośrodka KARTA - Obserwatorium Archiwistyki Społecznej
 * Author URI:  https://archiwa.org
 * License:     GPL2
 * Text Domain: osa
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

class Osa_Content_Synchronization {

  public function __construct() {

    add_action( 'plugins_loaded', array( $this, 'init' ) );

    register_deactivation_hook( __FILE__, array( $this, 'osa_plugin_deactivation' ) );

    register_uninstall_hook( __FILE__, array( 'Osa_Content_Synchronization', 'osa_plugin_uninstall' ) );
  }

  public function osa_set_post_name( $post_id, $post, $update ) {
    $post_type = get_post_type($post_id);

    if ( 'osa_document' != $post_type && 'osa_unit' != $post_type && 'osa_series' != $post_type && 'osa_collection' != $post_type ) {
      return;
    }

    $signature = sanitize_title( OSA_Data::get_post_meta_by_post_id( $post_id, 'osa_signature' ) );

    if ( isset($signature) && !empty( $signature ) > 0 && ( $post->post_name != $signature) ) {
      $post_new_data = array(
        ID => $post_id,
        "post_name" => $signature
      );

      wp_update_post( $post_new_data );
    }

  }

  public function init() {
    require_once plugin_dir_path( __FILE__ ) . 'model/class-osa-data.php';
    require_once plugin_dir_path( __FILE__ ) . 'class-sync-queue.php';
    require_once plugin_dir_path( __FILE__ ) . 'osa-settings-menu.php';
    require_once plugin_dir_path( __FILE__ ) . 'theme/functions.php';

    $this->settings_menu = new Osa_Settings_Menu();

    add_action( 'init', array( $this, 'osa_setup_post_type' ) );

    add_filter( 'query_vars', array( $this, 'osa_register_query_vars' ) );
    add_action( 'pre_get_posts', array( $this, 'osa_pre_get_posts' ), 1 );
    add_action( 'init', array( $this, 'osa_rewrite_tag' ), 10, 0);
    add_action( 'init', array( $this, 'osa_rewrite_rule' ), 10, 0);
    add_action( 'save_post', array( $this, 'osa_set_post_name' ), 10, 3 );

    if ( get_option( 'osa_use_default_theme' , true) ) {
      add_filter( 'single_template', array( $this, 'osa_single_template_selector' ) );
    }
  }

  public function osa_rewrite_tag() {
    add_rewrite_tag( '%signature%', '([^&]+)', 'osa_signature=' );
  }

  public function osa_rewrite_rule() {
    add_rewrite_rule( '^dokumenty/([^/]*)/?', 'index.php?post_type=osa_document&signature=$matches[1]','top' );
    add_rewrite_rule( '^jednostki/([^/]*)/?', 'index.php?post_type=osa_unit&signature=$matches[1]','top' );
    add_rewrite_rule( '^serie/([^/]*)/?', 'index.php?post_type=osa_series&signature=$matches[1]','top' );
    add_rewrite_rule( '^zbiory/([^/]*)/?', 'index.php?post_type=osa_collection&signature=$matches[1]','top' );
  }

  public function osa_register_query_vars( $vars ) {
    $vars[] = 'signature';
    return $vars;
  }

  public function osa_single_template_selector( $single ) {
    global $post;

    if ( $post->post_type == 'osa_collection' ||
            $post->post_type == 'osa_series' ||
            $post->post_type == 'osa_unit' ||
            $post->post_type == 'osa_document' ) {
        if ( file_exists( plugin_dir_path( __FILE__ ) . 'theme/single.php' ) ) {
            return plugin_dir_path( __FILE__ ) . 'theme/single.php';
        }
    }

    return $single;
  }

  public function osa_pre_get_posts( $query ) {
    if ( is_admin() || ! $query->is_main_query() ){
	    return;
    }

    if ( !is_post_type_archive( 'osa_collection' ) && !is_post_type_archive( 'osa_series' ) && !is_post_type_archive( 'osa_unit' ) && !is_post_type_archive( 'osa_document' )) {
      return;
    }

    $meta_query = array();
    $signature = get_query_var( 'signature' );

    // add meta_query elements
    if ( !empty( $signature ) ){
        $meta_query[] = array( 'key' => 'osa_signature', 'value' => $signature, 'compare' => 'LIKE' );
    }
    if ( count( $meta_query ) > 1 ){
        $meta_query['relation'] = 'AND';
    }

    if ( count( $meta_query ) > 0 ){
        $query->set( 'meta_query', $meta_query );
    }
  }

  function osa_setup_post_type() {
    register_post_type( 'osa_collection', array(
        'labels' => array(
            'name' => __( 'Zbiory' ),
            'singular_name' => __( 'Zbiór' ),
        ),
        'rewrite' => array(
          'slug' => 'kolekcja'
        ),
        'public'                => true,
        'has_archive'           => true,
        'menu_position'         => 20,
        'supports' => array( 'title', 'editor', 'custom-fields', 'page-attributes' )
        )
    );

    register_post_type( 'osa_series', array(
        'labels' => array(
            'name' => __( 'Serie' ),
            'singular_name' => __( 'Seria' ),
        ),
        'rewrite' => array(
          'slug' => 'seria'
        ),
        'public'                => true,
        'has_archive'           => true,
        'menu_position' => 20,
        'supports' => array( 'title', 'editor', 'custom-fields', 'page-attributes' )
        )
    );

    register_post_type( 'osa_unit', array(
        'labels' => array(
            'name' => __( 'Jednostki' ),
            'singular_name' => __( 'Jednostka' ),
        ),
        'rewrite' => array(
          'slug' => 'jednostka'
        ),
        'public'                => true,
        'has_archive'           => true,
        'menu_position' => 20,
        'supports' => array( 'title', 'editor', 'custom-fields', 'page-attributes', 'thumbnail' )
        )
    );

    register_post_type( 'osa_document', array(
        'labels' => array(
            'name' => __( 'Dokumenty' ),
            'singular_name' => __( 'Dokument' ),
        ),
        'rewrite' => array(
          'slug' => 'dokument'
        ),
        'public'                => true,
        'has_archive'           => true,
        'menu_position' => 20,
        'supports' => array( 'title', 'editor', 'custom-fields', 'page-attributes', 'thumbnail' )
        )
    );
    //TODO: move to activation hook
    flush_rewrite_rules();
  }

  public function osa_plugin_deactivation() {
    unregister_post_type( 'osa_collection' );
    unregister_post_type( 'osa_series' );
    unregister_post_type( 'osa_unit' );
    unregister_post_type( 'osa_document' );

    //clean up tasks
    if ( isset( $this->settings_menu ) && isset( $this->settings_menu->sync_process ) ) {
      $this->settings_menu->sync_process->cancel();
    }

    flush_rewrite_rules();
  }

  public static function osa_plugin_uninstall() {
    //clean up options
    delete_option( 'osa_use_default_theme' );
    delete_option( 'osa_url' );
    delete_option( 'osa_institution_id' );
    delete_option( 'osa_import_scope' );
    delete_option( 'osa_collection_ids' );
    delete_option( 'osa_use_default_theme' );
    delete_option( 'osa_sync_start_time' );
    delete_option( 'osa_sync_current_run_start_time' );
    delete_option( 'osa_sync_is_started' );
    delete_option( 'osa_sync_is_completed' );
    delete_option( 'osa_sync_is_running' );
    delete_option( 'osa_sync_completed_time' );

    //delete custom posts and meta
    Osa_Content_Synchronization::delete_custom_posts();
    Osa_Content_Synchronization::delete_post_meta();
  }

  public static function delete_custom_posts() {
    global $wpdb;

    $wpdb->query(
      $wpdb->prepare(
        "DELETE FROM $wpdb->posts WHERE post_type in ('osa_collection', 'osa_series', 'osa_unit', 'osa_document')"
      )
    );
  }

  public static function delete_post_meta() {
    global $wpdb;

    $wpdb->query(
      $wpdb->prepare(
        "DELETE FROM $wpdb->postmeta WHERE meta_key like 'osa_%'"
      )
    );
  }

}

new Osa_Content_Synchronization();