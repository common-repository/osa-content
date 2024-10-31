<?php

class Osa_Settings_Menu {

  private $sync_process;

  public function __construct() {
    add_action( 'admin_init', array( $this, 'osa_settings_init' ) );
    add_action( 'admin_menu', array( $this, 'wporg_options_page' ) );
    add_action( 'admin_post_osa_dispatch_sync', array( $this, 'osa_dispatch_sync' ) );
    add_action( 'admin_post_osa_cancel_sync', array( $this, 'osa_cancel_sync' ) );

    $this->sync_process = new OSA_Synchronization_queue();
    $this->display_messages();
  }

  public function display_messages() {
    if (get_transient('osa_sync_started') == true) {
      add_action( 'admin_notices', array( $this, 'osa_sync_started' ) );
    }

    if (get_transient('osa_sync_successful') == true) {
      add_action( 'admin_notices', array( $this, 'osa_sync_success' ) );
    }

    if (get_transient('osa_sync_paremeters_not_set') == true) {
        add_action( 'admin_notices', array( $this, 'osa_sync_paremeters_not_set' ) );
    }

    if (get_transient('osa_sync_process_already_started') == true) {
        add_action( 'admin_notices', array( $this, 'osa_sync_process_already_started' ) );
    }

    if (get_transient('osa_sync_cancelled') == true) {
        add_action( 'admin_notices', array( $this, 'osa_sync_cancelled' ) );
    }
  }

  function osa_settings_init() {
    // register a new setting for "reading" page
    register_setting( 'osa', 'osa_url' );
    register_setting( 'osa', 'osa_institution_id' );
    register_setting( 'osa', 'osa_import_scope' );
    register_setting( 'osa', 'osa_collection_ids' );
    register_setting( 'osa', 'osa_use_default_theme', array(
        'type' => 'boolean',
        'show_in_rest' => false,
        'default' => true
    ) );

    // register a new section in the "reading" page
    add_settings_section(
      'osa_settings_section',
      'Ustawienia pobierania z OSA',
      array( $this, 'osa_settings_section_cb' ),
      'osa'
    );

    // register a new field in the "wporg_settings_section" section, inside the "reading" page
    add_settings_field(
      'osa_settings_osa_url',
      'Adres URL OSA.*',
      array( $this, 'osa_settings_osa_url_cb' ),
      'osa',
      'osa_settings_section'
    );

    add_settings_field(
      'osa_settings_osa_institution_id',
      'Kod instytucji w OSA (np. PL_1055)*',
      array( $this, 'osa_settings_osa_institution_id_cb' ),
      'osa',
      'osa_settings_section'
    );

    add_settings_field(
      'osa_settings_osa_import_scope',
      'Pobierz:',
      array( $this, 'osa_settings_osa_import_scope_cb' ),
      'osa',
      'osa_settings_section'
    );

    add_settings_field(
        'osa_settings_osa_collection_ids',
        'Sygnatura zbiorów do pobrania z OSA. Wartości należy oddzielić przecinkami (np. PL_1055_001, PL_1055_002). W przypadku niewypełnienia, wszystkie zbiory instytucji zostaną pobrane.',
        array( $this, 'osa_settings_osa_collection_ids_cb' ),
        'osa',
        'osa_settings_section'
    );

    add_settings_field(
        'osa_use_default_theme',
        'Użyj przykładowego szablonu - należy odznaczyć, w przypadku samodzielnej implementacji wyświetlania metadanych OSA w skórce Wordpress.',
        array( $this, 'osa_use_default_theme_cb' ),
        'osa',
        'osa_settings_section'
    );
  }

  function osa_settings_section_cb( $args ) {

  }

  function osa_settings_osa_url_cb( $args ) {
    // get the value of the setting we've registered with register_setting()
    $osa_url = get_option( 'osa_url', 'https://osa.archiwa.org' );
    // output the field
    ?>
    <input type="text" name="osa_url" value="<?php echo isset( $osa_url ) ? esc_attr( $osa_url ) : ''; ?>">
    <?php
  }

  function osa_settings_osa_import_scope_cb( $args ) {
    // get the value of the setting we've registered with register_setting()
    $osa_import_scope = get_option( 'osa_import_scope' );
    // output the field
    ?>
    <input type="radio" id="osa_import_scope_all" name="osa_import_scope" value="all" <?php checked( $osa_import_scope, "all" ); ?> onclick="toggleText(this.value);">
    <label for="osa_import_scope_all">wszystkie zbiory instytucji</label><br>
    <input type="radio" id="osa_import_scope_chosen" name="osa_import_scope" value="chosen" <?php checked( $osa_import_scope, "chosen" ); ?> onclick="toggleText(this.value);">
    <label for="osa_import_scope_chosen">wybrane zbiory</label><br>
    <script type="text/javascript">
      function toggleText(val) {
      if(val=='all')
              document.getElementById('ids_textarea').disabled = true;
      else if(val=='chosen')
              document.getElementById('ids_textarea').disabled = false;
      }
    </script>
    <?php
  }

  function osa_settings_osa_institution_id_cb( $args ) {
    // get the value of the setting we've registered with register_setting()
    $osa_institution_id = get_option( 'osa_institution_id' );
    // output the field
    ?>
    <input type="text" name="osa_institution_id" value="<?php echo isset( $osa_institution_id ) ? esc_attr( $osa_institution_id ) : ''; ?>">
    <?php
  }

  function osa_settings_osa_collection_ids_cb( $args ) {
    // get the value of the setting we've registered with register_setting()
    $osa_collection_ids = get_option( 'osa_collection_ids' );
    // output the field
    ?>
    <textarea id="ids_textarea" name="osa_collection_ids"><?php echo isset( $osa_collection_ids ) ? esc_attr( $osa_collection_ids ) : ''; ?></textarea>
    <script type="text/javascript">
      function textDefault() {
        var scopeAllIsChecked = document.getElementById('osa_import_scope_all').checked;
        if (scopeAllIsChecked) {
          document.getElementById('ids_textarea').disabled = true;
        } else {
          document.getElementById('ids_textarea').disabled = false;
        }
      }
      textDefault()
    </script>
    <?php
  }

  function osa_use_default_theme_cb( $args ) {
    // get the value of the setting we've registered with register_setting()
    $osa_use_default_theme = get_option( 'osa_use_default_theme', true );
    // output the field
    ?>
    <input type="checkbox" id="osa_use_default_theme" name="osa_use_default_theme" value="1" <?php checked( $osa_use_default_theme, 1 ); ?>>
    <?php
  }

  function osa_options_page_html() {
    // check user capabilities
    if ( !current_user_can( 'manage_options' ) ) {
        return;
    }
    ?>
    <div class="wrap">
        <h1><?= esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields( 'osa' );
            // output setting sections and their fields
            // (sections are registered for "wporg", each field is registered to a specific section)
            do_settings_sections( 'osa' );
            submit_button( 'Zapisz ustawienia' );
            ?>
        </form>

        <!-- Display task status -->

        <h2><?= esc_html( 'Pobieranie danych z OSA' ); ?></h2>
        <table class='form-table'>
            <tbody>
                <tr>
                    <th><?= esc_html("Data rozpoczęcia ostatniego pobierania"); ?></th>
                    <td><?= ( $this->sync_process->get_start_time() ) ? $this->format_date( $this->sync_process->get_start_time() ) : "Nieznana"; ?></td>
                </tr>
                <tr>
                    <th><?= esc_html("Status"); ?></th>
                    <td><?= ( $this->sync_process->is_completed() ) ? "Zakończony" : ( ( $this->sync_process->is_started() ) ? "Rozpoczęty" : "Nieznany" ); ?></td>
                </tr>

                <?php if ($this->sync_process->is_completed()): ?>
                <tr>
                    <th><?= esc_html("Data zakończenia"); ?></th>
                    <td><?= $this->format_date( $this->sync_process->get_completed_time() ); ?></td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($this->sync_process->is_started()): ?>
        <h3><?= esc_html("Lista bieżących zadań pobierania:"); ?></h3>
        <h5>Należy odświeżyć stronę, aby zobaczyć postęp...</h5>
        <table class='form-table'>
            <tbody>
            <?php foreach ( $this->sync_process->get_tasks() as $task ) { ?>
                <tr>
                    <td><?= esc_html('Pobieranie danych typu "' . $task['type'] .'"'); ?></td>
                    <td><?= esc_html( ( isset( $task['page'] ) && isset( $task['total_pages'] ) ) ? sprintf('Strona %s z %s', $task['page'], $task['total_pages']) : 'oczekuje...'); ?></td>
                </tr>
            <?php  } ?>
           </tbody>
        </table>
        <?php endif; ?>

        <!-- Run sync task -->
        <!-- TODO: add nonce -->
        <form action="<?php echo admin_url('admin-post.php'); ?>" method="post">
            <?php if ($this->sync_process->is_started()): ?>
            <input type="hidden" name="action" value="osa_cancel_sync">
            <input type="submit" value="Przerwij pobieranie" class="button button-primary">
            <?php else: ?>
            <p><strong>UWAGA:</strong> przed rozpoczęciem pobierania należy zapisać ustawienia (przycisk powyżej)</p>
            <input type="hidden" name="action" value="osa_dispatch_sync">
            <input type="submit" value="Pobierz dane z OSA" class="button button-primary">
            <?php endif; ?>
        </form>
    </div>
    <?php
  }

  private function format_date( $timestamp ) {
    $date = new DateTime();
    $format = $this->sync_process->date_format;
    $date->setTimestamp( $timestamp );

    $timezone_offset = get_option( 'gmt_offset' );

    if ( $timezone_offset != 0 ) {
        $timezone = timezone_name_from_abbr( '', $timezone_offset * 60 * 60, 1 );
        $date->setTimezone( new DateTimeZone( $timezone) );
    }

    return $date->format( $format );
  }

  function wporg_options_page() {
    add_submenu_page(
        'tools.php',
        'Wtyczka OSA',
        'Wtyczka OSA',
        'manage_options',
        'osa',
        array( $this, 'osa_options_page_html' )
    );
  }

  function osa_cancel_sync() {
      $this->sync_process->cancel();

      set_transient( 'osa_sync_cancelled', true, 30 );
      delete_transient( 'osa_sync_started' );
      delete_transient( 'osa_sync_paremeters_not_set' );

      wp_redirect( 'tools.php?page=osa' );
      exit;
  }

  function osa_dispatch_sync() {

    $institution_id = get_option( 'osa_institution_id' );
    $url = get_option( 'osa_url', 'https://osa.archiwa.org' );

    if ( empty( $institution_id ) || empty( $url ) ) {
        set_transient( 'osa_sync_paremeters_not_set', true, 15 );
        delete_transient( 'osa_sync_started' );
        delete_transient( 'osa_sync_cancelled' );
    } elseif ( ! $this->sync_process->is_started() ) {
        $this->sync_process->push_task( array( 'url' => $url, 'institution_id' => $institution_id, 'type' => 'collections' ) );
        $this->sync_process->push_task( array( 'url' => $url, 'institution_id' => $institution_id, 'type' => 'series' ) );
        $this->sync_process->push_task( array( 'url' => $url, 'institution_id' => $institution_id, 'type' => 'units' ) );
        $this->sync_process->push_task( array( 'url' => $url, 'institution_id' => $institution_id, 'type' => 'documents' ) );

        $this->sync_process->dispatch();
        set_transient( 'osa_sync_started', true, 30 );
        delete_transient( 'osa_sync_paremeters_not_set' );
        delete_transient( 'osa_sync_cancelled' );
    } else {
        set_transient( 'osa_sync_process_already_started', true, 15 );
    }

    wp_redirect( 'tools.php?page=osa' );
    exit;
  }

  function osa_parameters_not_set() {
      ?>
      <div class="error notice">
          <p><?php _e( 'Należy ustawić adres URL OSA oraz ID instytucji przed synchronizacją!', 'osa_sync_textdomain' ); ?></p>
      </div>
      <?php
  }

  function osa_sync_started() {
      ?>
      <div class="update-nag notice">
          <p><?php _e( 'Pobieranie z OSA rozpoczęte!', 'osa_sync_textdomain' ); ?></p>
      </div>
      <?php
  }

  function osa_sync_success() {
      ?>
      <div class="updated notice">
          <p><?php _e( 'Pobieranie z OSA zakończone sukcesem!', 'osa_sync_textdomain' ); ?></p>
      </div>
      <?php
  }

  function osa_sync_cancelled() {
      ?>
      <div class="error notice">
          <p><?php _e( 'Pobieranie z OSA zostało przerwane.', 'osa_sync_textdomain' ); ?></p>
      </div>
      <?php
  }

  function osa_sync_process_already_started() {
      ?>
      <div class="error notice">
          <p><?php _e( 'Pobieranie z OSA już trwa! Przerwij bieżące lub poczekaj na zakończenie pobierania.', 'osa_sync_textdomain' ); ?></p>
      </div>
      <?php
  }
}