<?php
/**
 * Plugin Name:       FAQ Accordion
 * Plugin URI:        https://zubeidhendricks.dev/wp-plugins/faq-accordion-block
 * Description:        Build collapsible FAQ accordions with a shortcode — and automatically output FAQ schema so Google can show rich results.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            Zubeid Hendricks
 * Author URI:        https://zubeidhendricks.dev
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       faq-accordion-block
 *
 * @package FaqAccordionBlock
 */

defined( 'ABSPATH' ) || exit;

define( 'FAQ_ACCORDION_BLOCK_VERSION', '1.0.0' );

require_once __DIR__ . '/includes/factory-core.php';

/**
 * FAQ Accordion.
 */
final class FaqAccordionBlock extends ZubFactory_Plugin {

	/** @var array Collected Q&A pairs for schema output. */
	private $schema_items = array();

	private $styled = false;

	protected function configure() {
		$this->slug    = 'faq-accordion-block';
		$this->title   = 'FAQ Accordion';
		$this->version = FAQ_ACCORDION_BLOCK_VERSION;
	}

	protected function settings_fields() {
		return array(
			'accent' => array(
				'label'   => __( 'Accent colour', 'faq-accordion-block' ),
				'type'    => 'color',
				'default' => '#2271b1',
			),
			'schema' => array(
				'label'    => __( 'Rich results', 'faq-accordion-block' ),
				'type'     => 'checkbox',
				'cb_label' => __( 'Output FAQPage schema for Google rich results', 'faq-accordion-block' ),
				'default'  => 1,
			),
			'open_first' => array(
				'label'    => __( 'First item', 'faq-accordion-block' ),
				'type'     => 'checkbox',
				'cb_label' => __( 'Open the first question by default', 'faq-accordion-block' ),
				'default'  => 0,
			),
		);
	}

	protected function hooks() {
		add_shortcode( 'faq', array( $this, 'wrap' ) );
		add_shortcode( 'faq_item', array( $this, 'item' ) );
		add_action( 'wp_footer', array( $this, 'output_schema' ) );
	}

	/** [faq] ... [/faq] */
	public function wrap( $atts, $content = '' ) {
		$accent = $this->option( 'accent', '#2271b1' ) ?: '#2271b1';
		$inner  = do_shortcode( (string) $content );

		ob_start();
		if ( ! $this->styled ) {
			$this->styled = true;
			$this->styles( $accent );
		}
		echo '<div class="zfaq">' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
		return ob_get_clean();
	}

	/** [faq_item q="Question?"]Answer[/faq_item] */
	public function item( $atts, $content = '' ) {
		$atts = shortcode_atts( array( 'q' => '' ), $atts, 'faq_item' );
		$q    = trim( (string) $atts['q'] );
		if ( '' === $q ) {
			return '';
		}

		$answer_html = do_shortcode( wpautop( trim( (string) $content ) ) );

		// Stash for schema.
		$this->schema_items[] = array(
			'q' => $q,
			'a' => wp_strip_all_tags( $answer_html ),
		);

		$open = $this->option( 'open_first', 0 ) && 1 === count( $this->schema_items );

		ob_start();
		?>
		<details class="zfaq-item" <?php echo $open ? 'open' : ''; ?>>
			<summary class="zfaq-q"><?php echo esc_html( $q ); ?></summary>
			<div class="zfaq-a"><?php echo wp_kses_post( $answer_html ); ?></div>
		</details>
		<?php
		return ob_get_clean();
	}

	/** Print FAQPage JSON-LD once per page. */
	public function output_schema() {
		if ( ! $this->option( 'schema', 1 ) || empty( $this->schema_items ) ) {
			return;
		}
		$entities = array();
		foreach ( $this->schema_items as $row ) {
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $row['q'],
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $row['a'],
				),
			);
		}
		$data = array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
		echo '<script type="application/ld+json">'
			. wp_json_encode( $data )
			. '</script>' . "\n";
	}

	private function styles( $accent ) {
		?>
		<style>
			.zfaq{margin:16px 0;font-family:inherit}
			.zfaq-item{border:1px solid #e2e8f0;border-radius:8px;margin-bottom:10px;overflow:hidden}
			.zfaq-q{cursor:pointer;padding:14px 16px;font-weight:600;list-style:none;
				position:relative;padding-right:40px}
			.zfaq-q::-webkit-details-marker{display:none}
			.zfaq-q::after{content:"+";position:absolute;right:16px;top:50%;transform:translateY(-50%);
				font-size:22px;color:<?php echo esc_attr( $accent ); ?>;line-height:1}
			details[open] .zfaq-q::after{content:"\2212"}
			.zfaq-a{padding:0 16px 14px;line-height:1.6}
		</style>
		<?php
	}
}

add_action(
	'plugins_loaded',
	function () {
		( new FaqAccordionBlock( __FILE__ ) )->boot();
	}
);
