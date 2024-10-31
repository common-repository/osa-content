<?php

class OSA_Synchronization_queue {
    protected $action = 'osa_cron_task_heartbeat';
    public $date_format = 'Y-m-d H:i:s';
    protected $task_key_prefix = 'osa_sync_task_';
    
    protected $is_started_key = 'osa_sync_is_started';
    protected $is_completed_key = 'osa_sync_is_completed';
    protected $is_running_key = 'osa_sync_is_running';
    protected $start_time_key = 'osa_sync_start_time';
    protected $completed_time_key = 'osa_sync_completed_time';
    protected $current_run_start_time_key = 'osa_sync_current_run_start_time';

    public $per_page = 30;
    protected $task_run_time_buffer = 5;
    protected $next_task_time_offset = 10;
    protected $max_time_from_start = 4 * 60 * 60;
    
    protected $max_time;    
    protected $current_key;
    protected $current_data;
        
    public function __construct() {
        $this->max_time = ini_get( 'max_execution_time' );
        if (!isset($this->max_time) || $this->max_time < 1) {
            $this->max_time = 20;
        }
        
        $error_path = ini_get( 'error_log' );
        $debug_log = ini_get( 'WP_DEBUG_LOG');
        if ( empty( $error_path ) && ( $debug_log ) === TRUE ) {
            ini_set( "error_log", content_url() . '/debug.log' );
        }

        $this->set_up_collections_filter();

        add_action( $this->action, array( $this, 'osa_cron_task_heartbeat' ) );
        add_filter( 'cron_schedules', array( $this, 'osa_cron_task_heartbeat_schedule_time' ) );        
    }

    private function set_up_collections_filter() {
        $ids_as_string = get_option( 'osa_collection_ids' );

        if ( !empty( $ids_as_string ) ) {
            $ids = array_map( function ($element) { return trim( $element ); }, explode( ',', $ids_as_string ) );

            if ( !empty( $ids ) ) {
                $this->collections_filter = $ids;
            }
        }

    }
    
    public function osa_cron_task_heartbeat_schedule_time( $schedules ) {
        $schedules['osa_task_refresh_interval'] = array(
            'interval' => $this->get_schedule_interval(),
            'display'  => esc_html__( 'Every execution time limit plus 10 seconds' ),
        );
 
        return $schedules;
    }
    
    private function get_schedule_interval() {
        return $this->max_time + $this->next_task_time_offset;
    }
    
    public function osa_cron_task_heartbeat() {        
        $this->log_debug('OSA sychronization cron heartbeat called');
        if ( $this->is_started() && ! $this->is_running() ) {
            $this->set_is_running( true );
            $this->record_start_time();
            while ( $this->has_current() ) {
                $params = $this->get_current();
                $result = $this->run_task($params);
                if ( ! $result ) {
                    $this->log_debug( "Terminating OSA cron task execution at " . date( $this->date_format ) );
                    $this->set_is_running( false );
                    return ; //terminate current execution
                }
                $this->remove_current();
            }
            $this->complete();
        } elseif ( $this->is_started() && $this->is_running() && $this->is_run_time_exceeded() ) {
            //reenable task run if previous run time limit is exceeded (e.g. due to an error)
            $this->set_is_running( false );
            error_log( "Task flagged as running, but time exceeded. Resetting the is_running flag." );
        }
        
        if ( $this->is_started() && $this->is_total_time_exceeded() ) {
            //cancel synchronization if exceeded max time from start (e.g. due to an error)
            $this->cancel();
            error_log( 'Task cancelled due to exceeded max time from start. Should end by: ' . date( $this->date_format, ( get_option( $this->start_time_key ) + $this->max_time_from_start ) ) );
        }
    }
    
    public function dispatch() {
        if ( ! wp_next_scheduled( $this->action ) && ! $this->is_started() ) {
            $this->set_is_started(true);
            $this->set_is_completed(false);
            update_option( $this->start_time_key, time() );
            wp_schedule_event( time(), 'osa_task_refresh_interval', $this->action );
            $this->log_debug('Starts in: ' . $this->get_schedule_interval());
        } else {
            error_log( 'Synchronization already started' );
            $this->unschedule();
        }
    }
    
    public function cancel() {
        $this->log_debug( 'Cancelling task' );
        $this->set_is_started( false );
        $this->unschedule();
        $this->remove_all();
        $this->set_is_running( false );
        $this->set_is_completed( false );
    }
    
    private function record_start_time() {
        $current_run_start = time();
        update_option( $this->current_run_start_time_key, $current_run_start );
        $max_end_time = $this->compute_max_task_end_time( $current_run_start );
        $this->log_debug( "Task execution started at " . date( $this->date_format, $current_run_start ) . " should end by " . date( $this->date_format, $max_end_time ) );
    }
    
    private function is_run_time_exceeded() {
        $current_run_start = get_option( $this->current_run_start_time_key, 0);
        $max_end_time = $this->compute_max_task_end_time( $current_run_start );
        return $max_end_time <= time();
    }
    
    private function is_total_time_exceeded() {
        $current_run_start = get_option( $this->current_run_start_time_key, 0);
        $max_end_time = $current_run_start + $this->max_time_from_start;
        return $max_end_time <= time();
    }
    
    private function compute_max_task_end_time( $current_run_start ) {
        return $current_run_start + $this->max_time - $this->task_run_time_buffer;
    }
    
    public function push_task($params) {
        $this->log_debug('Adding task: ' . $params['type']);
        add_option( $this->generate_key(), $params );
    }
    
    private function generate_key() {
        $tokens = explode(' ', substr( microtime() , 2 ) );
        return $this->task_key_prefix . $tokens[1] . $tokens[0];
    }
    
    private function has_current() {
        global $wpdb;

        $table  = $wpdb->options;
        $column = 'option_name';
        
        $key = $wpdb->esc_like( $this->task_key_prefix ) . '%';

        $count = $wpdb->get_var( $wpdb->prepare( "
        SELECT COUNT(*)
        FROM {$table}
        WHERE {$column} LIKE %s", $key ) );

        return $count > 0;
    }
    
    private function get_current() {
        $key = $this->get_current_key();
        
        $this->current_key = $key;
        $this->current_data = get_option($key);
        
        return $this->current_data;
    }
    
    private function remove_current() {
        $key = $this->get_current_key();
        
        delete_option($key);
    }
    
    private function remove_all() {
        $keys = $this->get_all_keys();
        foreach ( $keys as $key ) {
            $this->log_debug( 'Removing task: ' . $key->option_name );
            delete_option( $key->option_name );
        }
    }
    
    private function get_current_key() {
        global $wpdb;

        $table  = $wpdb->options;
        $column = 'option_name';
        $key_like = $wpdb->esc_like( $this->task_key_prefix ) . '%';

        $key = $wpdb->get_var( $wpdb->prepare( "
            SELECT {$column}
            FROM {$table}
            WHERE {$column} LIKE %s
            ORDER BY {$column} ASC
	        LIMIT 1", $key_like ) );
        
        return $key;
    }
    
    public function get_tasks() {
        $keys = $this->get_all_keys();
        $results = array();
                
        foreach ( $keys as $key ) {
            $params = get_option( $key->option_name );
            $params['key'] = $key->option_name;
            array_push( $results, $params );
        }
        
        return $results;
    }
    
    private function get_all_keys() {
        global $wpdb;

        $table  = $wpdb->options;
        $column = 'option_name';
        $key_like = $wpdb->esc_like( $this->task_key_prefix ) . '%';

        $keys = $wpdb->get_results( $wpdb->prepare( "
            SELECT {$column}
            FROM {$table}
            WHERE {$column} LIKE %s
            ORDER BY {$column} ASC", $key_like ) );
        
        return $keys;
    }

    private function run_task( $params ) {
        if ( isset( $params['page'] ) ) {
            $page = $params['page'];
        } else {
            $this->log_debug('Started synching ' . $params['type'] ."  from url: " . $params['url'] ); 
            $page = 0;
        }        
        $per_page = $this->per_page;
        
        do {
            $url = trim( $params['url'] );
            if ( substr( $url, -1 ) == '/' ) {
                $url = substr( $url, 0 , -1);
            }
            if ( strpos( $url, 'http' ) === false ) {
                $url = 'https://' . $url;
            }
            $result = $this->save_paged_content_for_type( $url, $params['institution_id'], $params['type'], $page, $per_page );
            
            if (empty( $result ) or ! isset( $result['response_code'])  or $result['response_code'] != 200 ) {
                error_log( 'Error occured during request to server at URL: ' . $params['url'] . ' response code: ' . $result['response_code'] );
                return false;
            }
            
            $page = $page + 1;
            $this->set_next_page( $page );
            $this->set_total_pages( $result['total_pages'] );
            if ( $this->is_run_time_exceeded() ) {
                $this->log_debug( "Run time of " . $this->max_time . "s exceeded on page " . ( $page + 1) );
                return false;
            }
            
            if ( !$this->is_started() ) {
                $this->log_debug( 'Synchonization interrupted!' );
                return false;
            }
        } while ( $result['current_size'] > 0 and $result['current_size'] == $per_page and $page <= $result['total_pages'] - 1 );
        
        return true;
    }
    
    private function save_paged_content_for_type( $url, $institutionId, $type, $page, $per_page ) {
        $response = wp_remote_get(
            esc_url_raw($url . '/api/v1/public/' . $type . '?institution_id=' . $institutionId . '&page=' . $page . '&per_page=' . $per_page)
        );

        $response_code = wp_remote_retrieve_response_code( $response );

        if ($response_code != 200) {
            error_log('Enpoint' . $url . '/api/v1/public/' . $type . '?institution_id=' . $institutionId . '&page=' . $page . '&per_page=' . $per_page . ' returned response code: ' . $response_code);
            return array( 'response_code' => $response_code );
        }

        $json_data = json_decode( wp_remote_retrieve_body( $response ) );

        if ( isset( $json_data->size ) && isset( $json_data->content ) ) {
            $current_size = $json_data->size;
            $totalPages = $json_data->totalPages;

            $this->log_debug('Syncing ' . $type . ' page: ' . ( $page + 1 ) . " of " . ( $totalPages ) );
            
            foreach ($json_data->content as $value) {
                $object = OSA_Data::fromJson($type, $value);
                if ( $this->_can_save( $object ) ) {
                    $post_id = $object->save();
                    if ( $type == 'documents' || $type == 'units' ) {
                        $this->upload_files($url, $value, $post_id);
                    }
                }
            }

            return array( 'response_code' => $response_code, 'current_size' =>  $current_size, 'total_pages' => $totalPages);
        }

        return array( 'response_code' => $response_code, 'current_size' => 0, 'total_pages' => $totalPages );
    }

    private function _can_save( $data ) {
        if ( !empty( $this->collections_filter ) ) {
            foreach ( $this->collections_filter as $collection_id ) {
                if ( strpos( $data->id, $collection_id ) === 0 ) {
                    return true;
                }
            }
            return false;
        }

        return true;
    }
    
    private function upload_files($url, $document, $post_id) {
        if ( isset( $document->contentFiles ) ) {
            $is_first = true;
            $attachment_ids = array();
            foreach ( $document->contentFiles as $file ) {
                $id = $this->upload_file( $url, $file, $post_id );
                if ( $is_first ) {
                    update_post_meta( $post_id, '_thumbnail_id', $id );
                }
                $attachment_ids[] = $id;
            }
            update_post_meta( $post_id, 'osa_attachment_files', $attachment_ids );
        }
    }
    
    function alter_upload_dir( $upload ) {
        $upload['subdir'] = '/';
        $upload['path'] = $upload['basedir'];
        $upload['url']  = $upload['baseurl'];
        return $upload;
    }
    
    private function upload_file($url, $file, $post_id) {
        if ( !defined( 'ALLOW_UNFILTERED_UPLOADS' ) ) {
            define( 'ALLOW_UNFILTERED_UPLOADS', true );
        }
        require_once ABSPATH . 'wp-admin/includes/file.php';
        //TODO change to public API
        $url = ( strpos( $url, 'http' ) === false ) ? 'https://' . $url : $url;
        $tmp_file = download_url( $url . '/api/files/view/' . $file->id );
        if ( is_wp_error( $tmp_file ) ) {
            @unlink( $tmp_file );
            error_log( "Error downloading file from URL: " . $url . " " . var_export( $tmp_file->get_error_messages(), true ) );
        } else {                        
            $attachment_info = array(
                'name' => $file->name,
                'type' => $file->mimeType,
                'tmp_name' => $tmp_file
            );
            
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            
            $filename_function = $this->prepare_path_for_attachment( $post_id, $file->name );
                 
            $overrides = array(
                'test_form' => false,
                'unique_filename_callback' => $filename_function
                );
            
            //remove time component from base dir
            add_filter( 'upload_dir', array( $this, 'alter_upload_dir' ) );
            //here is created the attachment and the uploaded file is moved to its permanent destination
            $attachment_file = wp_handle_sideload( $attachment_info, $overrides ) ;
            
            remove_filter( 'upload_dir' , array( $this, 'alter_upload_dir' ) );
            
            if ( is_wp_error($attachment_file) ) {
                @unlink($tmp_file);
                error_log( "Error creating attachment file: " . var_export( $attachment_file->get_error_messages(), true ) );
            } else {
                $attachment = array(
                    'post_mime_type' => $file->mimeType,
                    'guid' => $attachment_file['url'],
                    'file' => $attachment_file['file'],
                    'post_parent' => $post_id,
                    'post_title' => preg_replace('/\.[^.]+$/', '', basename($attachment_file['file'])),
                    'post_content' => '',
                    'post_status' => 'inherit'
                );
                //check if file already exists
                $existing_file_id = $this->get_post_id_for_osa_file_id( $file->id );
                if ( $existing_file_id > 0 ){
                    //set id to modifu the attachment to prevent creation of new one
                    $attachment['ID'] = $existing_file_id;
                }
                               
                $id = wp_insert_attachment( $attachment, false, $post_id );
                if ( is_wp_error($id) ) {
                    @unlink($tmp_file);
                    error_log( "Error creating attachment metadata: " . var_export( $id->get_error_messages(), true ) );
                } else {
                    wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $attachment_file['file'] ) );
                    $this->set_post_id_for_osa_file_id( $id, $file->id );
                    @unlink($tmp_file);
                    return $id;
                }
            }
        }
        @unlink($tmp_file);
    }
    
    //create custom path, use lambda to pass $post_id to callback function
    private function prepare_path_for_attachment( $post_id, $file_name ) {      
        $osa_id = OSA_Data::get_post_meta_by_post_id($post_id, "osa_id");
        $osa_parent_id = OSA_Data::get_post_meta_by_post_id($post_id, "osa_parentId");
        $osa_type = OSA_Data::get_post_meta_by_post_id($post_id, "osa_level");
        $up_dir = wp_upload_dir();
        $path = $this->get_parent_path($osa_parent_id, $osa_type) . $osa_id;

        //create target dir if not exists
        $target = $up_dir['basedir'] . '/'  . $path;
        if ( !is_dir( $target ) ) {
            mkdir($target, 0777, true);
        }
        $name_path = $path . '/' . $file_name;
        return function( $dir, $filename ) use ( &$name_path ) {
            return $name_path;
        };
    }
    
    //recursive function
    private function get_parent_path( $parent_osa_id, $post_type ) {
        switch ( $post_type ) {
            case 'document':
                $parent_post_id = OSA_Data::find_post_id_by_osa_id( $parent_osa_id, 'unit' );
                break;
            case 'unit':
            case 'series':
                $parent_post_id = $this->_get_parent_for_unit_or_series( $parent_osa_id );
                break;
            default:
                return $parent_osa_id . '/';
        }

        if ( $parent_post_id > 0 ) {
            $new_type = OSA_Data::get_post_meta_by_post_id($parent_post_id, 'osa_level');
            if ( $new_type == 'collection' ) {
                $institution_id = $new_type = OSA_Data::get_post_meta_by_post_id($parent_post_id, 'osa_institutionId');
                return $institution_id . '/' . $parent_osa_id . '/';
            }
            $new_parent_id = OSA_Data::get_post_meta_by_post_id($parent_post_id, 'osa_parentId');
            
            return $this->get_parent_path($new_parent_id, $new_type) . $parent_osa_id . '/';
        }
        
        return $parent_osa_id . '/';
    }
    
    private function _get_parent_for_unit_or_series($osa_id) {
        $parent_post_id = OSA_Data::find_post_id_by_osa_id($osa_id, 'series');
        if ( $parent_post_id == 0) {
            $parent_post_id = OSA_Data::find_post_id_by_osa_id($osa_id, 'collection');
        }
        return $parent_post_id;
    }
    
    private function set_post_id_for_osa_file_id( $wp_post_id, $osa_file_id ) {
        update_post_meta($wp_post_id, 'osa_file_id', $osa_file_id);
    }
    
    private function get_post_id_for_osa_file_id( $osa_file_id ) {
        $query = new WP_Query(array(
            'post_type' => 'attachment',
            'post_status' => 'inherit',
            'meta_key' => 'osa_file_id',
            'meta_value' => $osa_file_id,
        ));
        
        if ($query->have_posts()) {
          return $query->get_posts()[0]->ID;
        } else {
          return 0;
        }
    }
    
    private function set_next_page( $page ) {
        $this->current_data['page'] = $page;
        
        update_option( $this->current_key, $this->current_data );
    }
    
    private function set_total_pages( $total_pages ) {
        $this->current_data['total_pages'] = $total_pages;
        
        update_option( $this->current_key, $this->current_data );
    }

    private function complete() {      
        $this->unschedule();
        
        //set not running
        $this->set_is_started(false );
        $this->set_is_running( false );
        $this->set_is_completed( true );
        
        $this->log_debug( "OSA sychronization task completed" );
        
        //add notifications
        set_transient( 'osa_sync_successful', true, 30 );
        update_option( $this->completed_time_key, time() );
    }
    
    private function unschedule() {
        $timestamp = wp_next_scheduled( $this->action );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, $this->action );
        }
    }
    
    private function set_is_started( $value ) {
        update_option( $this->is_started_key, $value );
    }
    
    public function is_started() {
        return get_option( $this->is_started_key, false );
    }
    
    private function set_is_running( $value ) {
        update_option( $this->is_running_key, $value );
    }
    
    public function is_running() {
        return get_option( $this->is_running_key, false);
    }
    
    private function set_is_completed( $value ) {
        update_option( $this->is_completed_key, $value );
    }
    
    public function is_completed() {
        return get_option( $this->is_completed_key, false );
    }
    
    public function get_completed_time() {
        return get_option( $this->completed_time_key, null );
    }
    
    public function get_start_time() {
        return get_option( $this->start_time_key, null );
    }
    
    private function log_debug( $message ) {
        if ( get_option( 'osa_is_debug', true ) ) {
            error_log( $message );
        }
    }
}