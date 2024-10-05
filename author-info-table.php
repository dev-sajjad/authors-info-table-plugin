<?php
/*
Plugin Name: Author Info Table
Description: Displays a table of author information using a shortcode with pagination.
Version: 1.0.0
Author: dev-sajjad
*/

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}

class Author_Info_Table {

	private static $instance = null;
	private $authors_per_page = 10;

	/**
	 * Get the singleton instance of the class.
	 *
	 * @return Author_Info_Table The single instance of the class.
	 */
	public static function get_instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor to prevent direct instantiation.
	 */
	private function __construct() {
		$this->define_constants();
		$this->init_hooks();
	}

	/**
	 * Define plugin constants.
	 */
	private function define_constants() {
		define('AIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
		define('AIT_PLUGIN_URL', plugin_dir_url(__FILE__));
	}

	/**
	 * Initialize WordPress hooks.
	 */
	private function init_hooks() {
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
		add_shortcode('author_info_table', array($this, 'author_table_shortcode'));
		add_action('wp_ajax_load_authors_page', array($this, 'ajax_load_authors_page'));
		add_action('wp_ajax_nopriv_load_authors_page', array($this, 'ajax_load_authors_page'));
	}

	/**
	 * Enqueue necessary scripts and styles.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style('author-table-styles', AIT_PLUGIN_URL . 'assets/css/author-table-styles.css');
		wp_enqueue_script('author-table-script', AIT_PLUGIN_URL . 'assets/js/author-table-script.js', array('jquery'), '1.0', true);
		wp_localize_script('author-table-script', 'authorTableAjax', array(
			'ajax_url' => admin_url('admin-ajax.php')
		));
	}

	/**
	 * Shortcode callback function to display the author table.
	 *
	 * @param array $atts Shortcode attributes (unused in this implementation).
	 * @return string HTML output of the author table and pagination.
	 */
	public function author_table_shortcode($atts) {
		$authors = get_users(array('who' => 'authors'));
		$total_authors = count($authors);
		$total_pages = ceil($total_authors / $this->authors_per_page);

		ob_start();

		echo '<div id="author-info-table-container">';
		echo $this->get_author_table_html($authors, 1);
		echo '</div>';
		echo $this->get_pagination_html($total_pages, 1);

		return ob_get_clean();
	}

	/**
	 * Generate HTML for the author table.
	 *
	 * @param array $authors List of author objects.
	 * @param int $page Current page number.
	 * @return string HTML of the author table.
	 */
	private function get_author_table_html($authors, $page) {
		$start = ($page - 1) * $this->authors_per_page;
		$authors_slice = array_slice($authors, $start, $this->authors_per_page);

		ob_start();

		echo '<table class="author-info-table">';
		echo '<thead><tr><th>Author Name</th><th>Author Email</th><th>Total Published Posts</th><th>Joining Date</th></tr></thead>';
		echo '<tbody>';

		foreach ($authors_slice as $author) {
			$author_posts = count_user_posts($author->ID, 'post', true);
			$joining_date = date('F j, Y', strtotime($author->user_registered));

			echo '<tr>';
			echo '<td>' . esc_html($author->display_name) . '</td>';
			echo '<td>' . esc_html($author->user_email) . '</td>';
			echo '<td>' . esc_html($author_posts) . '</td>';
			echo '<td>' . esc_html($joining_date) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';

		return ob_get_clean();
	}

	/**
	 * Generate HTML for pagination controls.
	 *
	 * @param int $total_pages Total number of pages.
	 * @param int $current_page Current page number.
	 * @return string HTML of pagination controls.
	 */
	private function get_pagination_html($total_pages, $current_page) {
		$total_authors = count (get_users(array('who' => 'authors')));
		$per_page_options = [10, 15, 20];

		ob_start();

		echo '<div class="ait-pagination">';
		// Total number of authors
		echo '<span class="ait-pagination__total">Total : ' . $total_authors. '</span>';
		// Authors per page selector
		echo '<select class="ait-pagination__per-page" id="authors-per-page">';
		foreach ($per_page_options as $option) {
			$selected = ($option == $this->authors_per_page) ? 'selected' : '';
			echo "<option value='{$option}' {$selected}>{$option} / page</option> >";
		}
		echo '</select>';
		// Prev button
		$prev_class = $current_page <= 1 ? 'ait-pagination__btn ait-pagination__btn--disabled' : 'ait-pagination__btn';
		echo '<button class="' . $prev_class . '" ' . ($current_page <= 1 ? 'disabled' : '') . ' data-page="' . ($current_page - 1) . '">Prev</button>';

		// Page numbers
		echo '<ul class="ait-pagination__pager">';
		for ($i = 1; $i <= $total_pages; $i++) {
			if ($i === 1 || $i === $total_pages || ($i >= $current_page - 2 && $i <= $current_page + 2)) {
				$class = $i === $current_page ? 'ait-pagination__number ait-pagination__number--active' : 'ait-pagination__number';
				echo '<li><button class="' . $class . '" data-page="' . $i . '">' . $i . '</button></li>';
			} elseif ($i === $current_page - 3 || $i === $current_page + 3) {
				echo '<li><span class="ait-pagination__more">...</span></li>';
			}
		}
		echo '</ul>';

		// Next button
		$next_class = $current_page >= $total_pages ? 'ait-pagination__btn ait-pagination__btn--disabled' : 'ait-pagination__btn';
		echo '<button class="' . $next_class . '" ' . ($current_page >= $total_pages ? 'disabled' : '') . ' data-page="' . ($current_page + 1) . '">Next</button>';

		echo '</div>';

		return ob_get_clean();
	}

	/**
	 * AJAX callback to load a specific page of authors.
	 * Updates the table and pagination HTML.
	 */
	public function ajax_load_authors_page() {
		$page = isset($_POST['page']) ? intval($_POST['page']) : 1;
		$per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : $this->authors_per_page;
		$this->authors_per_page = $per_page; // Update the authors_per_page property

		$authors = get_users(array('who' => 'authors'));
		$total_authors = count($authors);
		$total_pages = ceil($total_authors / $this->authors_per_page);

		$response = array(
			'html' => $this->get_author_table_html($authors, $page),
			'pagination' => $this->get_pagination_html($total_pages, $page)
		);

		wp_send_json_success($response);
	}
}

// Initialize the plugin
function ait_initialize() {
	Author_Info_Table::get_instance();
}
add_action('plugins_loaded', 'ait_initialize');