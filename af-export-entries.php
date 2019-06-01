<?php
/**
 * Plugin Name:     Advanced Forms Pro: Export Entries
 * Description:     Adds Export Metabox w/ Button
 * Author:          WightSpace
 * Text Domain:     w2g-form-export
 * Domain Path:     /languages
 * Version:         0.0.1
 *
 * @package         af_export_Form_Export
 */

// Your code starts here.


class AF_Export_Entries {

	private $form;
	private $post;

	/**
	 * Constructor
	 */
	public function __construct() {

		// Get field groups for the current form
		add_action( 'add_meta_boxes', array( $this, 'afee_export_cvs_meta_box') );

		// Create end-points
		add_filter( 'query_vars', array( $this, 'afee_query_vars') );

		// This is not working at the moments
		add_action( 'admin_notices', array($this, 'afee_export_admin_notices_action') );
	}

	/**
	 * Creates MetaBox
	 *
	 * @return void
	 */
	public function afee_export_cvs_meta_box() {

		global $post;
		$this->post = $post;
		$this->form = af_get_form( $this->post->ID );

		$this->export_cvs_entries();

		add_meta_box( 'af-export-cvs-meta-box', 'Export Form Entries', array( $this, 'fields_meta_box_callback' ), 'af_form', 'side', 'default', null );
	}

	public function afee_export_admin_notices_action() {
		add_settings_error('no_form_entries_found', '', 'No Entires Found!', 'error');
	}

	/**
	 * Sets Query Var
	 *
	 * @param [type] $query_vars
	 * @return void
	 */
	public function afee_query_vars($query_vars) {
		$query_vars[] = 'afee_export_cvs';
		return $query_vars;
	}


	/**
	 * Gets Entires From Af Entires
	 *
	 * @return void
	 */
	public function export_cvs_entries() {

		$afee_export_cvs = isset($_GET["afee_export_cvs"]) ? $_GET["afee_export_cvs"] : false; //get_query_var('afee_export_cvs', 'false');
		$post_type = get_post_type(get_the_ID());
		$file_name = sanitize_title(get_the_title() . '_' . date('Y-m-d'));

		if (  $post_type == 'af_form' &&  $afee_export_cvs == true ) {


			$field_groups = af_get_form_field_groups( $this->form['key'] );

			$af_entry = get_posts(
				array(
					'post_type' => 'af_entry',
					'numberposts' => '-1'
				)
			);

			wp_reset_postdata();

			$export_header = array();
			$export_body = array();

			if ( $af_entry ) :

				foreach ( $af_entry as $entry ) :

					setup_postdata( $entry );

					// Tried using ACF get_field() for these entries but it break AF PRO entire page
					$entry_form = get_post_meta($entry->ID, 'entry_form', true);

					if( $entry_form == $this->form['key'] ) :

						foreach ( $field_groups as $field_group ) :
							$fields = acf_get_fields( $field_group );
							$export_header = $fields;

							foreach ( $fields as $field ) :

								$label = $field['label'];
								$value = get_post_meta($entry->ID, $field['name'], true);

								if($value) {
									if(is_array($value)) {
										$export_body[] = implode(',', $value);
									}else {
										$export_body[] = $value;
									}
								}else {
									$export_body[] = "null";
								}

							endforeach;

						endforeach;

					endif;

				endforeach;

				if(count($export_header) > 0 && count($export_body) > 0) {

					$export_header = array_column($export_header, 'label');
					$export_body = array_chunk($export_body, count($fields) );
					$file_name =

					$this->array_to_csv_download(
						$export_header,
						$export_body,
						$file_name
					);

				}else {
					settings_errors( 'no_form_entries_found' );
				}


			endif;

		}
	}


	/**
	 * Create MetaBox Fields
	 *
	 * @return void
	 */
	public function fields_meta_box_callback() {

		$field_form_create_entries = get_field('field_form_create_entries');

		echo sprintf( '<p>%s</p>', _e( 'This will export all Entries related to this form as an CVS file.', 'advanced-forms' ) );

		if( $field_form_create_entries ) {
			echo sprintf( '<a class="button button-large" href="%s">%s</a>', admin_url() . '/post.php?post=' . $this->post->ID . '&action=edit&afee_export_cvs=true' , 'Export' );

		}else {
			echo sprintf( '<p>%s</p>', _e( 'Create entries must be set to True.', 'advanced-forms' ) );

		}

	}

	/**
	 * Puts all data gathered from AF_Entires
	 * and saves them to file
	 *
	 * @param array $export_header
	 * @param array $export_body
	 * @param string $filename
	 * @param string $delimiter
	 * @return void
	 */
	public function array_to_csv_download($export_header, $export_body, $filename = "export.csv", $delimiter=";") {

		header('Content-Type: application/csv');
		header('Content-Disposition: attachment; filename="'.$filename.'";');

		if (ob_get_length()) {
			ob_clean();
		}

		$f = fopen('php://output', 'w');

		fputcsv($f, $export_header, $delimiter, ' ');

		foreach ($export_body as $line) {

			fputcsv($f, $line, $delimiter, ' ');

		}

		exit();

	}

}

if( class_exists( 'AF' ) ) {
	// Instantiate a singleton of this plugin
	new AF_Export_Entries();
}
